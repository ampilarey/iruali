<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Delivery fees by area, set in Admin → Settings.
 */
class DeliveryService
{
    /** Islands charged the Greater Malé rate. */
    public const GREATER_MALE = ['male', 'malé', 'hulhumale', 'hulhumalé', 'villimale', 'villimalé', 'villingili', 'hulhule', 'hulhulé'];

    public static function zones(): array
    {
        return [
            'greater_male' => __('Greater Malé (Malé, Hulhumalé, Villimalé)'),
            'islands' => __('Other islands'),
        ];
    }

    /**
     * Pick the zone from what the customer chose, else guess it from the island name.
     */
    public function zoneFor(?string $zone, ?string $city = null): string
    {
        if ($zone && array_key_exists($zone, self::zones())) {
            return $zone;
        }

        return in_array(mb_strtolower(trim((string) $city)), self::GREATER_MALE, true) ? 'greater_male' : 'islands';
    }

    /**
     * Delivery fee for a zone, given the order's goods total after discounts.
     */
    public function fee(string $zone, float $goodsTotal): float
    {
        $freeOver = (float) Setting::get('free_delivery_over');
        if ($freeOver > 0 && $goodsTotal >= $freeOver) {
            return 0.0;
        }

        return (float) Setting::get($zone === 'greater_male' ? 'delivery_fee_greater_male' : 'delivery_fee_islands');
    }

    /**
     * Fees per zone for this goods total (for showing on the checkout page).
     */
    public function quotes(float $goodsTotal): array
    {
        return collect(self::zones())->mapWithKeys(fn ($label, $zone) => [$zone => $this->fee($zone, $goodsTotal)])->all();
    }
}
