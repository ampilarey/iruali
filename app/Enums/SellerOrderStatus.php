<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * seller_orders.status — one shop's part of an order. Same path and look as the order itself.
 */
enum SellerOrderStatus: string
{
    use HasStatusLabels;

    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return OrderStatus::from($this->value)->label();
    }

    public function badgeClass(): string
    {
        return OrderStatus::from($this->value)->badgeClass();
    }

    public function rank(): int
    {
        return OrderStatus::from($this->value)->rank();
    }

    /**
     * Rank by stored value for the progress bars; a missing or unknown value ranks below pending.
     */
    public static function rankFor(?string $status): int
    {
        return $status === null ? -1 : (self::tryFrom($status)?->rank() ?? -1);
    }

    /**
     * Rank keyed by stored value, for the statuses on the fulfilment path (cancelled is not on it).
     *
     * @return array<string, int>
     */
    public static function ranks(): array
    {
        $ranks = [];
        foreach (self::cases() as $case) {
            if ($case !== self::Cancelled) {
                $ranks[$case->value] = $case->rank();
            }
        }

        return $ranks;
    }
}
