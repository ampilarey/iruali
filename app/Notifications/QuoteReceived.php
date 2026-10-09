<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\QuoteService;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The shop sent (or changed) its quote: the customer gets it by email and/or SMS, as they chose for
 * order updates.
 */
class QuoteReceived extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public bool $changed = false)
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
        $names = ['shop' => $quote->shopName(), 'product' => $quote->displayName()];
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject($this->changed ? __(':shop changed its quote for :product', $names) : __(':shop sent you a quote for :product', $names))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Your price: :line', ['line' => QuoteService::priceLine($quote)]));

        if ($saving = $quote->savingPercent()) {
            $mail->line(__(':percent% under the shop\'s price of :price each.', ['percent' => $saving, 'price' => Money::format($quote->list_price)]));
        }
        $mail->line(__('The price holds until :date. Accept it to put it in your cart at this price, then check out as usual.', ['date' => $quote->valid_until?->translatedFormat('j M Y')]));
        if ($quote->shop_message) {
            $mail->line(__(':shop wrote:', $names))->line('> '.Str::limit($quote->shop_message, 500));
        }

        return $mail->action(__('See the quote'), route('quotes.show', $quote));
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: :shop sent you a quote for :product: :total, until :date. :url', [
            'shop' => $this->quote->shopName(),
            'product' => Str::limit($this->quote->displayName(), 40),
            'total' => Money::format($this->quote->lineTotal()),
            'date' => $this->quote->valid_until?->translatedFormat('j M'),
            'url' => route('quotes.show', $this->quote),
        ]);
    }
}
