<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deduction (negative) or credit (positive) on a shop's earnings, settled in its next payout.
 */
class SellerAdjustment extends Model
{
    protected $fillable = ['seller_id', 'amount', 'reason', 'return_request_id', 'payout_id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'payout_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}
