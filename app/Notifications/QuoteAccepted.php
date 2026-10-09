<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The customer accepted the shop's quote (queued; shops can turn quote emails off).
 */
class QuoteAccepted extends Notification implements ShouldQueue
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

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__(':label accepted: :product', ['label' => $quote->label(), 'product' => $quote->displayName()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable instanceof User ? $notifiable->shopName() : '']))
            ->line(__(':business accepted your quote for :product: :line.', ['business' => $quote->business_name, 'product' => $quote->displayName(), 'line' => QuoteService::priceLine($quote)]))
            ->line(__('It is in their cart now. Stock is not held for it, so keep enough on hand; you get the usual new-order email when they check out.'))
            ->action(__('Open the request'), route('seller.quotes.show', $quote));
    }
}
