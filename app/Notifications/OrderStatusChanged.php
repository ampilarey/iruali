<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return WebPushChannel::configured() ? ['mail', WebPushChannel::class] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;

        [$subject, $line] = match ($this->order->status) {
            'processing' => [__('Order :number is being prepared', ['number' => $number]), __('The shop is packing your order now.')],
            'shipped' => [__('Order :number is on its way', ['number' => $number]), __('Your order has left the shop and is on its way to your island.')],
            'delivered' => [__('Order :number has been delivered', ['number' => $number]), __('Your order has been delivered. We hope you enjoy it.')],
            'cancelled' => [__('Order :number has been cancelled', ['number' => $number]), __('Your order has been cancelled. Any loyalty points you used have been returned.')],
            default => [__('Update on order :number', ['number' => $number]), __('Your order status is now :status.', ['status' => $this->order->status])],
        };

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject($subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($line);

        // Tracking details each shop gave when it sent its part
        if ($this->order->status === 'shipped') {
            foreach ($this->order->sellerOrders()->with('seller')->whereNotNull('tracking_note')->get() as $part) {
                $mail->line(__('Tracking from :shop: :note', ['shop' => $part->shopName(), 'note' => $part->tracking_note]));
            }
        }

        return $mail->action(__('View your order'), route('orders.show', $this->order));
    }

    /**
     * Browser notification (same wording as the email subject).
     */
    public function toWebPush(object $notifiable): array
    {
        $number = $this->order->order_number;

        return [
            'title' => match ($this->order->status) {
                'processing' => __('Order :number is being prepared', ['number' => $number]),
                'shipped' => __('Order :number is on its way', ['number' => $number]),
                'delivered' => __('Order :number has been delivered', ['number' => $number]),
                'cancelled' => __('Order :number has been cancelled', ['number' => $number]),
                default => __('Update on order :number', ['number' => $number]),
            },
            'body' => __('Tap to see your order.'),
            'url' => route('orders.show', $this->order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$this->order->id,
        ];
    }
}
