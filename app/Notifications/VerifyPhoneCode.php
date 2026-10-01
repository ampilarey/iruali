<?php

namespace App\Notifications;

use App\Models\OTP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The 6-digit code texted to a customer to verify their mobile number.
 */
class VerifyPhoneCode extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public OTP $otp)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: your verification code is :code. It expires in 10 minutes.', ['code' => $this->otp->code]);
    }
}
