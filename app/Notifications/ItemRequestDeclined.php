<?php

namespace App\Notifications;

use App\Models\ItemRequest;
use App\Models\User;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * An admin declined a customer's item request, with the reason (ItemRequestService::decline()).
 */
class ItemRequestDeclined extends Notification implements ShouldQueue
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
            ->subject(__('About your request: :item', ['item' => Str::limit($request->title, 60)]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Sorry, we can\'t bring :item for you.', ['item' => $request->title]));
        if ($request->declined_reason) {
            $mail->line('> '.Str::limit($request->declined_reason, 500));
        }

        return $mail->line(__('You are welcome to ask us for something else.'))
            ->action(__('See your request'), route('item-requests.show', $request));
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('item_request_declined', [
            'item' => Str::limit($this->itemRequest->title, 40),
            'url' => route('item-requests.show', $this->itemRequest),
        ]);
    }
}
