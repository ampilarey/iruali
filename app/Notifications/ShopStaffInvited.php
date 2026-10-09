<?php

namespace App\Notifications;

use App\Models\ShopStaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A shop owner invited this email address to their shop's staff (Seller Centre → Staff). The signed
 * link works for 7 days; with an account they sign in and accept, without one they make one.
 */
class ShopStaffInvited extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ShopStaffInvitation $invitation, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shop = $this->invitation->shop?->shopName() ?? 'iruali';
        $days = (int) config('shop_staff.invitation_days', 7);

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__(':shop invited you to help run their shop on iruali', ['shop' => $shop]))
            ->line(__(':name invited you to join the staff of :shop on iruali as a :role.', [
                'name' => $this->invitation->inviter->name ?? $shop,
                'shop' => $shop,
                'role' => mb_strtolower($this->invitation->roleLabel()),
            ]))
            ->line(__('You will sign in with your own account and see the Seller Centre pages your role allows. You can still shop on iruali as usual.'))
            ->action(__('See the invitation'), $this->url)
            ->line(trans_choice('The link works for :count day. If you did not expect this, you can ignore this email.|The link works for :count days. If you did not expect this, you can ignore this email.', $days, ['count' => $days]));
    }
}
