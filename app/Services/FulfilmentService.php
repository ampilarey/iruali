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

        // Commission is on the goods only: a shop delivering its own part also gets its delivery charge
        $commission = round($net * (float) $part->commission_rate / 100, 2);
        $part->fill(['subtotal' => $subtotal, 'commission_amount' => $commission, 'seller_earnings' => round($net - $commission + $part->shopDeliveryCharge(), 2)])->save();

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
        // Nothing moves on before the money is in (or the order is paid on delivery): Order::mayBeFulfilled()
        if (! $part->order->mayBeFulfilled()) {
            return [];
        }

        // A pre-order paid once the shop confirms stays as it is until it is paid: moved on, it could no
        // longer be paid (Order::awaitingPayment(); PreorderConfirmService)
        if ($part->order->confirm_first && $part->order->payment_status !== 'paid') {
            return [];
        }

        // A part the customer collects is only started here; then it is marked ready for pickup and
        // completed with the customer's code (markReadyForPickup(), confirmPickup())
        if ($part->isPickup()) {
            return $part->status === 'pending' ? ['processing'] : [];
        }

        // Pre-order items still waiting for their stock, or a booking-fee pre-order whose balance is not
        // paid yet: the part can be prepared but not sent (PreorderService, BookingFeeService)
        if ($part->isAwaitingStock() || ($part->order->hasBookingFee() && $part->order->payment_status !== 'paid')) {
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

        $done = DB::transaction(function () use ($part, $status, $trackingNote, $tracking) {
            // The order locked (as a cancellation locks it: OrderService::updateOrderStatus()) and the
            // part read again: a cancel at the same moment wins, and the part is not moved on after it
            Order::whereKey($part->order_id)->lockForUpdate()->first(['id']);
            $part->refresh();
            if (! in_array($status, $this->nextStatuses($part), true)) {
                return false;
            }
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

            return true;
        });
        if (! $done) {
            return false;
        }

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
     * Cancel one shop's part of a paid order, when that shop can't supply it and another shop has
     * already sent its part (so the order can't be cancelled as a whole). Its items go back on sale,
     * the customer is refunded what they paid for them (and the shop's own delivery), less any
     * dispute refund already made on the part, and gets back the points spent on them; the shop's
     * dispute deduction is given back (it sold nothing) and its sale is reversed for GST. The order
     * carries on with its other shops. Returns why it can't be done, or null when it is done.
     */
    public function cancelPart(SellerOrder $part, User $admin): ?string
    {
        $done = DB::transaction(function () use ($part, $admin) {
            // The order locked, as every other change to it is (a cancel, a shop moving its part on)
            $order = Order::whereKey($part->order_id)->lockForUpdate()->first();
            $part->refresh();
            if (! $order || $order->status === 'cancelled' || ! in_array($part->status, ['pending', 'processing'], true)) {
                return 'This part can no longer be cancelled: it has been sent, or it is cancelled already.';
            }
            if ($order->sellerOrders()->where('status', '!=', 'cancelled')->count() < 2) {
                return 'This is the only shop left in the order: cancel the whole order instead.';
            }
            if ($order->payment_status !== 'paid') {
                return 'Only a paid order can have one shop cancelled. Cancel the whole order, or wait until it is paid.';
            }

            // What the customer paid for this part (its items, and the shop's own delivery), less what a
            // dispute on it already refunded (DisputeService::maxClaim())
            $refund = app(DisputeService::class)->maxClaim($part);
            $points = $this->partPoints($order, $part);

            $part->forceFill(['status' => 'cancelled', 'cancelled_alone_at' => now(), 'cancelled_refund' => $refund, 'points_returned' => $points])->save();
            foreach ($part->items()->with('product')->get() as $item) {
                if ($item->product && $item->stockedQuantity() > 0) {
                    app(OrderService::class)->restock($item->product, $item->product_variant_id, $item->stockedQuantity());
                }
                // A pre-order line still waiting for stock stops waiting: it no longer counts against the
                // product's pre-order limit, is not flagged late, and stock that arrives (or the units
                // just put back) is not handed to it. The order itself is still live, so the pre-order
                // queries (PreorderService), which look at the order's status, would keep counting it.
                if ($item->is_preorder && $item->preorder_allocated_at === null) {
                    $item->forceFill(['preorder_allocated_at' => now()])->save();
                }
            }
            // A dispute still open on the part is closed by this refund: decided again, it would refund twice
            \App\Models\Dispute::where('seller_order_id', $part->id)->whereIn('status', \App\Enums\DisputeStatus::openValues())->update([
                'status' => $refund > 0 ? 'resolved_refund' : 'resolved_rejected', 'amount_resolved' => 0, 'resolved_at' => now(), 'admin_id' => $admin->id,
                'resolution_note' => 'Closed: the shop\'s part was cancelled'.($refund > 0 ? ' and '.\App\Support\Money::format($refund).' refunded for it.' : '.'),
            ]);
            app(PreorderService::class)->afterOrderReversed($order);
            app(GstService::class)->partCancelled($part);
            app(OrderService::class)->reverseDisputeDeductions($order, $part->id);

            if ($refund > 0) {
                app(PaymentService::class)->flagRefund($order, $refund, 'Part cancelled: '.$part->shopName().' on order '.$order->order_number);
            }
            if ($points > 0 && $order->user) {
                app(PointsService::class)->record($order->user, $points, 'refund', $order, __('Redeemed points returned'));
            }

            return null;
        });
        if ($done !== null) {
            return $done;
        }

        $order = $part->order->fresh();
        \App\Support\Audit::record('order.part_cancelled', $order, ['shop' => $part->shopName(), 'refund' => (float) $part->cancelled_refund, 'order_number' => $order->order_number, 'by' => $admin->id]);
        $this->syncOrder($order);
        app(OrderService::class)->takeBackEarnedPoints($order->fresh());
        if ($part->seller_id) { // a part whose shop account is gone has nobody to tell
            app(OrderNotifier::class)->cancelledToShops($order, (int) $part->seller_id);
        }
        app(OrderNotifier::class)->sendToCustomer($order, new \App\Notifications\OrderPartCancelled($order, $part->fresh()));

        return null;
    }

    /**
     * Can this part be cancelled on its own (cancelPart())? For the order page's button.
     *
     * @param  \Illuminate\Support\Collection<int, SellerOrder>  $parts  the order's parts
     */
    public static function partCancellable(Order $order, SellerOrder $part, \Illuminate\Support\Collection $parts): bool
    {
        return in_array($part->status, ['pending', 'processing'], true) && $order->status !== 'cancelled'
            && $order->payment_status === 'paid' && $parts->where('status', '!=', 'cancelled')->count() > 1;
    }

    /**
     * The redeemed points spent on a part's items (PaidShares), less what returns and disputes on it
     * already gave back, and never more than the order still holds.
     */
    protected function partPoints(Order $order, SellerOrder $part): int
    {
        $redeemed = (int) $order->points_redeemed;
        if ($redeemed <= 0) {
            return 0;
        }
        $shares = \App\Support\PaidShares::shares($order)['points'];
        $total = array_sum($shares);
        if ($total <= 0) {
            return 0;
        }
        $partShare = 0;
        foreach ($part->items()->pluck('order_items.id') as $id) {
            $partShare += $shares[(int) $id] ?? 0;
        }
        $onPart = (int) \App\Models\ReturnRequest::where('seller_order_id', $part->id)->sum('points_returned')
            + (int) \App\Models\Dispute::where('seller_order_id', $part->id)->sum('points_returned');
        $onOrder = (int) \App\Models\ReturnRequest::where('order_id', $order->id)->sum('points_returned')
            + (int) \App\Models\Dispute::where('order_id', $order->id)->sum('points_returned')
            + (int) SellerOrder::where('order_id', $order->id)->sum('points_returned');

        return max(0, min((int) floor($redeemed * $partShare / $total) - $onPart, $redeemed - $onOrder));
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
        // Not a card order still waiting for its payment: the shop does not see it yet
        return $order->isShownToShops() ? $order->sellerOrders()->where('seller_id', $seller->id)->first() : null;
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
     * A shop delivering its part at its own fee gets that fee and its bulky-item charges with its
     * earnings (the part is refreshed so they, and its GST, include them).
     *
     * @param  array<int, array{method: string, surcharge: float, pickup_address: ?string, pickup_island: ?string, pickup_hours: ?string, fee?: float, to_shop?: bool}>  $choices  keyed by seller id (0 for none)
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
                'delivery_fee' => $choice['fee'] ?? 0,
                'delivery_fee_to_shop' => (bool) ($choice['to_shop'] ?? false),
            ])->save();

            if ($part->delivery_fee_to_shop) {
                $this->refreshPart($order, $part->seller_id);
            }
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
            $part->order->hasBookingFee() && $part->order->payment_status !== 'paid' => 'balance', // a booking-fee pre-order: the balance first
            // Cash on delivery is paid at the counter, so it may be made ready before
            $part->order->payment_status !== 'paid' && ! in_array($part->order->payment_method, [ManualPaymentService::COD, CreditService::METHOD], true) => 'unpaid',
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
