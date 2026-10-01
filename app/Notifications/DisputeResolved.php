<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * iruali has decided a dispute: the customer and the shop both hear the outcome.
 */
class DisputeResolved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Dispute $dispute) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && $notifiable->id === $this->dispute->customer_id) {
            return $notifiable->notificationChannels('order_updates');
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dispute = $this->dispute;
        $order = $dispute->order;
        $isShop = $notifiable instanceof User && $dispute->seller_id && $notifiable->id === $dispute->seller_id;

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Dispute on order :number: :outcome', ['number' => $order->order_number, 'outcome' => $dispute->statusLabel()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->outcomeLine($isShop));

        if ($dispute->resolution_note) {
            $mail->line(__('Note from iruali: :note', ['note' => $dispute->resolution_note]));
        }

        return $mail->action(__('View your order'), $isShop ? route('seller.orders.show', $order) : route('orders.show', $order));
    }

    public function toSms(object $notifiable): string
    {
        $dispute = $this->dispute;
        $number = $dispute->order->order_number;

        return match ($dispute->status) {
            'resolved_refund', 'resolved_partial' => __('iruali: your dispute on order :number is resolved. A refund of :amount is on its way.', ['number' => $number, 'amount' => Money::format($dispute->amount_resolved)]),
            default => __('iruali: your dispute on order :number was reviewed and not upheld. See the order page for the reason.', ['number' => $number]),
        };
    }

    protected function outcomeLine(bool $isShop): string
    {
        $dispute = $this->dispute;
        $amount = Money::format($dispute->amount_resolved);

        if ($isShop) {
            return match ($dispute->status) {
                'resolved_refund', 'resolved_partial' => __('iruali has decided the dispute on order :number: the customer is refunded :amount, and your share of it is taken from your next payout.', ['number' => $dispute->order->order_number, 'amount' => $amount]),
                default => __('iruali has decided the dispute on order :number: the claim was not upheld. Nothing changes for your payout.', ['number' => $dispute->order->order_number]),
            };
        }

        return match ($dispute->status) {
            'resolved_refund' => __('We have decided your dispute in your favour. We will refund :amount to the card you paid with and email you when it is sent.', ['amount' => $amount]),
            'resolved_partial' => __('We have decided your dispute: a partial refund of :amount goes back to the card you paid with. We will email you when it is sent.', ['amount' => $amount]),
            default => __('We have reviewed your dispute carefully and could not uphold it.'),
        };
    }
}
