<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerAdjustment;
use App\Models\User;
use App\Notifications\BookingBalanceDue;
use App\Services\Sms\PhoneNumber;
use App\Support\Audit;
use App\Support\Money;
use App\Support\PayLink;
use App\Support\TeamAlerts;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Throwable;

/**
 * Pre-orders with a booking fee, for goods the shop buys only once a customer orders them (for
 * example from a shop abroad). The product form's "Pre-order" fieldset takes the fee: a percentage
 * of the price or a fixed amount per unit (PreorderService::formRules()).
 *
 * - Checkout: while such a product is out of stock its cart line is a pre-order with a booking fee.
 *   Those lines are ordered on their own (one shop, nothing else in the cart) and paid by card or
 *   bank transfer (the wallet can pay part of the fee); the customer ticks that the fee is kept if
 *   they cancel. The order keeps the fee (orders.deposit_amount) and the customer pays only that.
 * - Booking fee paid: PaymentService::confirm() moves the order to "deposit_paid" (feePaid()) and
 *   tells the shop to order the item. The fee gets its own invoice with the GST in it
 *   (GstService::assignDepositInvoice()); points, the invoice for the rest and the shop's earnings
 *   wait for the balance, when the order is paid like any other.
 * - Stock arrived (PreorderService::receiveStock()): once nothing in the order waits for stock, the
 *   customer is sent the balance with a payment link by SMS and email, due in the product's days
 *   to pay (products.preorder_balance_days, BALANCE_DAYS by default; the order keeps them)
 *   (requestBalance()). The order can't be sent before it is paid (FulfilmentService, OrderService).
 * - Daily (preorders:balance-reminders): a reminder before the due date; past it, the team is told
 *   and Admin → Inbox lists it. An admin then cancels (keeping the fee) or moves the due date.
 * - Cancelling: when the customer cancels, the shop keeps the fee (keepOnCancel(): it is credited
 *   to the shop less commission) unless the fee became refundable: the shop moved the date later
 *   or the order is late (markRefundable()). An admin cancelling refunds everything, unless they
 *   choose to keep the fee (for example when the balance was never paid).
 */
class BookingFeeService
{
    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    public const TYPES = [self::PERCENT, self::FIXED];

    /** The largest booking fee as a percentage of the price. */
    public const MAX_PERCENT = 90;

    /** Days the customer has to pay the balance once the stock has arrived, unless the product sets its own. */
    public const BALANCE_DAYS = 7;

    /** The balance's payment link keeps working this many days after the due date. */
    public const LINK_DAYS_AFTER_DUE = 7;

    /** The reminder goes this many days before the balance is due. */
    public const REMIND_DAYS_BEFORE = 2;

    /** Session: the booking fee the checkout page showed (placing the order checks it is still the same). */
    public const SESSION_SHOWN = 'booking_fee_checkout';

    // ---- Products -------------------------------------------------------------------------------

    /**
     * The booking fee for one unit at this price: a percentage of it, or the fixed amount, never more
     * than the price. 0 when the product takes no booking fee.
     */
    public function unitFee(Product $product, float $unitPrice): float
    {
        if (! $product->hasBookingFee() || $unitPrice <= 0) {
            return 0.0;
        }

        $value = (float) $product->preorder_deposit_value;
        $fee = $product->preorder_deposit_type === self::PERCENT ? $unitPrice * min($value, self::MAX_PERCENT) / 100 : $value;

        return round(min($fee, $unitPrice), 2);
    }

    /**
     * "MVR 1,500.00 per item (30% of the price)" for the product page and the cart.
     */
    public function describe(Product $product, ?float $unitPrice = null): string
    {
        $unitPrice ??= (float) $product->final_price;
        $amount = Money::format($this->unitFee($product, $unitPrice));

        return $product->preorder_deposit_type === self::PERCENT
            ? __(':amount per item (:percent% of the price)', ['amount' => $amount, 'percent' => rtrim(rtrim(number_format(min((float) $product->preorder_deposit_value, self::MAX_PERCENT), 2, '.', ''), '0'), '.')])
            : __(':amount per item', ['amount' => $amount]);
    }

    // ---- Checkout -------------------------------------------------------------------------------

