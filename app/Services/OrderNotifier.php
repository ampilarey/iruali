<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PaymentSlipSubmitted;
use App\Notifications\PaymentUpdated;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends order emails. A mail failure is logged and never breaks the order flow.
 */
class OrderNotifier
{
    public function orderPlaced(Order $order): void
    {
        $order->loadMissing(['user', 'items.product.seller']);
        $this->send($order->user, new OrderPlaced($order));

        $order->items
            ->filter(fn ($item) => $item->product?->seller)
            ->groupBy(fn ($item) => $item->product->seller_id)
            ->each(fn ($items) => $this->send($items->first()->product->seller, new NewSellerOrder($order, $items)));
    }

    public function statusChanged(Order $order): void
    {
        $this->send($order->user, new OrderStatusChanged($order));
    }

    public function paymentUpdated(Order $order): void
    {
        $this->send($order->user, new PaymentUpdated($order));
    }

    public function slipSubmitted(Order $order): void
    {
        $email = trim((string) Setting::get('contact_email'));
        if ($email === '') {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new PaymentSlipSubmitted($order));
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
