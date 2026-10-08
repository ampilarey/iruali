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
 * Tells iruali's admins a customer is owed money (sent to the contact email).
 */
class RefundDue extends Notification implements ShouldQueue
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
            ->subject('Refund due on order '.$order->order_number.': '.Money::format($order->refund_amount))
            ->line('Order '.$order->order_number.' ('.($order->user->name ?? 'customer').') needs a refund of '.Money::format($order->refund_amount).'.')
            ->line('Reason: '.$order->refund_reason)
            ->line('Card payments are refunded in the BML merchant portal. Then record the reference on the order.')
            ->action('Open the order', route('admin.orders.show', $order));
    }
}
