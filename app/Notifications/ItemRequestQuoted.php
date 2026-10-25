<?php

namespace App\Notifications;

use App\Models\ItemRequest;
use App\Models\User;
use App\Services\Sms\SmsText;
use App\Support\Money;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * An admin priced a customer's item request (ItemRequestService::quote()): by email and/or SMS, as the
 * customer chose for order updates.
 */
class ItemRequestQuoted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ItemRequest $itemRequest)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $notifiable->notificationChannels('order_updates') : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->itemRequest;
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('We can bring it: :item', ['item' => Str::limit($request->title, 60)]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Good news: we can bring :item for you.', ['item' => $request->title]))
            ->line(__('Price: :total for :qty, delivered to your island. It ships about :days days after you order.', ['total' => Money::format($request->total()), 'qty' => $request->quantity, 'days' => $request->ships_in_days]))
            ->line($request->deposit_percent
                ? __('Pay a booking fee of :amount now and the rest when it arrives.', ['amount' => Money::format($request->payNow())])
                : __('You pay the full price when you order.'));
        if ($request->quote_message) {
            $mail->line('> '.Str::limit($request->quote_message, 500));
        }

        return $mail->line(__('The price holds until :date.', ['date' => $request->valid_until?->translatedFormat('j M Y')]))
            ->action(__('See the price'), route('item-requests.show', $request));
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('item_request_quoted', [
            'item' => Str::limit($this->itemRequest->title, 40),
            'total' => Money::format($this->itemRequest->total()),
            'date' => $this->itemRequest->valid_until?->translatedFormat('j M'),
            'url' => route('item-requests.show', $this->itemRequest),
        ]);
    }
}