    /**
     * The cart's booking fee, or null when none of its lines takes one: per line (pre-order lines of
     * products with a booking fee, keyed by cart item id) and in total, and why the cart can't be
     * ordered like this (other items with them, or several shops), if so.
     *
     * With them the days the order would be placed with: late after the fewest days of its lines
     * (PreorderService::markOrder()) and the balance due after the most.
     *
     * @param  array<int, mixed>  $preorderLines  the cart's pre-order lines keyed by cart item id (PreorderService)
     * @return array{fee: float, lines: array<int, float>, problem: ?string, late_days: int, balance_days: int, gst: bool}|null
     */
    public function forCart(Cart $cart, array $preorderLines): ?array
    {
        $lines = [];
        foreach ($cart->items as $item) {
            if (isset($preorderLines[(int) $item->id]) && $item->product?->hasBookingFee()) {
                $lines[(int) $item->id] = round($this->unitFee($item->product, (float) $item->unit_price) * (int) $item->quantity, 2);
            }
        }
        if ($lines === []) {
            return null;
        }

        $feeItems = $cart->items->filter(fn (CartItem $item) => isset($lines[(int) $item->id]));
        $shops = $feeItems->map(fn (CartItem $item) => (int) ($item->product->seller_id ?? 0))->unique();
        $problem = match (true) {
            count($lines) < $cart->items->count() => __('Pre-orders with a booking fee are ordered on their own. Please remove the other items from your cart and order them separately.'),
            $shops->count() > 1 => __('Pre-orders with a booking fee are ordered from one shop at a time. Please order from each shop separately.'),
            default => null,
        };

        return [
            'fee' => round(array_sum($lines), 2), 'lines' => $lines, 'problem' => $problem,
            'late_days' => (int) $feeItems->min(fn (CartItem $item) => $item->product->preorderLateDays()),
            'balance_days' => (int) $feeItems->max(fn (CartItem $item) => $item->product->preorderBalanceDays()),
            // The fee has GST in it when the shop is GST-registered (its own tax invoice: GstService)
            'gst' => $shops->count() === 1 && app(GstService::class)->sellerRegistered($shops->first() ?: null),
        ];
    }

    /**
     * The booking fee the order asks for: null when it would be the whole goods total or more (then
     * the customer simply pays in full, as for any pre-order).
     */
    public function depositFor(float $fee, float $goodsTotal): ?float
    {
        return $fee > 0 && round($fee, 2) < round($goodsTotal, 2) ? round($fee, 2) : null;
    }

    /**
     * For the checkout page: the booking fee to pay now and the rest, and its days, remembered so
     * that placing the order can tell if they changed in between. Null when the order is paid in full.
     *
     * @param  array<int, mixed>  $preorderLines  PreorderService::checkoutLines()
     * @return array{fee: float, lines: array<int, float>, problem: ?string, late_days: int, balance_days: int, gst: bool}|null
     */
    public function checkout(Cart $cart, array $preorderLines, float $goodsTotal): ?array
    {
        $booking = $this->forCart($cart, $preorderLines);
        $deposit = $booking ? $this->depositFor($booking['fee'], $goodsTotal) : null;
        Session::put(self::SESSION_SHOWN, $deposit === null ? null : ['fee' => $deposit, 'late_days' => $booking['late_days'], 'balance_days' => $booking['balance_days']]);

        return $deposit === null ? null : ['fee' => $deposit] + $booking;
    }

