<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PaymentUpdated;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends order emails. A mail failure is logged and never breaks the order flow.
 * A guest order (no account) is emailed at the address the guest gave at checkout.
 */
class OrderNotifier
{
    /**
     * Tell the customer, the shops and iruali's team about a new order, once only (orders.placed_notified_at).
     * A card order waits for its payment (OrderService::waitsForCardPayment()): PaymentService::confirm()
     * announces it then, so nobody gets "order placed" for a payment that never went through.
     * Returns whether this call announced it.
     */
    public function placedOnce(Order $order, bool $customer = true, bool $shops = true): bool
    {
        if (Order::whereKey($order->id)->whereNull('placed_notified_at')->update(['placed_notified_at' => now()]) === 0) {
            return false;
        }
        $order->forceFill(['placed_notified_at' => now()])->syncOriginal();
        $this->orderPlaced($order, $customer, $shops);

        return true;
    }

    public function orderPlaced(Order $order, bool $customer = true, bool $shops = true): void
    {
        \App\Support\Webhooks::order('order.created', $order);
        $order->loadMissing(['user', 'items.product.seller']);
        if ($customer) {
            $this->sendToCustomer($order, new OrderPlaced($order));
        }

        if ($shops) {
            $order->items
                ->whereNotNull('product.seller')
                ->groupBy(fn ($item) => $item->product->seller_id)
                ->each(fn ($items) => $this->send($items->first()->product->seller, new NewSellerOrder($order, $items)));
        }

        if (! $order->user?->isSmokeTest()) {
            $shops = $order->items->pluck('product.seller_id')->filter()->unique()->count();
            \App\Support\TeamAlerts::send('new_order', 'iruali: new order '.$order->order_number.', '.\App\Support\Money::format($order->total_amount)
                .', '.$shops.' '.($shops === 1 ? 'shop' : 'shops').', '.mb_strtolower(\App\Services\PaymentService::methodLabel($order->payment_method))
                .($order->shipping_city ? ', to '.$order->shipping_city : '').'.', route('admin.orders.show', $order));
        }
    }

    public function statusChanged(Order $order): void
    {
        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            \App\Support\Webhooks::order('order.'.$order->status, $order);
        }
        // A card order never paid was never announced (placedOnce()): no "cancelled" message about it either
        if ($order->status === 'cancelled' && $order->placed_notified_at === null && $order->payment_method === 'bml') {
            return;
        }
        $this->sendToCustomer($order, new OrderStatusChanged($order));
    }

    public function paymentUpdated(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            \App\Support\Webhooks::order('order.paid', $order);
        }
        $this->sendToCustomer($order, new PaymentUpdated($order));
    }

    /**
     * The order's customer: the account holder, or the guest's email when there is no account.
     */
    public function sendToCustomer(Order $order, BaseNotification $notification): void
    {
        if ($order->user) {
            $this->send($order->user, $notification);

            return;
        }

        if (! $order->guest_email) {
            return;
        }

        try {
            Notification::route('mail', $order->guest_email)->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function send(?User $user, BaseNotification $notification): void
    {
        // A customer who signed up with only a mobile number has no email: their messages come by
        // SMS (User::notificationChannels()). With neither there is nowhere to send.
        if (! $user || $user->isSmokeTest() || (blank($user->email) && ! $user->isPhoneVerified())) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
