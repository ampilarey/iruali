<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A refund owed on an order, and how it was sent: one row each time money is flagged as owed
 * (PaymentService::flagRefund()), closed when the refund is recorded (PaymentService::markRefunded()).
 * Refunds flagged while one is still due are sent together, under one reference.
 *
 * @property int $id
 * @property int $order_id
 * @property string $amount
 * @property string|null $reason
 * @property string $status
 * @property string|null $method
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon|null $refunded_at
 */
class Refund extends Model
{
    public const DUE = 'due';

    public const REFUNDED = 'refunded';

    protected $fillable = ['order_id', 'amount', 'reason', 'status', 'method', 'reference', 'approved_by', 'approved_at', 'recorded_by', 'refunded_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'card' => 'card (BML)',
            'bank' => 'bank transfer',
            'wallet' => 'wallet credit',
            default => 'not sent yet',
        };
    }
}