    /**
     * Placing the order (OrderService, in its transaction): the booking fee for the pre-order lines
     * reserveForCheckout() picked and the days to pay the balance, as the checkout page showed them.
     * Null for an order paid in full.
     *
     * @param  array<int, mixed>  $preorderLines  PreorderService::reserveForCheckout()
     * @param  array<string, mixed>  $shippingData  payment_method and agree_booking_fee from the form
     * @return array{deposit: float, balance_days: int}|null
     *
     * @throws RuntimeException a message for the customer
     */
    public function forOrder(Cart $cart, array $preorderLines, array $shippingData, float $goodsTotal): ?array
    {
        $booking = $this->forCart($cart, $preorderLines);
        $deposit = $booking ? $this->depositFor($booking['fee'], $goodsTotal) : null;

        $shown = Session::get(self::SESSION_SHOWN);
        if ($shown !== null) {
            $shown = is_array($shown) ? $shown : ['fee' => (float) $shown]; // a checkout page from before the days were shown
            if ($deposit === null || round((float) ($shown['fee'] ?? 0), 2) !== round($deposit, 2)) {
                throw new RuntimeException($deposit === null
                    ? __('Your order no longer has a booking fee. Please check your order and place it again.')
                    : __('The booking fee for your order is now :amount. Please check your order and place it again.', ['amount' => Money::format($deposit)]));
            }
            // Fewer days to pay, or more days late before a full refund, than the page said
            if ($booking !== null && ($booking['late_days'] > (int) ($shown['late_days'] ?? $booking['late_days']) || $booking['balance_days'] < (int) ($shown['balance_days'] ?? $booking['balance_days']))) {
                throw new RuntimeException(__('The shop has changed the terms of this pre-order. Please check your order and place it again.'));
            }
        } elseif ($deposit !== null && Session::has(PreorderService::SESSION_SHOWN)) {
            // The checkout page showed no booking fee (the item was in stock then)
            throw new RuntimeException(__('The booking fee for your order is now :amount. Please check your order and place it again.', ['amount' => Money::format($deposit)]));
        }
        if ($booking === null || $deposit === null) {
            return null;
        }

        if ($booking['problem'] !== null) {
            throw new RuntimeException($booking['problem']);
        }
        if (in_array($shippingData['payment_method'] ?? 'bml', [ManualPaymentService::COD, CreditService::METHOD], true)) {
            throw new RuntimeException(__('A booking fee is paid by card or bank transfer. Please choose one of those.'));
        }
        if (empty($shippingData['agree_booking_fee'])) {
            throw new RuntimeException(__('Please tick that you agree to the booking fee terms.'));
        }

        return ['deposit' => $deposit, 'balance_days' => $booking['balance_days']];
    }

    // ---- Paying ---------------------------------------------------------------------------------

    /**
     * The part of a booking-fee order the next payment is for: deposit (the booking fee) or balance.
     * Null for an order paid in one go.
     */
    public static function nextPart(Order $order): ?string
    {
        if (! $order->hasBookingFee()) {
            return null;
        }

        return $order->payment_status === 'deposit_paid' ? 'balance' : 'deposit';
    }

    /**
     * The booking fee is paid (PaymentService::confirm()): once only, even when two confirmations
     * race. A transfer slip is kept aside so that the balance gets its own, and the customer and the
     * shop are told. False when the fee was already paid.
     */
    public function feePaid(Order $order): bool
    {
        $paidAt = now();
        if (Order::whereKey($order->id)->whereNotIn('payment_status', ['deposit_paid', 'paid'])->update(['payment_status' => 'deposit_paid', 'deposit_paid_at' => $paidAt]) === 0) {
            return false;
        }
        $order->forceFill(['payment_status' => 'deposit_paid', 'deposit_paid_at' => $paidAt])->syncOriginal();
        if ($order->payment_slip_path) {
            $order->forceFill(['deposit_slip_path' => $order->payment_slip_path, 'payment_slip_path' => null, 'payment_slip_uploaded_at' => null])->save();
        }

        Audit::record('booking_fee.paid', $order, ['amount' => (float) $order->deposit_amount, 'method' => $order->payment_method, 'order_number' => $order->order_number]);
        app(GstService::class)->depositPaid($order); // the shop's invoice for the fee, with its GST
        app(OrderNotifier::class)->sendToCustomer($order, new \App\Notifications\PaymentUpdated($order, 'deposit'));
        $this->tellShops($order, 'deposit');

        return true;
    }

