<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer following a brand (Brand page → Follow). notified_at is how far the daily digest
 * (brands:notify-followers) has covered it: news about the brand counts from there, or from when
 * the customer followed while nothing has been sent yet.
 */
class BrandFollow extends Model
{
    protected $fillable = ['user_id', 'brand_id', 'notified_at'];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** The moment news about the brand counts from for this follower. */
    public function newsSince(): CarbonInterface
    {
        return $this->notified_at ?? $this->created_at ?? now();
    }
}
