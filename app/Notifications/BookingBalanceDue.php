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
 * A pre-order with a booking fee has arrived: the balance is due, with a payment link, by SMS and
 * email (App\Services\BookingFeeService). The reminder before the due date is the same message.
 */
class BookingBalanceDue extends Notification implements ShouldQueue
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
        return SmsTypes::render($this->reminder ? 'booking_balance_reminder' : 'booking_balance_due', [
            'number' => $this->order->order_number,
            'amount' => Money::format($this->order->balanceAmount()),
            'date' => $this->dueDate(),
            'url' => $this->url,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject($this->reminder
                ? __('Reminder: the balance for order :number is due :date', ['number' => $number, 'date' => $this->dueDate()])
                : __('Your pre-order has arrived: please pay the balance (order :number)', ['number' => $number]))
            ->greeting(__('Hello :name,', ['name' => $this->order->customerName() ?? __('there')]));

        if (! $this->reminder) {
            $mail->line(__('Good news: your pre-order has arrived.'));
        }

        return $mail->line(__('Please pay the balance of :amount by :date. The shop sends your order once it is paid.', ['amount' => Money::format($this->order->balanceAmount()), 'date' => $this->dueDate()]))
            ->line(__('You can pay by card or bank transfer. No sign-in needed.'))
            ->action(__('Pay the balance'), $this->url)
            ->line($this->order->deposit_refundable_at
                ? __('If you no longer want it, you can cancel from your order page for a full refund.')
                : __('If you no longer want it, you can cancel from your order page; the booking fee of :fee is not refunded.', ['fee' => Money::format($this->order->deposit_amount)]))
            ->line(__('If you have already paid, you can ignore this email.'));
    }

    protected function dueDate(): string
    {
        return ($this->order->balance_due_on ?? today())->translatedFormat('j M');
    }
}
