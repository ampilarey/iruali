<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * payout_batches.status — drafted, bank file exported, marked paid, or cancelled.
 */
enum PayoutBatchStatus: string
{
    use HasStatusLabels;

    case Draft = 'draft';
    case Exported = 'exported';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Exported => __('Exported'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800',
            self::Exported => 'bg-blue-100 text-blue-800',
            self::Paid => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Exported;
    }
}
