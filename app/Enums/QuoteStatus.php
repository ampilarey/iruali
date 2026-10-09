<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * quote_requests.status: new (waiting for the shop) → quoted (the shop sent a price) → accepted
 * (the quote is in the customer's cart) → ordered (checked out); or declined (by the shop, the
 * customer or iruali) or expired (its "valid until" day passed before it was ordered).
 */
enum QuoteStatus: string
{
    use HasStatusLabels;

    case New = 'new';
    case Quoted = 'quoted';
    case Accepted = 'accepted';
    case Ordered = 'ordered';
    case Declined = 'declined';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New request'),
            self::Quoted => __('Quoted'),
            self::Accepted => __('Accepted'),
            self::Ordered => __('Ordered'),
            self::Declined => __('Declined'),
            self::Expired => __('Expired'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'bg-yellow-100 text-yellow-800',
            self::Quoted => 'bg-blue-100 text-blue-800',
            self::Accepted => 'bg-purple-100 text-purple-800',
            self::Ordered => 'bg-green-100 text-green-800',
            self::Declined => 'bg-gray-200 text-gray-800',
            self::Expired => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Still going: the customer can't ask another quote for the same product meanwhile.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Quoted, self::Accepted], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::New->value, self::Quoted->value, self::Accepted->value];
    }
}
