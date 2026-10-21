<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * orders.payment_status — the payment is confirmed or it is not. A pre-order with a booking fee
 * is "deposit_paid" between its booking fee and its balance (App\Services\BookingFeeService).
 */
enum PaymentStatus: string
{
    use HasStatusLabels;

    case Unpaid = 'unpaid';
    case DepositPaid = 'deposit_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::DepositPaid => __('Booking fee paid'),
            self::Paid => __('Paid'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-gray-100 text-gray-700',
            self::DepositPaid => 'bg-amber-100 text-amber-800',
            self::Paid => 'bg-green-100 text-green-800',
        };
    }

    /**
     * Anything else is shown as unpaid (older rows may hold other words).
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
