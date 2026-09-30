<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the customer their return was approved, rejected or refunded.
 */
class ReturnUpdated extends Notification
{
    public function __construct(public ReturnRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
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