    /**
     * Tell the order's shops about its payment: the booking fee (order the item now) or the balance
     * (send it now).
     */
    public function tellShops(Order $order, string $part): void
    {
        $order->loadMissing('sellerOrders.seller');
        foreach ($order->sellerOrders as $shopPart) {
            if ($shopPart->seller && $shopPart->status !== 'cancelled') {
                try {
                    $shopPart->seller->notify(new \App\Notifications\ShopOrderPaid($order, $part));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }

    // ---- The balance ----------------------------------------------------------------------------

    /**
     * Nothing in the order waits for stock any more (PreorderService::receiveStock()): ask the
     * customer for the balance by SMS and email, with a payment link, due in the order's days to pay
     * (as the product said when the order was placed). Once per order; false when it isn't waiting
     * for the balance.
     */
    public function requestBalance(Order $order): bool
    {
        $due = today()->addDays((int) $order->balance_days > 0 ? (int) $order->balance_days : self::BALANCE_DAYS);
        $asked = Order::whereKey($order->id)->where('payment_status', 'deposit_paid')->whereNotNull('deposit_amount')
            ->whereNull('balance_requested_at')->whereIn('status', ['pending', 'processing'])
            ->update(['balance_requested_at' => now(), 'balance_due_on' => $due->toDateString(), 'payment_link_sent_at' => now()]);
        if ($asked === 0) {
            return false;
        }

        $order->refresh();
        Audit::record('booking_fee.balance_requested', $order, ['amount' => $order->balanceAmount(), 'due' => $due->toDateString(), 'order_number' => $order->order_number]);
        $this->sendBalanceLink($order);

        return true;
    }

    /**
     * Text and email the customer the balance with a payment link that works until a week after it
     * is due (a reminder sends a new one).
     */
    public function sendBalanceLink(Order $order, bool $reminder = false): void
    {
        if (! $order->balanceDue() || $order->user?->isSmokeTest()) {
            return;
        }

        $routes = array_filter([
            'sms' => PhoneNumber::normalize($order->shipping_phone ?: $order->user?->phone),
            'mail' => $order->customerEmail(),
        ]);
        if ($routes === []) {
            return;
        }

        $until = ($order->balance_due_on ?? today())->copy()->addDays(self::LINK_DAYS_AFTER_DUE)->endOfDay();
        $url = PayLink::url($order, $until);

        try {
            $notifiable = Notification::route(array_key_first($routes), reset($routes));
            foreach (array_slice($routes, 1, null, true) as $channel => $to) {
                $notifiable->route($channel, $to);
            }
            $notifiable->notify(new BookingBalanceDue($order, $url, $reminder));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Daily: remind customers whose balance is due within REMIND_DAYS_BEFORE days (once), and flag
     * balances past their due date (once) to the team. Moving the due date starts both again.
     *
     * @return array{reminded: int, overdue: int}
     */
    public function sendReminders(): array
    {
        $open = fn () => Order::query()->where('payment_status', 'deposit_paid')->whereNotNull('deposit_amount')
            ->whereNotNull('balance_requested_at')->whereIn('status', ['pending', 'processing']);

        $reminded = 0;
        $open()->whereNull('balance_reminded_at')
            ->whereDate('balance_due_on', '>=', today()->toDateString())
            ->whereDate('balance_due_on', '<=', today()->addDays(self::REMIND_DAYS_BEFORE)->toDateString())
            ->eachById(function (Order $order) use (&$reminded) { // by id: the rows it marks drop out of the query
                if (Order::whereKey($order->id)->whereNull('balance_reminded_at')->update(['balance_reminded_at' => now()]) > 0) {
                    $this->sendBalanceLink($order->fresh(), true);
                    $reminded++;
                }
            });

        $overdue = 0;
        $open()->whereNull('balance_overdue_at')->whereDate('balance_due_on', '<', today()->toDateString())
            ->eachById(function (Order $order) use (&$overdue) {
                if (Order::whereKey($order->id)->whereNull('balance_overdue_at')->update(['balance_overdue_at' => now()]) > 0) {
                    Audit::record('booking_fee.balance_overdue', $order, ['amount' => $order->balanceAmount(), 'due' => $order->balance_due_on?->toDateString(), 'order_number' => $order->order_number]);
                    TeamAlerts::send('booking_balance_overdue', 'iruali: the balance of '.Money::format($order->balanceAmount()).' on pre-order '.$order->order_number
                        .' was due '.$order->balance_due_on?->format('j M').'. Cancel the order (the booking fee is kept) or move the due date.', route('admin.orders.show', $order));
                    $overdue++;
                }
            });

        return ['reminded' => $reminded, 'overdue' => $overdue];
    }

    /** Orders whose balance is past its due date (the Admin → Inbox row). */
    public function overdueCount(): int
    {
        return Order::where('payment_status', 'deposit_paid')->whereNotNull('deposit_amount')->whereNotNull('balance_requested_at')
            ->whereIn('status', ['pending', 'processing'])->whereDate('balance_due_on', '<', today()->toDateString())->count();
    }

    /**
     * An admin gives the customer until another day to pay the balance: the reminder and the
     * overdue alert start again, and the customer is sent the new date with a fresh link.
     */
    public function moveDueDate(Order $order, CarbonInterface $date, User $by): bool
    {
        if (! $order->balanceDue()) {
            return false;
        }

        $from = $order->balance_due_on?->toDateString();
        $order->forceFill(['balance_due_on' => $date->toDateString(), 'balance_reminded_at' => null, 'balance_overdue_at' => null, 'payment_link_sent_at' => now()])->save();
        Audit::record('booking_fee.due_moved', $order, ['from' => $from, 'to' => $date->toDateString(), 'by' => $by->id, 'order_number' => $order->order_number]);
        $this->sendBalanceLink($order);

        return true;
    }

    // ---- Cancelling -----------------------------------------------------------------------------

    /**
     * From now on the booking fee is refunded if the order is cancelled: the shop moved the date
     * later, or the order is late (PreorderService). Returns how many orders changed.
     *
     * @param  iterable<int>  $orderIds
     */
    public function markRefundable(iterable $orderIds, string $why): int
    {
        $ids = collect($orderIds)->map(fn ($id) => (int) $id)->unique()->values();
        $orders = $ids->isEmpty() ? collect() : Order::whereIn('id', $ids)->whereNotNull('deposit_amount')->whereNull('deposit_refundable_at')->where('status', '!=', 'cancelled')->get();

        foreach ($orders as $order) {
            if (Order::whereKey($order->id)->whereNull('deposit_refundable_at')->update(['deposit_refundable_at' => now()]) > 0) {
                Audit::record('booking_fee.refundable', $order, ['why' => $why, 'order_number' => $order->order_number]);
            }
        }

        return $orders->count();
    }

    /**
     * Can the booking fee be kept if the order is cancelled now? It has been paid and has not become
     * refundable (date moved later, or late).
     */
    public function keepable(Order $order): bool
    {
        return $order->status !== 'cancelled' && $order->bookingFeePaid() && $order->deposit_refundable_at === null;
    }

    /**
     * Cancelling with the booking fee kept (OrderService::updateOrderStatus(), in its transaction):
     * records what is kept and credits it to the shop, less iruali's commission at the order's rate.
     * Returns the amount kept (0 when there is nothing to keep).
     */
    public function keepOnCancel(Order $order): float
    {
        if (! $this->keepable($order)) {
            return 0.0;
        }

        $fee = round((float) $order->deposit_amount, 2);
        $order->forceFill(['deposit_kept_amount' => $fee])->save();

        // Booking-fee orders come from one shop (forCart()). Its fee's invoice stands (GstService), and
        // the shop is paid the fee less commission; iruali's own sales have no shop to pay
        $part = $order->sellerOrders()->orderBy('id')->first();
        $commission = $part?->seller_id ? round($fee * (float) $part->commission_rate / 100, 2) : 0.0;
        $share = $part?->seller_id ? round($fee - $commission, 2) : 0.0;
        if ($part) {
            app(GstService::class)->depositKept($part, $commission);
        }
        if ($part?->seller_id && $share > 0) {
            SellerAdjustment::create(['seller_id' => $part->seller_id, 'seller_order_id' => $part->id, 'amount' => $share, 'reason' => 'Booking fee kept: order '.$order->order_number.' cancelled']);
        }
        Audit::record('booking_fee.kept', $order, ['amount' => $fee, 'shop_share' => $share, 'order_number' => $order->order_number]);

        return $fee;
    }

    /**
     * What goes back to the customer when the order is cancelled with $kept of the booking fee kept:
     * the card (or bank transfer) part and the wallet part. The kept fee comes out of the wallet's
     * share first.
     *
     * @return array{kept: float, card: float, wallet: float}
     */
    public static function refundSplit(Order $order, float $kept): array
    {
        $kept = round(max(0, $kept), 2);
        $wallet = round((float) $order->wallet_amount, 2);
        $fromWallet = min($wallet, $kept);

        return [
            'kept' => $kept,
            'card' => round(max(0, $order->cardPaid() - ($kept - $fromWallet)), 2),
            'wallet' => round(max(0, $wallet - $fromWallet), 2),
        ];
    }

    /**
     * What cancelling now would return, for the cancel page: the fee kept when the customer cancels
     * (keepable()), the rest refunded.
     *
     * @return array{kept: float, card: float, wallet: float}
     */
    public function customerCancelPreview(Order $order): array
    {
        return self::refundSplit($order, $this->keepable($order) ? (float) $order->deposit_amount : 0.0);
    }
}
