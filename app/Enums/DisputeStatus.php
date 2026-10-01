<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * disputes.status — open ones wait on someone; resolved ones say how it ended.
 */
enum DisputeStatus: string
{
    use HasStatusLabels;

    case Open = 'open';
    case AwaitingCustomer = 'awaiting_customer';
    case AwaitingSeller = 'awaiting_seller';
    case ResolvedRefund = 'resolved_refund';
    case ResolvedPartial = 'resolved_partial';
    case ResolvedRejected = 'resolved_rejected';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::AwaitingCustomer => __('Waiting for the customer'),
            self::AwaitingSeller => __('Waiting for the shop'),
            self::ResolvedRefund => __('Resolved: full refund'),
            self::ResolvedPartial => __('Resolved: partial refund'),
            self::ResolvedRejected => __('Resolved: not upheld'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-yellow-100 text-yellow-800',
            self::AwaitingCustomer => 'bg-blue-100 text-blue-800',
            self::AwaitingSeller => 'bg-purple-100 text-purple-800',
            self::ResolvedRefund, self::ResolvedPartial => 'bg-green-100 text-green-800',
            self::ResolvedRejected => 'bg-gray-200 text-gray-800',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::AwaitingCustomer, self::AwaitingSeller], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Open->value, self::AwaitingCustomer->value, self::AwaitingSeller->value];
    }
}
