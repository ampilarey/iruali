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
        Log::info("SMS to {$to} from {$sender}: {$message}");

        return SmsResult::logged();
    }
}
