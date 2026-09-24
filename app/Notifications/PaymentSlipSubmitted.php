<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the store's contact email when a customer uploads a transfer slip.
 */
class PaymentSlipSubmitted extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject('Transfer slip to check: '.$this->order->order_number)
            ->line('A customer uploaded a bank transfer slip for order '.$this->order->order_number.' ('.Money::format($this->order->total_amount).').')
            ->action('Check the payment', route('admin.orders.show', $this->order));
    }
}
