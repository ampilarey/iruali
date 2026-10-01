<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the customer their refund has been sent.
 */
class RefundRecorded extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Refund sent for order :number', ['number' => $order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We have sent your refund of :amount.', ['amount' => Money::format($order->refund_amount)]))
            ->line(__('Reference: :ref', ['ref' => $order->refund_reference]))
            ->line(__('Refunds usually reach you within 5–7 business days, depending on your bank.'))
            ->action(__('View your order'), route('orders.show', $order));
    }
}
