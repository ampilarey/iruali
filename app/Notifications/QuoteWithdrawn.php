<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The customer declined the shop's quote (or withdrew the request), or iruali closed it: the shop is
 * told (queued; shops can turn quote emails off).
 */
class QuoteWithdrawn extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('quotes') ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quote = $this->quote;
        $names = ['label' => $quote->label(), 'business' => $quote->business_name, 'product' => $quote->displayName()];

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->greeting(__('Hello :name,', ['name' => $notifiable instanceof User ? $notifiable->shopName() : '']));

        if ($quote->declined_by === 'admin') {
            $mail->subject(__(':label was closed by iruali', $names))
                ->line(__('iruali closed the request from :business for :product.', $names));
        } else {
            $mail->subject(__(':label was declined by the customer', $names))
                ->line($quote->quoted_at
                    ? __(':business declined your quote for :product.', $names)
                    : __(':business no longer needs a quote for :product.', $names));
        }

        if ($quote->decline_reason) {
            $mail->line(__('Reason: :reason', ['reason' => Str::limit($quote->decline_reason, 500)]));
        }

        return $mail->action(__('Open the request'), route('seller.quotes.show', $quote));
    }
}
