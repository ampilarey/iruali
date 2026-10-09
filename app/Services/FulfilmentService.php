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

        // What the shop funds (multi-buy offers, its discount code) comes off its goods: commission and
        // earnings are worked out on the discounted total (subtotal - shop_discount)
        $part->shop_discount = round($items->sum(fn ($i) => $i->shopDiscount()), 2);
        $net = round($subtotal - (float) $part->shop_discount, 2);

        $commission = round($net * (float) $part->commission_rate / 100, 2);
        $part->fill(['subtotal' => $subtotal, 'commission_amount' => $commission, 'seller_earnings' => round($net - $commission, 2)])->save();

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

        // A part the customer collects is only started here; then it is marked ready for pickup and
        // completed with the customer's code (markReadyForPickup(), confirmPickup())
        if ($part->isPickup()) {
            return $part->status === 'pending' ? ['processing'] : [];
        }

        // Pre-order items still waiting for their stock: the part can be prepared but not sent (PreorderService)
        if ($part->isAwaitingStock()) {
            return array_values(array_diff(OrderService::TRANSITIONS[$part->status] ?? [], ['cancelled', 'shipped']));
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

    // ---- Pick up from the shop ------------------------------------------------------------
    //
    // A pickup part goes pending → processing (the shop starts preparing) → ready for pickup
    // (pickup_ready_at; the customer gets a 6-digit code) → delivered when the shop types the
    // code the customer shows. Delivered has the same effects as a delivery: the order's status
    // follows, the shop's earnings become payable and the return window starts.

    /**
     * Record how each shop part reaches the customer, as worked out by DeliveryService::prepareOrder().
     *
     * @param  array<int, array{method: string, surcharge: float, pickup_address: ?string, pickup_island: ?string, pickup_hours: ?string}>  $choices  keyed by seller id (0 for none)
     */
    public function applyDeliveryMethods(Order $order, array $choices): void
    {
        foreach ($order->sellerOrders()->get() as $part) {
            $choice = $choices[(int) ($part->seller_id ?? 0)] ?? null;
            if ($choice === null) {
                continue;
            }

            $part->forceFill([
                'delivery_method' => $choice['method'],
                'delivery_surcharge' => $choice['surcharge'],
                'pickup_address' => $choice['pickup_address'],
                'pickup_island' => $choice['pickup_island'],
                'pickup_hours' => $choice['pickup_hours'],
            ])->save();
        }
    }

    /**
     * Can the shop mark this part ready for pickup now? "ok", or why not: not_pickup, cancelled,
     * collected, ready (already) or unpaid (no code goes out before the customer has paid).
     */
    public function pickupReadiness(SellerOrder $part): string
    {
        return match (true) {
            ! $part->isPickup() => 'not_pickup',
            $part->status === 'cancelled' || $part->order->status === 'cancelled' => 'cancelled',
            $part->status === 'delivered' => 'collected',
            $part->pickup_ready_at !== null => 'ready',
            $part->isAwaitingStock() => 'awaiting_stock', // pre-order items whose stock has not arrived yet
            $part->order->payment_status !== 'paid' => 'unpaid',
            default => 'ok',
        };
    }

    /**
     * The shop has the part at its counter: the customer is sent a new 6-digit pickup code by
     * email (and by text when SMS is set up). Being ready counts as sent on time for the shop's
     * late-shipment figures.
     */
    public function markReadyForPickup(SellerOrder $part): bool
    {
        if ($this->pickupReadiness($part) !== 'ok') {
            return false;
        }

        $marked = DB::transaction(function () use ($part) {
            // Checked again with the row locked, so a double click can't send two different codes
            if (SellerOrder::whereKey($part->id)->lockForUpdate()->value('pickup_ready_at') !== null) {
                return false;
            }

            // Send the shop's latest pickup details with the code
            $setting = \App\Models\SellerDeliverySetting::for($part->seller_id);
            if ($setting->offersPickup()) {
                $part->forceFill([
                    'pickup_address' => $setting->pickup_address,
                    'pickup_island' => $setting->pickupIslandForOrder(),
                    'pickup_hours' => $setting->pickup_hours,
                ]);
            }

            $part->forceFill([
                'status' => $part->status === 'pending' ? 'processing' : $part->status,
                'pickup_code' => $this->newPickupCode(),
                'pickup_code_attempts' => 0,
                'pickup_ready_at' => now(),
                'shipped_at' => $part->shipped_at ?? now(),
            ])->save();

            return true;
        });
        if (! $marked) {
            return false;
        }

        $this->syncOrder($part->order->fresh());
        $this->notifyPickupReady($part->fresh());

        return true;
    }

    /**
     * The shop types the code the customer shows. The right code completes the pickup; wrong
     * codes are counted, and after SellerOrder::MAX_PICKUP_ATTEMPTS only iruali can confirm it.
     *
     * @return string collected, wrong, locked or not_ready
     */
    public function confirmPickup(SellerOrder $part, string $code): string
    {
        if (! $part->isReadyForPickup() || $part->order->status === 'cancelled') {
            return 'not_ready';
        }
        if ($part->pickupLocked()) {
            return 'locked';
        }

        $code = (string) preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6 || ! hash_equals((string) $part->pickup_code, $code)) {
            SellerOrder::whereKey($part->id)->increment('pickup_code_attempts');

            return $part->refresh()->pickupLocked() ? 'locked' : 'wrong';
        }

        $this->completePickup($part);

        return 'collected';
    }

    /**
     * The customer has collected the part: it counts as delivered.
     */
    public function completePickup(SellerOrder $part): void
    {
        DB::transaction(function () use ($part) {
            $part->forceFill([
                'status' => 'delivered',
                'delivered_at' => now(),
                'pickup_collected_at' => now(),
                'shipped_at' => $part->shipped_at ?? now(),
            ])->save();
        });

        $this->syncOrder($part->order->fresh());
    }

    protected function newPickupCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Email the customer (the account, or the guest's address) and text the phone given at
     * checkout when an SMS gateway is set up: the code is what they collect with.
     */
    protected function notifyPickupReady(SellerOrder $part): void
    {
        $order = $part->order;
        app(OrderNotifier::class)->sendToCustomer($order, new \App\Notifications\PickupReady($part));

        $phone = $order->shipping_phone ?: $order->user?->phone;
        if (! $phone || ! app(\App\Services\Sms\SmsManager::class)->isLive() || $order->user?->isSmokeTest()) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Notification::route('sms', $phone)->notify(new \App\Notifications\PickupReady($part));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
