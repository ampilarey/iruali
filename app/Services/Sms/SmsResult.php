<?php

namespace App\Services\Sms;

/**
 * What happened to one text message.
 */
final class SmsResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $providerResponse = null,
        public readonly ?float $cost = null,
    ) {}

    public static function sent(?string $response = null, ?float $cost = null): self
    {
        return new self(true, 'sent', $response, $cost);
    }

    public static function logged(): self
    {
        return new self(true, 'logged');
    }

    public static function failed(?string $response = null, string $status = 'failed'): self
    {
        return new self(false, $status, $response);
    }
}
