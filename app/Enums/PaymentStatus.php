<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * orders.payment_status — the BML card payment is confirmed or it is not.
 */
enum PaymentStatus: string
{
    use HasStatusLabels;

    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Paid => __('Paid'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-gray-100 text-gray-700',
            self::Paid => 'bg-green-100 text-green-800',
        };
    }

    /**
     * Anything that is not "paid" is shown as unpaid (older rows may hold other words).
     */
    public static function labelFor(?string $status): string
    {
        return (self::tryFrom((string) $status) ?? self::Unpaid)->label();
    }

    public static function badgeFor(?string $status): string
    {
        return (self::tryFrom((string) $status) ?? self::Unpaid)->badgeClass();
    }
}
