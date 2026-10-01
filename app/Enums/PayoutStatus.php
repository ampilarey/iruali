<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * seller_payouts.status — pending while the payout sits in a draft or exported batch, paid once transferred.
 */
enum PayoutStatus: string
{
    use HasStatusLabels;

    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Paid => 'bg-green-100 text-green-800',
        };
    }
}
