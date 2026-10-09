<?php

namespace App\Services;

use App\Enums\SellerOrderStatus;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\SellerOrderShipped;
use Illuminate\Support\Facades\DB;

/**
 * Per-shop fulfilment. Each order is split into one part per shop (SellerOrder). Shops move their
 * own part along pending → processing → shipped → delivered; the order's overall status follows
 * the slowest part, so the customer sees "shipped" only when everything is on its way.
 */
class FulfilmentService
{
    public function __construct(protected OrderService $orders) {}

    /**
     * Split an order into shop parts and work out what each shop earns. Safe to run again.
     */
    public function createParts(Order $order): void
    {
        $order->loadMissing(['items.product' => fn ($q) => $q->withTrashed()]);

        foreach ($order->items->map(fn ($i) => $i->product?->seller_id)->unique() as $sellerId) {
            $this->refreshPart($order, $sellerId ?: null);
        }
    }

    /**
     * Create or update one shop's part from the order's items (runs whenever an item is added).
     * A part's commission rate is fixed when it is created; a part already paid out is never changed.
     */
    public function refreshPart(Order $order, ?int $sellerId): SellerOrder
    {
        $part = SellerOrder::firstOrNew(['order_id' => $order->id, 'seller_id' => $sellerId]);
        if ($part->payout_id) {
            return $part;
        }

        $items = $order->items()->with(['product' => fn ($q) => $q->withTrashed()])->get()
            ->filter(fn ($i) => ($i->product?->seller_id ?: null) === $sellerId);
        $subtotal = round($items->sum(fn ($i) => $i->price * $i->quantity), 2);

        if (! $part->exists) {
            $seller = $sellerId ? User::find($sellerId) : null;
            $part->status = $order->status === 'cancelled' ? 'cancelled' : 'pending';
            $part->commission_rate = $seller ? $seller->effectiveCommissionRate() : 0;
        }

        $commission = round($subtotal * (float) $part->commission_rate / 100, 2);
        $part->fill(['subtotal' => $subtotal, 'commission_amount' => $commission, 'seller_earnings' => $subtotal - $commission])->save();

        app(GstService::class)->capturePart($part, $order); // GST as it is when the order is placed

        return $part;
    }

    /**
     * Statuses a shop part can move to next (cancelling is done on the whole order).
     */
    public function nextStatuses(SellerOrder $part): array
    {
        if ($part->order->status === 'cancelled') {
            return [];
        }

        return array_values(array_diff(OrderService::TRANSITIONS[$part->status] ?? [], ['cancelled']));
    }

    /**
     * Move one shop's part forward, then bring the order's overall status up to date.
     *
     * @param  array<string, mixed>  $tracking  courier, tracking_number, tracking_url, vessel_or_flight, expected_delivery_date
     */
    public function advance(SellerOrder $part, string $status, ?string $trackingNote = null, array $tracking = []): bool
    {
        if (! in_array($status, $this->nextStatuses($part), true)) {
            return false;
        }

        $order = $part->order;
        $before = $order->status;

        DB::transaction(function () use ($part, $status, $trackingNote, $tracking) {
            $part->fill(['status' => $status]);
            if ($status === 'shipped') {
                $part->shipped_at = now();
                if (filled($trackingNote)) {
                    $part->tracking_note = $trackingNote;
                }
            }
            if ($status === 'out_for_delivery') {
                $part->out_for_delivery_at = now();
                $part->shipped_at ??= now();
            }
            if ($status === 'delivered') {
                $part->delivered_at = now();
                $part->shipped_at ??= now();
            }
            $this->fillTracking($part, $tracking);
            $part->save();
        });

        $this->syncOrder($order->fresh());

        // A shop sent its part but the rest of the order is still coming: tell the customer about this part.
        if ($status === 'shipped' && $order->fresh()->status === $before && $order->sellerOrders()->count() > 1) {
            $this->notifyPartShipped($part->fresh());
        }

        return true;
    }

    /**
     * The order's status follows its parts: delivered when all are delivered, shipped when all are on
     * their way, processing as soon as any shop starts. It only ever moves forward.
     */
    public function syncOrder(Order $order): void
    {
        if ($order->status === 'cancelled') {
            return;
        }

        $parts = $order->sellerOrders()->where('status', '!=', 'cancelled')->pluck('status');
        if ($parts->isEmpty()) {
            return;
        }

        $ranks = $parts->map(fn ($s) => max(SellerOrderStatus::rankFor($s), 0));
        $target = match (true) {
            $ranks->min() >= SellerOrderStatus::Delivered->rank() => 'delivered',
            $ranks->min() >= SellerOrderStatus::OutForDelivery->rank() => 'out_for_delivery',
            $ranks->min() >= SellerOrderStatus::Shipped->rank() => 'shipped',
            $ranks->max() >= SellerOrderStatus::Processing->rank() => 'processing',
            default => 'pending',
        };

        $this->orders->advanceTo($order, $target);
    }

    /**
     * Record (or correct) a part's delivery details without changing its status.
     *
     * @param  array<string, mixed>  $tracking
     */
    public function updateTracking(SellerOrder $part, array $tracking): void
    {
        $this->fillTracking($part, $tracking);
        $part->save();
    }

    /**
     * Only the known tracking fields, and only those actually given (so a form that leaves a
     * field out does not blank it).
     */
    protected function fillTracking(SellerOrder $part, array $tracking): void
    {
        foreach (SellerOrder::TRACKING_FIELDS as $field) {
            if (array_key_exists($field, $tracking)) {
                $part->{$field} = filled($tracking[$field]) ? $tracking[$field] : null;
            }
        }
    }

    /**
     * When an admin moves the whole order, bring every part along (and cancel them all on cancel).
     */
    public function cascadeFromOrder(Order $order, string $status): void
    {
        foreach ($order->sellerOrders()->where('status', '!=', 'cancelled')->get() as $part) {
            if ($status === 'cancelled') {
                $part->update(['status' => 'cancelled']);
                app(GstService::class)->partCancelled($part); // a paid sale reversed (GST report)

                continue;
            }

            if (max(SellerOrderStatus::rankFor($part->status), 0) < max(SellerOrderStatus::rankFor($status), 0)) {
                $part->update([
                    'status' => $status,
                    'shipped_at' => in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true) ? ($part->shipped_at ?? now()) : $part->shipped_at,
                    'out_for_delivery_at' => $status === 'out_for_delivery' ? ($part->out_for_delivery_at ?? now()) : $part->out_for_delivery_at,
                    'delivered_at' => $status === 'delivered' ? now() : $part->delivered_at,
                ]);
            }
        }
    }

    /**
     * Once any shop has sent its part, the order can no longer be cancelled as a whole.
     */
    public function anyPartSent(Order $order): bool
    {
        return $order->sellerOrders()->whereIn('status', ['shipped', 'out_for_delivery', 'delivered'])->exists();
    }

    public function partFor(Order $order, User $seller): ?SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $seller->id)->first();
    }

    protected function notifyPartShipped(SellerOrder $part): void
    {
        // The account holder, or the guest's email for a guest order
        app(OrderNotifier::class)->sendToCustomer($part->order, new SellerOrderShipped($part));
    }
}
