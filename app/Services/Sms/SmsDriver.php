<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /**
     * Send one message to a normalised (+960…) number. Never throws.
     */
    public function send(string $to, string $message, string $sender): SmsResult;
}
