<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money that came into iruali's BML account, from an imported statement, and the order it pays.
 */
class BankStatementLine extends Model
{
    protected $fillable = ['fingerprint', 'batch', 'posted_on', 'description', 'reference', 'amount', 'order_id', 'match_kind', 'status'];

    protected $casts = ['posted_on' => 'date', 'amount' => 'decimal:2'];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
