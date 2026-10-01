<?php

namespace App\Notifications;

use App\Models\OTP;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit code texted to a customer to verify their mobile number.
 */
class VerifyPhoneCode extends Notification
{
    public function __construct(public OTP $otp) {}

    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: your verification code is :code. It expires in 10 minutes.', ['code' => $this->otp->code]);
    }
}
