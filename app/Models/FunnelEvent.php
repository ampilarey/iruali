<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of the shopping funnel taken by an anonymised visitor (see FunnelService).
 */
class FunnelEvent extends Model
{
    public const UPDATED_AT = null;

    public const EVENTS = ['view_product', 'add_to_cart', 'begin_checkout', 'order_paid'];

    protected $fillable = ['session_hash', 'user_id', 'event', 'product_id', 'order_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
