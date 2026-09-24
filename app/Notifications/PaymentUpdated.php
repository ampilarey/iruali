<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentUpdated extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        if ($this->order->payment_status === 'paid') {
            return $mail->subject(__('Payment received for order :number', ['number' => $number]))
                ->line(__('We have received your payment of :amount. Thank you.', ['amount' => Money::format($this->order->total_amount)]))
                ->action(__('View your order'), route('orders.show', $this->order));
        }

        return $mail->subject(__('Please check your payment for order :number', ['number' => $number]))
            ->line(__('We couldn\'t match your transfer slip to a payment. Please check the amount and upload the slip again.'))
            ->action(__('Upload your slip'), route('orders.show', $this->order));
    }
}
