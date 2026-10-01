<?php

namespace App\Notifications;

use App\Models\OTP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The 6-digit code a customer types on the "verify your email" page.
 */
class VerifyEmailCode extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public OTP $otp)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Your iruali verification code'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your verification code is:'))
            ->line('# '.$this->otp->code)
            ->line(__('It expires in 10 minutes. If you did not create an iruali account, you can ignore this email.'))
            ->action(__('Verify my email'), route('verification.notice'));
    }
}
