<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * return_requests.status
 */
enum ReturnStatus: string
{
    use HasStatusLabels;

    case Requested = 'requested';
    case Approved = 'approved';
    case Refunded = 'refunded';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Return requested'),
            self::Approved => __('Return approved'),
            self::Refunded => __('Refunded'),
            self::Rejected => __('Return not accepted'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Requested => 'bg-yellow-100 text-yellow-800',
            self::Approved => 'bg-blue-100 text-blue-800',
            self::Refunded => 'bg-green-100 text-green-800',
            self::Rejected => 'bg-red-100 text-red-800',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Requested || $this === self::Approved;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Requested->value, self::Approved->value];
    }
}
