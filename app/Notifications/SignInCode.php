<?php

namespace App\Notifications;

use App\Models\OTP;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit code for signing in without a password, by SMS or email (whichever route it is sent
 * to). CodeLoginController sends it with notifyNow(): the customer is waiting on the page, so it
 * does not wait for the queue worker.
 */
class SignInCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OTP $otp)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('login_code', ['code' => $this->otp->code]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Your iruali sign-in code'))
            ->line(__('Your sign-in code is:'))
            ->line('# '.$this->otp->code)
            ->line(__('It expires in 10 minutes. Never share it with anyone: iruali will never ask you for it.'))
            ->line(__('If you did not try to sign in, you can ignore this email; your account is safe.'));
    }
}
