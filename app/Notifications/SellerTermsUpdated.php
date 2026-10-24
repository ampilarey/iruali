<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SellerTerms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An admin published a new version of the Seller Terms (Admin → Legal pages): the shop owner is asked
 * to read what changed and agree, which the Seller Centre requires before it opens again.
 */
class SellerTermsUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $version)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = SellerTerms::versionLabel($this->version);
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('iruali\'s Seller Terms have changed: please agree'))
            ->greeting(__('Hello :name,', ['name' => $notifiable instanceof User ? $notifiable->shopName() : __('there')]))
            ->line(__('We have published a new version of the Seller Terms (:date).', ['date' => $date]));

        foreach (SellerTerms::changes() as $change) {
            $mail->line('• '.$change);
        }

        return $mail->line(__('Please sign in and agree to the new terms. The Seller Centre opens again once you have; your products stay on sale meanwhile.'))
            ->action(__('Read and agree'), route('seller.terms.show'));
    }
}
