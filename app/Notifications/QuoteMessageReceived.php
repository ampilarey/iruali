<?php

namespace App\Notifications;

use App\Models\QuoteMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Someone wrote in a quote request's messages: the customer gets it by email and/or SMS as they
 * chose for order updates, the shop by email unless it turned quote emails off. At most one every
 * few minutes per request and person (QuoteService::postMessage).
 */
class QuoteMessageReceived extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteMessage $message)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }
        if ($this->isCustomer($notifiable)) {
            return $notifiable->notificationChannels('order_updates');
        }

        return $notifiable->wantsNotification('quotes') ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quote = $this->message->quoteRequest;

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('New message about :label', ['label' => $quote->label()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable instanceof User && ! $this->isCustomer($notifiable) ? $notifiable->shopName() : ($notifiable->name ?? '')]))
            ->line(__(':sender wrote about the quote for :product:', ['sender' => $this->message->senderName(), 'product' => $quote->displayName()]))
            ->line('> '.Str::limit($this->message->body, 500))
            ->action(__('Reply'), $this->urlFor($notifiable))
            ->line(__('You get at most one of these emails every :minutes minutes per request; the page shows all the messages.', ['minutes' => \App\Services\QuoteService::NOTIFY_EVERY_MINUTES]));
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: new message from :sender about :label. Reply at :url', [
            'sender' => $this->message->senderName(),
            'label' => $this->message->quoteRequest->label(),
            'url' => $this->urlFor($notifiable),
        ]);
    }

    protected function isCustomer(User $user): bool
    {
        return $user->id === $this->message->quoteRequest->customer_id;
    }

    protected function urlFor(object $notifiable): string
    {
        $quote = $this->message->quoteRequest;

        return $notifiable instanceof User && $this->isCustomer($notifiable)
            ? route('quotes.show', $quote).'#messages'
            : route('seller.quotes.show', $quote).'#messages';
    }
}
