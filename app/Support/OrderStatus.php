<?php

namespace App\Support;

use App\Enums\OrderStatus as Status;

/**
 * Labels for order and shop-part statuses. The source is App\Enums\OrderStatus; this class
 * stays so the many views that call OrderStatus::label() keep working.
 */
final class OrderStatus
{
    /**
     * The translated label for a stored status value.
     */
    public static function label(?string $status): string
    {
        return Status::labelFor($status);
    }

    /**
     * The customer-facing progress steps, translated, in order.
     *
     * @return array<string, string>
     */
    public static function steps(): array
    {
        return Status::steps();
    }
}
