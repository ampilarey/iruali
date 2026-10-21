<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deduction (negative) or credit (positive) on a shop's earnings, settled in its next payout.
 */
class SellerAdjustment extends Model
{
    // seller_order_id: a booking fee kept for the shop when the order was cancelled (BookingFeeService)
    protected $fillable = ['seller_id', 'amount', 'reason', 'return_request_id', 'seller_order_id', 'payout_id'];

    protected $casts = ['amount' => 'decimal:2'];

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<SellerPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'payout_id');
    }

    /** @return BelongsTo<SellerOrder, $this> */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}
