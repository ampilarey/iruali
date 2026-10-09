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
use Illuminate\Support\Str;

/**
 * A business asked the shop for a bulk quote (queued; shops can turn quote emails off under
 * Settings → Notifications).
 */
class QuoteRequested extends Notification implements ShouldQueue
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
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('New bulk quote request: :product', ['product' => $quote->displayName()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable instanceof User ? $notifiable->shopName() : '']))
            ->line(__(':business asks you for a price on :quantity × :product.', ['business' => $quote->business_name, 'quantity' => $quote->quantity, 'product' => $quote->displayName()]))
            ->line(__('Deliver to: :place', ['place' => $quote->deliveryPlace()]));

        if ($quote->needed_by) {
            $mail->line(__('Needed by: :date', ['date' => $quote->needed_by->translatedFormat('j M Y')]));
        }
        if ($quote->notes) {
            $mail->line('> '.Str::limit($quote->notes, 500));
        }

        return $mail->action(__('Reply in Seller Centre'), route('seller.quotes.show', $quote))
            ->line(__('Send a unit price for the quantity you can supply and how long it holds, ask in the messages, or decline. Please reply within :days days.', ['days' => QuoteService::WAITING_DAYS]));
    }
}
