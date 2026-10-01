<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        // Shipping and delivery news follow the delivery preference; everything else the order one
        $type = in_array($this->order->status, ['shipped', 'out_for_delivery', 'delivered'], true) ? 'delivery_updates' : 'order_updates';

        return $notifiable->notificationChannels($type);
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

    public function toSms(object $notifiable): string
    {
        $number = $this->order->order_number;

        return match ($this->order->status) {
            'processing' => __('iruali: order :number is being prepared by the shop.', ['number' => $number]),
            'shipped' => __('iruali: order :number is on its way.', ['number' => $number]),
            'delivered' => __('iruali: order :number has been delivered. Enjoy!', ['number' => $number]),
            'cancelled' => __('iruali: order :number has been cancelled.', ['number' => $number]),
            default => __('iruali: order :number is now :status.', ['number' => $number, 'status' => $this->order->status]),
        };
    }
}
