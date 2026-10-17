<?php

namespace App\Notifications;

use App\Support\LoyaltyTiers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The customer moved up a loyalty tier (App\Support\LoyaltyTiers).
 */
class TierUpgraded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $tier)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tier = LoyaltyTiers::all()[$this->tier] ?? LoyaltyTiers::all()['reef'];

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('You are now :tier on iruali', ['tier' => $tier['name']]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Thank you for shopping with us. You have moved up to :tier.', ['tier' => $tier['name']]))
            ->line(__('From now on you earn :times times the points on every order.', ['times' => rtrim(rtrim(number_format($tier['multiplier'], 2), '0'), '.')]))
            ->action(__('See your rewards'), route('account.rewards'));
    }
}
