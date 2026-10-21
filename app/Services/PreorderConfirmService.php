<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PreorderAvailable;
use App\Services\Sms\PhoneNumber;
use App\Support\Audit;
use App\Support\PayLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Throwable;

/**
 * Pre-orders paid once the shop confirms it can get the item, for goods a shop orders from a
 * supplier for each customer and can't promise before it asks. The product form's "Pre-order"
 * fieldset sets it ("Nothing now: the full price once I confirm I can get it",
 * products.preorder_confirm_first; PreorderService::formRules()).
 *
 * - Checkout: while such a product is out of stock its cart line is a pre-order that waits for the
 *   shop. Those lines are ordered on their own (one shop, nothing else in the cart), by card or bank
 *   transfer, without the wallet. Nothing is paid when the order is placed: Order::awaitingPayment()
 *   is false until the shop confirms, and the unpaid-order jobs leave the order alone.
 * - The shop (Seller Centre → Pre-orders, or its order page) or an admin confirms it (confirm()): the
 *   customer is texted and emailed a link to pay the full amount within the product's days to pay
 *   (products.preorder_balance_days, PAY_DAYS by default; the order keeps them). Or the item can't be
 *   had (decline()): the order is cancelled and the customer told so (OrderStatusChanged).
 * - Paid: PaymentService::confirm() as for any order, and the shop is told to order the item. From
 *   then on it is a paid pre-order like any other (Stock arrived, then sent).
 * - Daily (preorders:balance-reminders): a reminder the day before payment is due. Still unpaid after
 *   its due date, the unpaid-order jobs cancel it like any unpaid order (exceptUntilDue()).
 * - Admin → Inbox counts the orders waiting for a shop to confirm (waitingCount()).
 */
class PreorderConfirmService
{
    /** Days the customer has to pay once the shop confirms, unless the product sets its own. */
    public const PAY_DAYS = 3;

    /** The payment link keeps working until the end of this many days after the due date. */
    public const LINK_DAYS_AFTER_DUE = 1;

    /** Session: the checkout page showed the order as paid once the shop confirms, with its days to pay. */
    public const SESSION_SHOWN = 'preorder_confirm_checkout';

    // ---- Checkout -------------------------------------------------------------------------------

    /**
     * The cart's pre-order lines that wait for the shop to confirm, or null when there are none: their
     * cart item ids, the days to pay (the most of its lines) and why the cart can't be ordered like
     * this (other items with them, or several shops), if so.
     *
     * @param  array<int, mixed>  $preorderLines  the cart's pre-order lines keyed by cart item id (PreorderService)
     * @return array{lines: list<int>, pay_days: int, problem: ?string}|null
     */
    public function forCart(Cart $cart, array $preorderLines): ?array
    {
        $items = $cart->items->filter(fn (CartItem $item) => isset($preorderLines[(int) $item->id]) && $item->product?->confirmsFirst())->values();
        if ($items->isEmpty()) {
            return null;
        }

        $problem = match (true) {
            $items->count() < $cart->items->count() => __('Pre-orders that the shop confirms first are ordered on their own. Please remove the other items from your cart and order them separately.'),
            $items->map(fn (CartItem $item) => (int) ($item->product->seller_id ?? 0))->unique()->count() > 1 => __('Pre-orders that the shop confirms first are ordered from one shop at a time. Please order from each shop separately.'),
            default => null,
        };

        return [
            'lines' => $items->map(fn (CartItem $item) => (int) $item->id)->all(),
            'pay_days' => (int) $items->max(fn (CartItem $item) => $item->product->preorderPayDays()),
            'problem' => $problem,
        ];
    }

    /**
     * For the checkout page: the order waits for the shop, remembered with its days to pay so that
     * placing the order can tell if that changed in between. Null for an order paid now.
     *
     * @param  array<int, mixed>  $preorderLines  PreorderService::checkoutLines()
     * @return array{lines: list<int>, pay_days: int, problem: ?string}|null
     */
    public function checkout(Cart $cart, array $preorderLines): ?array
    {
        $confirm = $this->forCart($cart, $preorderLines);
        Session::put(self::SESSION_SHOWN, $confirm === null ? null : ['pay_days' => $confirm['pay_days']]);

        return $confirm;
    }

    /**
     * Placing the order (OrderService, in its transaction): it waits for the shop to confirm, as the
     * checkout page showed, and is paid by card or bank transfer. Returns its days to pay, or null for
     * an order paid now.
     *
     * @param  array<int, mixed>  $preorderLines  PreorderService::reserveForCheckout()
     * @param  array<string, mixed>  $shippingData  payment_method from the form
     * @return array{pay_days: int}|null
     *
     * @throws RuntimeException a message for the customer
     */
    public function forOrder(Cart $cart, array $preorderLines, array $shippingData): ?array
    {
        $confirm = $this->forCart($cart, $preorderLines);

        // The checkout page said whether it waits for the shop: not the other way now, nor with fewer days to pay
        $shown = Session::get(self::SESSION_SHOWN);
        $changed = is_array($shown)
            ? $confirm === null || $confirm['pay_days'] < (int) ($shown['pay_days'] ?? 0)
            : $confirm !== null && Session::has(PreorderService::SESSION_SHOWN);
        if ($changed) {
            throw new RuntimeException(__('The shop has changed the terms of this pre-order. Please check your order and place it again.'));
        }
        if ($confirm === null) {
            return null;
        }

        if ($confirm['problem'] !== null) {
            throw new RuntimeException($confirm['problem']);
        }
        if (in_array($shippingData['payment_method'] ?? 'bml', [ManualPaymentService::COD, CreditService::METHOD], true)) {
            throw new RuntimeException(__('A pre-order that the shop confirms first is paid by card or bank transfer. Please choose one of those.'));
        }

        return ['pay_days' => $confirm['pay_days']];
    }

