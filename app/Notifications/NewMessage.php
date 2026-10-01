<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone wrote in an order thread: the customer gets it by email and/or SMS as they chose,
 * the shop by email.
 */
class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        $conversation = $this->message->conversation;
        if ($notifiable instanceof User && $notifiable->id === $conversation?->customer_id) {
            return $notifiable->notificationChannels('order_updates');
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $conversation = $this->message->conversation;
        $order = $conversation->order;
        $url = $this->urlFor($notifiable);

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('New message about order :number', ['number' => $order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':sender wrote about order :number:', ['sender' => $this->message->senderName(), 'number' => $order->order_number]))
            ->line('> '.Str::limit($this->message->body, 500))
            ->action(__('Reply'), $url)
            ->line(__('You get at most one of these emails every 10 minutes per conversation; the full thread is on the order page.'));
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: new message from :sender about order :number. Reply at :url', [
            'sender' => $this->message->senderName(),
            'number' => $this->message->conversation->order->order_number,
            'url' => $this->urlFor($notifiable),
        ]);
    }

    protected function urlFor(object $notifiable): string
    {
        $conversation = $this->message->conversation;
        $order = $conversation->order;

        if ($notifiable instanceof User && $conversation->seller_id && $notifiable->id === $conversation->seller_id) {
            return route('seller.orders.show', $order);
        }

        return route('orders.show', $order).'#conversation-'.$conversation->id;
    }
}
