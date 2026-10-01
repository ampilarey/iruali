<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A shop has completed its onboarding checklist (sent to iruali's admins).
 */
class SellerOnboarded extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $seller) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shop = $this->seller->shopName();

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__(':shop has completed its shop checklist', ['shop' => $shop]))
            ->line(__(':shop (:email) has finished setting up: logo, banner, about text, phone, delivery options, bank account and a first product.', ['shop' => $shop, 'email' => $this->seller->email]))
            ->line($this->seller->seller_approved
                ? __('Its products can now be approved and will show on the storefront.')
                : __('The shop is still waiting for approval. Review it and approve it to let its products go live.'))
            ->action(__('Open sellers'), route('admin.sellers'));
    }
}
