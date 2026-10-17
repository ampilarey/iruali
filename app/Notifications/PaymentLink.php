<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\Sms\SmsText;
use App\Support\Money;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A payment link for an unpaid order, by SMS and/or email (Admin → order → Send payment link).
 */
class PaymentLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $url)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('payment_link', [
            'number' => $this->order->order_number,
            'amount' => Money::format($this->order->cardAmount()),
            'url' => $this->url,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Pay for your iruali order :number', ['number' => $this->order->order_number]))
            ->line(__('Your order :number is waiting for payment of :amount.', ['number' => $this->order->order_number, 'amount' => Money::format($this->order->cardAmount())]))
            ->line(__('Use the button to pay by card or bank transfer. No sign-in needed. The link works for 48 hours.'))
            ->action(__('Pay now'), $this->url)
            ->line(__('If you have already paid, you can ignore this email.'));
    }
}
