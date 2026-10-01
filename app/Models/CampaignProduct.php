<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product a shop put into a campaign, with its discount and whether an admin approved it.
 */
class CampaignProduct extends Model
{
    protected $fillable = ['campaign_id', 'product_id', 'seller_id', 'discount_percent', 'approved_at'];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Campaign::forgetDiscounts());
        static::deleted(fn () => Campaign::forgetDiscounts());
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function discount(): float
    {
        return (float) ($this->discount_percent ?? $this->campaign?->discount_percent ?? 0);
    }
}
