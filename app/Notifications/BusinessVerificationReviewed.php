<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * iruali has checked a shop's business documents (Admin → Verifications): approved, so the shop shows
 * "Verified business", or rejected with the reason. Always sent: it is about the shop's account,
 * not one of the shop emails it can switch off.
 */
class BusinessVerificationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $approved, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shop = $notifiable instanceof User ? $notifiable->shopName() : '';
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->greeting(__('Hello :name,', ['name' => $shop]));

        if ($this->approved) {
            return $mail->subject(__('Your business is verified on iruali'))
                ->line(__('We have checked your business registration and ID card. :shop now shows "Verified business" next to its name on your products, your shop page and brand pages.', ['shop' => $shop]))
                ->line(__('If you send new documents later, the badge is hidden until we have checked them again.'))
                ->action(__('View your shop'), $notifiable instanceof User ? route('sellers.show', $notifiable) : route('home'));
        }

        return $mail->subject(__('We could not verify your business'))
            ->line(__('We have checked the business documents :shop sent, but could not verify them.', ['shop' => $shop]))
            ->line(__('Reason: :reason', ['reason' => (string) $this->reason]))
            ->line(__('Please send corrected documents and we will check them again. Your shop keeps selling as before.'))
            ->action(__('Send documents'), route('seller.settings.verification'));
    }
}
