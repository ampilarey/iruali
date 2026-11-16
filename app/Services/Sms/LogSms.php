<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes the message to the log instead of sending it (development, and before a gateway is set up).
 */
class LogSms implements SmsDriver
{
    public function send(string $to, string $message, string $sender): SmsResult
    {
        // On the live site the log never gets a working code (or other numbers in the text); locally it shows
        // the whole message, which is how a developer signs in without a gateway
        $text = app()->isProduction() ? (string) preg_replace('/\b\d{4,8}\b/', '••••••', $message) : $message;
        Log::info("SMS to {$to} from {$sender}: {$text}");

        return SmsResult::logged();
    }
}
