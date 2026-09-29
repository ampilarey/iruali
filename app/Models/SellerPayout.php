<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money iruali has paid a shop (by bank transfer) for a batch of delivered, paid order parts.
 */
class SellerPayout extends Model
{
    protected $fillable = ['seller_id', 'amount', 'reference', 'note', 'created_by', 'paid_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class, 'payout_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SellerAdjustment::class, 'payout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
