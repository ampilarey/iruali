<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The customer's 1-5 rating of a delivered order, with an optional comment.
 */
class OrderFeedback extends Model
{
    protected $table = 'order_feedback';

    protected $fillable = ['order_id', 'user_id', 'rating', 'comment'];

    protected $casts = ['rating' => 'integer'];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