    // ---- The shop confirms, or can't get it -----------------------------------------------------

    /**
     * The shop (or an admin) can get the item: the customer is texted and emailed a link to pay the
     * full amount, due in the order's days to pay (as the product said when the order was placed).
     * Once per order; false when it isn't waiting for the shop.
     */
    public function confirm(Order $order, User $by): bool
    {
        $due = today()->addDays((int) $order->pay_days > 0 ? (int) $order->pay_days : self::PAY_DAYS);
        $confirmed = Order::whereKey($order->id)->where('confirm_first', true)
            ->whereNull('availability_confirmed_at')->whereNull('availability_declined_at')
            ->where('status', 'pending')->where('payment_status', '!=', 'paid')
            ->update(['availability_confirmed_at' => now(), 'pay_due_on' => $due->toDateString(), 'payment_link_sent_at' => now()]);
        if ($confirmed === 0) {
            return false;
        }

        $order->refresh();
        Audit::record('preorder.confirmed', $order, ['by' => $by->id, 'amount' => $order->cardAmount(), 'due' => $due->toDateString(), 'order_number' => $order->order_number]);
        $this->sendPayLink($order);

        return true;
    }

    /**
     * The shop (or an admin) can't get the item: the order is cancelled like any unpaid order (points
     * and a voucher go back) and the customer is told the shop can't get it (OrderStatusChanged).
     * False when it isn't waiting for the shop.
     */
    public function decline(Order $order, User $by): bool
    {
        // Marked first, once, so the cancellation message says why
        $marked = Order::whereKey($order->id)->where('confirm_first', true)
            ->whereNull('availability_confirmed_at')->whereNull('availability_declined_at')
            ->where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')
            ->update(['availability_declined_at' => now()]);
        if ($marked === 0) {
            return false;
        }

        $order->refresh();
        if (! app(OrderService::class)->updateOrderStatus($order, 'cancelled')) {
            $order->forceFill(['availability_declined_at' => null])->save();

            return false;
        }
        Audit::record('preorder.declined', $order, ['by' => $by->id, 'order_number' => $order->order_number]);

        return true;
    }

    // ---- Paying ---------------------------------------------------------------------------------

    /**
     * Text and email the customer the amount with a payment link that works until the day after it is
     * due (a reminder sends a new one).
     */
    public function sendPayLink(Order $order, bool $reminder = false): void
    {
        if (! $order->confirm_first || $order->availability_confirmed_at === null || ! $order->awaitingPayment() || $order->user?->isSmokeTest()) {
            return;
        }

        $routes = array_filter([
            'sms' => PhoneNumber::normalize($order->shipping_phone ?: $order->user?->phone),
            'mail' => $order->customerEmail(),
        ]);
        if ($routes === []) {
            return;
        }

        $until = ($order->pay_due_on ?? today())->copy()->addDays(self::LINK_DAYS_AFTER_DUE)->endOfDay();
        $url = PayLink::url($order, $until);

        try {
            $notifiable = Notification::route(array_key_first($routes), reset($routes));
            foreach (array_slice($routes, 1, null, true) as $channel => $to) {
                $notifiable->route($channel, $to);
            }
            $notifiable->notify(new PreorderAvailable($order, $url, $reminder));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Daily: remind customers whose payment is due today or tomorrow (once), with a fresh link. Not
     * on the day the shop confirmed (they were just sent one), nor once a transfer slip is in.
     */
    public function sendReminders(): int
    {
        $reminded = 0;
        Order::query()->where('confirm_first', true)->whereNotNull('availability_confirmed_at')->whereNull('pay_reminded_at')
            ->where('status', 'pending')->where('payment_status', '!=', 'paid')->whereNull('payment_slip_uploaded_at')
            ->where('availability_confirmed_at', '<', today())
            ->whereDate('pay_due_on', '>=', today()->toDateString())
            ->whereDate('pay_due_on', '<=', today()->addDay()->toDateString())
            ->eachById(function (Order $order) use (&$reminded) { // by id: the rows it marks drop out of the query
                if (Order::whereKey($order->id)->whereNull('pay_reminded_at')->update(['pay_reminded_at' => now()]) > 0) {
                    $this->sendPayLink($order->fresh(), true);
                    $reminded++;
                }
            });

        return $reminded;
    }

    /**
     * Leave out, for the unpaid-order cancel jobs, the pre-orders waiting for the shop or for a payment
     * not due yet. Once its due date has passed, an unpaid one is cancelled like any other order.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public static function exceptUntilDue(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('confirm_first', false)->orWhereDate('pay_due_on', '<', today()->toDateString()));
    }

    /** Orders waiting for their shop to confirm (the Admin → Inbox row). */
    public function waitingCount(): int
    {
        return Order::where('confirm_first', true)->whereNull('availability_confirmed_at')->whereNull('availability_declined_at')
            ->where('status', '!=', 'cancelled')->count();
    }

    /**
     * May this shop confirm or decline the order: it is the order's shop (these pre-orders come from
     * one shop).
     */
    public function isShopOf(Order $order, User $shop): bool
    {
        return $order->sellerOrders()->where('seller_id', $shop->id)->exists();
    }
}
