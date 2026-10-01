<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * A month's warning that some loyalty points are about to expire.
 */
class PointsExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $points, public Carbon $expiresOn) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__(':points loyalty points expire soon', ['points' => $this->points]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':points of your loyalty points will expire on :date. Use them at checkout before then: each point is worth MVR 1.', ['points' => $this->points, 'date' => $this->expiresOn->translatedFormat('j F Y')]))
            ->action(__('Shop now'), route('shop'))
            ->line(__('See your points and referrals on your Rewards page: :url', ['url' => route('account.rewards')]));
    }
}
