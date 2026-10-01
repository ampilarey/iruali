<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the customer their refund has been sent.
 */
class RefundRecorded extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Refund sent for order :number', ['number' => $order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $order->customerName()]))
            ->line(__('We have sent your refund of :amount.', ['amount' => Money::format($order->refund_amount)]))
            ->line(__('Reference: :ref', ['ref' => $order->refund_reference]))
            ->line(__('Refunds usually reach you within 5–7 business days, depending on your bank.'))
            ->action(__('View your order'), $order->customerUrl());
    }
}
