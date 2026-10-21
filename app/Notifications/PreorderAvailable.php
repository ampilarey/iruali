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
 * The shop has confirmed it can get a pre-order paid once it confirms: please pay the full amount by
 * the due date, with a payment link, by SMS and email (App\Services\PreorderConfirmService). The
 * reminder before the due date is the same message.
 */
class PreorderAvailable extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $url, public bool $reminder = false)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render($this->reminder ? 'preorder_pay_reminder' : 'preorder_confirmed', [
            'number' => $this->order->order_number,
            'amount' => Money::format($this->order->cardAmount()),
            'date' => $this->dueDate(),
            'url' => $this->url,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject($this->reminder
                ? __('Reminder: please pay for order :number by :date', ['number' => $number, 'date' => $this->dueDate()])
                : __('The shop can get your pre-order: please pay (order :number)', ['number' => $number]))
            ->greeting(__('Hello :name,', ['name' => $this->order->customerName() ?? __('there')]));

        if (! $this->reminder) {
            $mail->line(__('Good news: the shop has confirmed it can get your pre-order.'));
        }

        return $mail->line(__('Please pay :amount by :date. The shop orders your item once it is paid; if it is not paid by then, the order is cancelled.', ['amount' => Money::format($this->order->cardAmount()), 'date' => $this->dueDate()]))
            ->line(__('You can pay by card or bank transfer. No sign-in needed.'))
            ->action(__('Pay now'), $this->url)
            ->line(__('If you no longer want it, you can cancel from your order page. You have not paid anything yet.'))
            ->line(__('If you have already paid, you can ignore this email.'));
    }

    protected function dueDate(): string
    {
        return ($this->order->pay_due_on ?? today())->translatedFormat('j M');
    }
}
