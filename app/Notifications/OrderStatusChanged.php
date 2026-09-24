<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
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

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject($subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($line)
            ->action(__('View your order'), route('orders.show', $this->order));
    }
}
