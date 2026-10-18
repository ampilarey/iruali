<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pay later: a reminder that an invoice is due soon, or that it is overdue (credit:reminders).
 */
class CreditInvoice extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'due'|'overdue'  $kind
     */
    public function __construct(public Order $order, public string $kind)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = ['number' => $this->order->order_number, 'amount' => \App\Support\Money::format((float) $this->order->total_amount), 'date' => $this->order->credit_due_on?->translatedFormat('j M Y')];
        $mail = (new MailMessage)->salutation(__('The iruali team'));

        $mail = $this->kind === 'overdue'
            ? $mail->subject(__('Invoice :number is overdue', $data))->line(__('The invoice for order :number (:amount) was due on :date. Please pay it as soon as you can; new pay-later orders wait until it is paid.', $data))
            : $mail->subject(__('Invoice :number is due on :date', $data))->line(__('A reminder that the invoice for order :number (:amount) is due on :date.', $data));

        return $mail->action(__('See your statement'), route('account.credit'));
    }
}
