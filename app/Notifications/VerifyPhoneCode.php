<?php

namespace App\Notifications;

use App\Models\OTP;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
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

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('phone_code', ['code' => $this->otp->code]);
    }
}
