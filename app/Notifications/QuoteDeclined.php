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
 * The shop declined the customer's quote request, or iruali closed it: the customer is told, by
 * email and/or SMS as they chose for order updates.
 */
class QuoteDeclined extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $notifiable->notificationChannels('order_updates') : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quote = $this->quote;
        $names = ['shop' => $quote->shopName(), 'product' => $quote->displayName(), 'number' => $quote->id];
        $byIruali = $quote->declined_by === 'admin';

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject($byIruali ? __('Your quote request for :product was closed', $names) : __(':shop can\'t quote for :product', $names))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line($byIruali ? __('iruali closed your quote request #:number for :product.', $names) : __(':shop declined your quote request #:number for :product.', $names));

        if ($quote->decline_reason) {
            $mail->line(__('Reason: :reason', ['reason' => Str::limit($quote->decline_reason, 500)]));
        }

        return $mail->line(__('You can still buy it at the listed price, or ask again later.'))
            ->action(__('See the request'), route('quotes.show', $quote));
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: your quote request #:number for :product was declined. :url', [
            'number' => $this->quote->id,
            'product' => Str::limit($this->quote->displayName(), 40),
            'url' => route('quotes.show', $this->quote),
        ]);
    }
}
