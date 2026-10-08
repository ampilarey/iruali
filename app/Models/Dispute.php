<?php

namespace App\Models;

use App\Enums\DisputeStatus;
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<SellerOrder, $this> */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', DisputeStatus::openValues());
    }

    public function isOpen(): bool
    {
        return in_array($this->status, DisputeStatus::openValues(), true);
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
        return DisputeStatus::labelFor($this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return DisputeStatus::badgeFor($this->status);
    }

    public function statusEnum(): ?DisputeStatus
    {
        return DisputeStatus::tryFrom((string) $this->status);
    }
}
