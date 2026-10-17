<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We have your complaint": its reference, to whoever sent the complaint form.
 */
class ComplaintReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reference, string $locale = 'en')
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
            ->subject(__('We have your complaint (:reference)', ['reference' => $this->reference]))
            ->line(__('Thank you for telling us. Your complaint reference is :reference.', ['reference' => $this->reference]))
            ->line(__('Someone from the iruali team will look into it and reply to this email, usually within two working days.'));
    }
}
