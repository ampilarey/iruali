<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You can order again": to those who asked while ordering was paused.
 */
class OrderingBack extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(string $locale = 'en')
    {
        $this->locale = $locale;
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('You can order on iruali again'))
            ->line(__('Ordering is back on iruali. Thank you for waiting.'))
            ->action(__('Start shopping'), route('home'));
    }
}
