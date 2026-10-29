<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone wrote in a conversation (MessagingService): a customer gets it by email and/or SMS as they
 * chose for order updates, a shop by email.
 */
class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        // A visitor without an account: the email and/or mobile they gave
        if ($notifiable instanceof AnonymousNotifiable) {
            return array_keys($notifiable->routes);
        }
        if ($notifiable instanceof User && $this->roleOf($notifiable) === 'customer') {
            return $notifiable->notificationChannels('order_updates');
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $conversation = $this->message->conversation;
        $role = $this->roleOf($notifiable);
        $sender = $this->message->senderName($role);
        $mail = (new MailMessage)->salutation(__('The iruali team'))->greeting(__('Hello :name,', ['name' => $notifiable instanceof User ? $notifiable->name : ($conversation->guest_name ?? '')]));

        if ($conversation->kind === Conversation::ORDER && $conversation->order) {
            $number = $conversation->order->order_number;
            $mail->subject(__('New message about order :number', ['number' => $number]))
                ->line(__(':sender wrote about order :number:', ['sender' => $sender, 'number' => $number]));
        } else {
            $mail->subject(__('New message: :title', ['title' => Str::limit($conversation->title(), 60)]))
                ->line(__(':sender wrote:', ['sender' => $sender]));
        }

        return $mail->line('> '.Str::limit($this->message->body, 500))
            ->action(__('Reply'), $conversation->urlFor($role))
            ->line(__('You get at most one of these emails every 10 minutes per conversation; the full conversation is on the website.'));
    }

    public function toSms(object $notifiable): SmsText
    {
        $conversation = $this->message->conversation;
        $role = $this->roleOf($notifiable);
        if ($conversation->kind === Conversation::ORDER && $conversation->order) {
            return SmsTypes::render('new_message', [
                'sender' => $this->message->senderName($role),
                'number' => $conversation->order->order_number,
                'url' => $conversation->urlFor($role),
            ]);
        }

        return SmsTypes::render('chat_message', ['sender' => $this->message->senderName($role), 'url' => $conversation->urlFor($role)]);
    }

    protected function roleOf(object $notifiable): string
    {
        return $notifiable instanceof User ? ($this->message->conversation?->roleOf($notifiable) ?? 'customer') : 'customer';
    }
}
