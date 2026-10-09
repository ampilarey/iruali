<?php

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Please confirm your subscription": sent when someone signs up for the newsletter in the footer.
 * The address only gets newsletters once the link in it is clicked (double opt-in), so nobody can
 * sign up someone else.
 */
class ConfirmNewsletterSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Confirm your iruali newsletter subscription'))
            ->greeting(__('Hello,'))
            ->line(__('Please confirm that :email should get the iruali newsletter: deals, new arrivals and news from shops across the islands.', ['email' => $this->subscriber->email]))
            ->action(__('Yes, subscribe me'), $this->subscriber->confirmUrl())
            ->line(__('If you did not ask for this, ignore this email and you will not hear from us.'))
            ->salutation(__('The iruali team'));
    }
}
