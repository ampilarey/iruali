<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentUpdated extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $notifiable->notificationChannels('order_updates') : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        return $mail->subject(__('Payment received for order :number', ['number' => $number]))
            ->line(__('We have received your payment of :amount. Thank you.', ['amount' => Money::format($this->order->total_amount)]))
            ->action(__('View your order'), route('orders.show', $this->order));
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: payment of :amount received for order :number. Thank you.', [
            'amount' => Money::format($this->order->total_amount),
            'number' => $this->order->order_number,
        ]);
    }
}
