<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's formal complaint about one shop's part of an order, decided by iruali.
 */
class Dispute extends Model
{
    public const TYPES = [
        'non_delivery' => 'Not delivered',
        'return_rejected' => 'Return was not accepted',
        'item_not_as_described' => 'Item not as described',
        'other' => 'Something else',
    ];

    public const OPEN_STATUSES = ['open', 'awaiting_customer', 'awaiting_seller'];

    public const STATUSES = [
        'open' => 'Open',
        'awaiting_customer' => 'Waiting for the customer',
        'awaiting_seller' => 'Waiting for the shop',
        'resolved_refund' => 'Resolved: full refund',
        'resolved_partial' => 'Resolved: partial refund',
        'resolved_rejected' => 'Resolved: not upheld',
    ];

    /** Days after delivery during which "not as described" can be raised. */
    public const NOT_AS_DESCRIBED_DAYS = 7;

    protected $fillable = [
        'order_id', 'seller_order_id', 'customer_id', 'seller_id', 'return_request_id', 'conversation_id',
        'type', 'status', 'details', 'amount_claimed', 'amount_resolved', 'resolution_note', 'opened_at', 'resolved_at', 'admin_id',
    ];

    protected $casts = [
        'amount_claimed' => 'decimal:2',
        'amount_resolved' => 'decimal:2',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isResolved(): bool
    {
        return ! $this->isOpen();
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type] ?? $this->type);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'open' => 'bg-yellow-100 text-yellow-800',
            'awaiting_customer' => 'bg-blue-100 text-blue-800',
            'awaiting_seller' => 'bg-purple-100 text-purple-800',
            'resolved_refund' => 'bg-green-100 text-green-800',
            'resolved_partial' => 'bg-green-100 text-green-800',
            'resolved_rejected' => 'bg-gray-200 text-gray-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}
