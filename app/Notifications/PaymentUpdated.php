<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class PaymentUpdated extends Notification implements ShouldQueue
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
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        return $mail->subject(__('Payment received for order :number', ['number' => $number]))
            ->line(__('We have received your payment of :amount. Thank you.', ['amount' => Money::format($this->order->total_amount)]))
            ->action(__('View your order'), route('orders.show', $this->order));
    }
}
