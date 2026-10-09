<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    /** refund_value: what the customer paid for these units (after the shop's discounts). */
    protected $fillable = ['return_request_id', 'order_item_id', 'quantity', 'refund_value'];

    protected $casts = [
        'refund_value' => 'decimal:2',
    ];

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
