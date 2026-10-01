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
    public function orderPlaced(Order $order): void
    {
        $order->loadMissing(['user', 'items.product.seller']);
        $this->sendToCustomer($order, new OrderPlaced($order));

        $order->items
            ->filter(fn ($item) => $item->product?->seller)
            ->groupBy(fn ($item) => $item->product->seller_id)
            ->each(fn ($items) => $this->send($items->first()->product->seller, new NewSellerOrder($order, $items)));
    }

    public function statusChanged(Order $order): void
    {
        $this->sendToCustomer($order, new OrderStatusChanged($order));
    }

    public function paymentUpdated(Order $order): void
    {
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
        if (! $user || ! $user->email) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
