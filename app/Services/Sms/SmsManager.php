<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use Throwable;

/**
 * Sends text messages through the configured driver and keeps a record of every one.
 */
class SmsManager
{
    protected ?SmsDriver $driver = null;

    public function driverName(): string
    {
        return config('sms.driver', 'log') === 'http' ? 'http' : 'log';
    }

    /**
     * True when messages really leave the server (a gateway is configured).
     */
    public function isLive(): bool
    {
        return $this->driverName() !== 'log';
    }

    public function send(?string $to, string $message): SmsResult
    {
        $message = trim($message);
        $number = PhoneNumber::normalize($to);

        if (! $number) {
            return $this->record((string) $to, $message, SmsResult::failed('Not a Maldivian mobile number', 'invalid'));
        }
        if ($message === '') {
            return $this->record($number, $message, SmsResult::failed('Empty message', 'invalid'));
        }

        try {
            $result = $this->driver()->send($number, $message, (string) config('sms.sender_id', 'iruali'));
        } catch (Throwable $e) {
            report($e);
            $result = SmsResult::failed(mb_substr($e->getMessage(), 0, 1000));
        }

        return $this->record($number, $message, $result);
    }

    public function driver(): SmsDriver
    {
        return $this->driver ??= match ($this->driverName()) {
            'http' => new HttpSms((array) config('sms.http', [])),
            default => new LogSms,
        };
    }

    /**
     * Use another driver (tests, or a one-off command).
     */
    public function using(?SmsDriver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    protected function record(string $to, string $message, SmsResult $result): SmsResult
    {
        try {
            SmsMessage::create([
                'to' => mb_substr($to, 0, 30),
                'message' => $message,
                'status' => $result->status,
                'provider_response' => $result->providerResponse,
                'cost' => $result->cost,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }
}
