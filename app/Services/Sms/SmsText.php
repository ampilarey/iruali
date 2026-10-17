<?php

namespace App\Services\Sms;

use Stringable;

/**
 * A text message built from an SMS type (App\Support\SmsTypes): the words, which type it is (for the
 * log and the switches) and whether it is urgent (goes out at night and over the monthly limit).
 * An empty text means the type is switched off.
 */
final class SmsText implements Stringable
{
    public function __construct(
        public readonly string $type,
        public readonly string $text,
        public readonly bool $urgent = false,
    ) {}

    public function __toString(): string
    {
        return $this->text;
    }
}
