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
 * Tells iruali's admins (the contact email) a customer has uploaded a bank transfer slip to check.
 */
class PaymentSlipUploaded extends Notification implements ShouldQueue
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
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject('Transfer slip to check: order '.$this->order->order_number)
            ->line('The customer uploaded a bank transfer slip for order '.$this->order->order_number.' ('.Money::format($this->order->cardAmount()).').')
            ->line('Check that the money is in the account, then press "Confirm payment received" on the order. The shops send it after that.')
            ->action('Open the order', route('admin.orders.show', $this->order));
    }
}
