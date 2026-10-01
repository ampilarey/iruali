<?php

namespace App\Services;

use App\Models\ReturnRequest;
use App\Models\SellerAdjustment;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReturnRequested;
use App\Notifications\ReturnUpdated;
use App\Support\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Returns and refunds.
 *
 * requested → approved (refund amount set, stock optionally returned, the shop's share taken back
 * from its earnings) → refunded (money sent back, reference recorded); or → rejected.
 */
class ReturnService
{
    public const DISK = 'local';

    /**
     * Can the customer ask to return items from this shop's part? Delivered, within the return
     * window, nothing already open for it, and something left to return.
     */
    public function canRequest(SellerOrder $part, User $user): bool
    {
        return $part->order?->user_id === $user->id
            && $part->status === 'delivered'
            && $this->withinWindow($part)
            && ! $part->returnRequests()->whereIn('status', ['requested', 'approved'])->exists()
            && $this->returnableItems($part)->isNotEmpty();
    }

    public function withinWindow(SellerOrder $part): bool
    {
        $delivered = $part->delivered_at ?? $part->updated_at;

        return $delivered && $delivered->copy()->addDays(Company::returnWindowDays())->endOfDay()->isFuture();
    }

    /**
     * This shop's order items with how many can still be returned (not in an earlier, non-rejected request).
     *
     * @return Collection<int, array{item: \App\Models\OrderItem, max: int}>
     */
    public function returnableItems(SellerOrder $part): Collection
    {
        $returned = DB::table('return_request_items')
            ->join('return_requests', 'return_requests.id', '=', 'return_request_items.return_request_id')
            ->where('return_requests.seller_order_id', $part->id)
            ->where('return_requests.status', '!=', 'rejected')
            ->selectRaw('order_item_id, sum(quantity) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        return $part->items()->with('product')->get()
            ->map(fn ($item) => ['item' => $item, 'max' => max(0, $item->quantity - (int) ($returned[$item->id] ?? 0))])
            ->filter(fn ($row) => $row['max'] > 0)
            ->values();
    }

    /**
     * @param  array<int, int>  $quantities  order_item_id => quantity to return
     */
    public function request(SellerOrder $part, User $user, array $quantities, string $reason, ?string $details, ?UploadedFile $photo): ?ReturnRequest
    {
        $returnable = $this->returnableItems($part)->keyBy(fn ($row) => $row['item']->id);
        $lines = collect($quantities)
            ->map(fn ($qty, $itemId) => ['id' => (int) $itemId, 'qty' => (int) $qty])
            ->filter(fn ($l) => $l['qty'] > 0 && isset($returnable[$l['id']]))
            ->map(fn ($l) => ['id' => $l['id'], 'qty' => min($l['qty'], $returnable[$l['id']]['max'])]);

        if ($lines->isEmpty()) {
            return null;
        }

        $value = $lines->sum(fn ($l) => $returnable[$l['id']]['item']->price * $l['qty']);

        $request = DB::transaction(function () use ($part, $user, $lines, $reason, $details, $photo, $value) {
            $request = ReturnRequest::create([
                'order_id' => $part->order_id,
                'seller_order_id' => $part->id,
                'user_id' => $user->id,
                'status' => 'requested',
                'reason' => $reason,
                'details' => $details,
                'photo_path' => $photo?->storeAs('return-photos', $part->order->order_number.'-'.Str::random(8).'.'.$photo->extension(), self::DISK),
                'items_value' => round($value, 2),
            ]);
            foreach ($lines as $line) {
                $request->items()->create(['order_item_id' => $line['id'], 'quantity' => $line['qty']]);
            }

            return $request;
        });

        $this->notifyRequested($request);

        return $request;
    }

    /**
     * The refund we suggest: the items, plus the order's delivery fee when the shop was at fault and
     * this is the only shop in the order. Admins can change it.
     */
    public function suggestedRefund(ReturnRequest $request): float
    {
        $order = $request->order;
        $refund = (float) $request->items_value;

        if (in_array($request->reason, ReturnRequest::SHOP_FAULT, true) && $order->sellerOrders()->count() === 1) {
            $refund += (float) $order->shipping_amount;
        }

        return round(min($refund, $this->refundableLeft($request)), 2);
    }

    /**
     * Most that can still be refunded on the order (its total minus refunds already approved).
     */
    public function refundableLeft(ReturnRequest $request): float
    {
        $taken = (float) ReturnRequest::where('order_id', $request->order_id)
            ->whereIn('status', ['approved', 'refunded'])->whereKeyNot($request->id)->sum('refund_amount');

        return max(0, round((float) $request->order->total_amount - $taken, 2));
    }

    public function approve(ReturnRequest $request, float $refund, bool $restock, ?string $note, User $admin): bool
    {
        if ($request->status !== 'requested' || $refund < 0 || $refund > $this->refundableLeft($request) + 0.001) {
            return false;
        }

        DB::transaction(function () use ($request, $refund, $restock, $note, $admin) {
            $request->update([
                'status' => 'approved',
                'refund_amount' => round($refund, 2),
                'restocked' => $restock,
                'admin_note' => $note,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ]);

            if ($restock) {
                foreach ($request->items()->with('orderItem.product')->get() as $line) {
                    if ($line->orderItem?->product) {
                        app(OrderService::class)->restock($line->orderItem->product, $line->orderItem->product_variant_id, $line->quantity);
                    }
                }
            }

            // Take back what the shop earned on the returned items (their price less commission).
            $part = $request->sellerOrder;
            if ($part?->seller_id) {
                // The shop gives back its share of what the customer is actually refunded for the items
                $base = min((float) $refund, (float) $request->items_value);
                $shopShare = round($base * (1 - (float) $part->commission_rate / 100), 2);
                if ($shopShare > 0) {
                    SellerAdjustment::create([
                        'seller_id' => $part->seller_id,
                        'amount' => -$shopShare,
                        'reason' => 'Return on order '.$request->order->order_number,
                        'return_request_id' => $request->id,
                    ]);
                }
            }
        });

        $this->notifyCustomer($request->fresh());

        return true;
    }

    public function reject(ReturnRequest $request, ?string $note, User $admin): bool
    {
        if ($request->status !== 'requested') {
            return false;
        }

        $request->update(['status' => 'rejected', 'admin_note' => $note, 'resolved_by' => $admin->id, 'resolved_at' => now()]);
        $this->notifyCustomer($request->fresh());

        return true;
    }

    public function markRefunded(ReturnRequest $request, string $reference): bool
    {
        if ($request->status !== 'approved') {
            return false;
        }

        $request->update(['status' => 'refunded', 'refund_reference' => $reference, 'refunded_at' => now()]);
        $this->notifyCustomer($request->fresh());

        return true;
    }

    protected function notifyRequested(ReturnRequest $request): void
    {
        try {
            if ($seller = $request->sellerOrder?->seller) {
                $seller->notify(new ReturnRequested($request));
            }
            if ($email = trim((string) Setting::get('contact_email'))) {
                Notification::route('mail', $email)->notify(new ReturnRequested($request));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function notifyCustomer(ReturnRequest $request): void
    {
        try {
            $request->user?->notify(new ReturnUpdated($request));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
