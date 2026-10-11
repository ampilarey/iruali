<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You can open your shop on iruali now": sent once to each address on the waiting list when new
 * shop applications open again (App\Services\SellerWaitlistService).
 */
class SellerApplicationsReopened extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You can open your shop on iruali now'))
            ->greeting(__('Hello,'))
            ->line(__('You asked us to tell you when iruali takes new shops again. Applications are open now.'))
            ->action(__('Open your shop'), route('seller.apply'))
            ->line(__('Applying takes five minutes. We check every shop before its products go on sale.'))
            ->salutation(__('The iruali team'));
    }
}
