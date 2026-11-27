<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the customer their return was approved, rejected or refunded.
 */
class ReturnUpdated extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $request)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        // By SMS too (or instead) for a customer who chose it, or who has only a mobile number
        return $notifiable instanceof \App\Models\User ? $notifiable->notificationChannels('order_updates') : ['mail'];
    }

    public function toSms(object $notifiable): \App\Services\Sms\SmsText
    {
        $request = $this->request;
        $number = $request->order->order_number;

        return match ($request->status) {
            'approved' => \App\Support\SmsTypes::render('return_approved', ['number' => $number, 'amount' => Money::format($request->refund_amount)]),
            'rejected' => \App\Support\SmsTypes::render('return_rejected', ['number' => $number]),
            'refunded' => \App\Support\SmsTypes::render('refund_sent', ['number' => $number, 'amount' => Money::format($request->refund_amount), 'ref' => (string) $request->refund_reference]),
            default => new \App\Services\Sms\SmsText('return_approved', ''), // nothing to say by SMS
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request;
        $number = $request->order->order_number;

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        match ($request->status) {
            'approved' => $mail->subject(__('Your return on order :number is approved', ['number' => $number]))
                ->line(__('We have approved your return. We will refund :amount.', ['amount' => Money::format($request->refund_amount)]))
                ->line(__('The refund goes back to the card you paid with. We will email you again when it is sent.')),
            'rejected' => $mail->subject(__('Your return on order :number', ['number' => $number]))
                ->line(__('Sorry, we could not accept your return request.')),
            'refunded' => $mail->subject(__('Refund sent for order :number', ['number' => $number]))
                ->line(__('We have sent your refund of :amount.', ['amount' => Money::format($request->refund_amount)]))
                ->line(__('Reference: :ref', ['ref' => $request->refund_reference]))
                ->line(__('Refunds usually reach you within 5–7 business days, depending on your bank.')),
            default => $mail->subject(__('Your return on order :number', ['number' => $number])),
        };

        if ($request->admin_note && $request->status !== 'refunded') {
            $mail->line(__('Note from iruali: :note', ['note' => $request->admin_note]));
        }

        return $mail->action(__('View your order'), route('orders.show', $request->order));
    }
}
