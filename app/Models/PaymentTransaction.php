<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    /** States after which BML will not change the transaction any more (except refunds). */
    public const FINAL_FAILED = ['CANCELLED', 'FAILED', 'EXPIRED', 'VOIDED'];

    protected $fillable = ['order_id', 'gateway', 'transaction_id', 'local_id', 'amount', 'currency', 'state', 'payment_url', 'response', 'confirmed_at'];

    protected $casts = [
        'amount' => 'integer',
        'response' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isConfirmed(): bool
    {
        return $this->state === 'CONFIRMED';
    }

    public function hasFailed(): bool
    {
        return in_array($this->state, self::FINAL_FAILED, true);
    }
}
