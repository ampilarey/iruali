<?php

namespace App\Services\Sms;

/**
 * Maldivian mobile numbers: seven digits starting with 7 or 9, with or without the +960 code.
 */
final class PhoneNumber
{
    /**
     * "+960 777-1234", "009607771234", "7771234" → "+9607771234"; anything else → null.
     */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if (str_starts_with($digits, '00960')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '960') && strlen($digits) === 10) {
            $digits = substr($digits, 3);
        }

        return preg_match('/^[79]\d{6}$/', $digits) ? '+960'.$digits : null;
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }
}
