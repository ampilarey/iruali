<?php

namespace App\Models;

use App\Services\DeliveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A delivery area's own fee and transit days (Admin → Delivery). The area is "greater_male",
 * "islands" (the default for every island without its own row) or an atoll name. A blank fee
 * falls back to the fee in Settings; blank days fall back to the other islands' days.
 */
class DeliveryRate extends Model
{
    protected $fillable = ['area', 'fee', 'transit_days_min', 'transit_days_max'];

    protected $casts = [
        'fee' => 'decimal:2',
        'transit_days_min' => 'integer',
        'transit_days_max' => 'integer',
    ];

    protected static function booted(): void
    {
        // DeliveryService keeps the table in the cache; any change clears it
        static::saved(fn () => Cache::forget(DeliveryService::RATES_CACHE));
        static::deleted(fn () => Cache::forget(DeliveryService::RATES_CACHE));
    }
}
