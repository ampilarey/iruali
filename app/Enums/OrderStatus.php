<?php

namespace App\Enums;

use App\Enums\Concerns\HasStatusLabels;

/**
 * orders.status — the customer order as a whole (derived from its shop parts by FulfilmentService).
 */
enum OrderStatus: string
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
        return match ($this) {
            self::Pending => __('Pending'),
            self::Processing => __('Processing'),
            self::Shipped => __('Shipped'),
            self::OutForDelivery => __('Out for delivery'),
            self::Delivered => __('Delivered'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Processing => 'bg-blue-100 text-blue-800',
            self::Shipped => 'bg-purple-100 text-purple-800',
            self::OutForDelivery => 'bg-indigo-100 text-indigo-800',
            self::Delivered => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }

    /**
     * The customer-facing progress step for this status (null for cancelled, which is off the path).
     */
    public function step(): ?string
    {
        return match ($this) {
            self::Pending => __('Order placed'),
            self::Processing => __('Preparing'),
            self::Shipped => __('On its way'),
            self::OutForDelivery => __('Out for delivery'),
            self::Delivered => __('Delivered'),
            self::Cancelled => null,
        };
    }

    /**
     * Position on the fulfilment path; cancelled ranks below everything.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Processing => 1,
            self::Shipped => 2,
            self::OutForDelivery => 3,
            self::Delivered => 4,
            self::Cancelled => -1,
        };
    }

    /**
     * The progress steps shown to customers, in order, keyed by stored value.
     *
     * @return array<string, string>
     */
    public static function steps(): array
    {
        $steps = [];
        foreach (self::cases() as $case) {
            if (($step = $case->step()) !== null) {
                $steps[$case->value] = $step;
            }
        }

        return $steps;
    }
}
