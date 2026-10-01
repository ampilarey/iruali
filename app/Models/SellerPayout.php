<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money iruali pays a shop (by bank transfer) for a set of delivered, paid order parts. Recorded
 * directly once transferred (status paid), or drafted in a payout batch first (status pending).
 */
class SellerPayout extends Model
{
    protected $fillable = ['seller_id', 'amount', 'status', 'reference', 'note', 'created_by', 'payout_batch_id', 'paid_at'];

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

    /**
     * The bulk-transfer batch this payout was made in, if any.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    /**
     * paid: the money was sent; pending: waiting in a payout batch.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
