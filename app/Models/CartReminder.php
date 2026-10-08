<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reminder email sent about a cart the customer walked away from. One row per cart and stage,
 * so nobody is nudged twice.
 */
class CartReminder extends Model
{
    protected $fillable = ['cart_id', 'stage', 'sent_at', 'voucher_id'];

    protected $casts = [
        'stage' => 'integer',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
