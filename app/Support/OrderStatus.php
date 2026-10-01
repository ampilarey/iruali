<?php

namespace App\Support;

/**
 * Labels for order and shop-part statuses, in one place so every page agrees.
 */
final class OrderStatus
{
    public const LABELS = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    /**
     * The English label (pass it through __() to translate).
     */
    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    /**
     * The customer-facing progress steps, translated, in order.
     *
     * @return array<string, string>
     */
    public static function steps(): array
    {
        return [
            'pending' => __('Order placed'),
            'processing' => __('Preparing'),
            'shipped' => __('On its way'),
            'out_for_delivery' => __('Out for delivery'),
            'delivered' => __('Delivered'),
        ];
    }
}
