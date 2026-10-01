<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's request to return items from one shop's part of an order.
 */
class ReturnRequest extends Model
{
    public const REASONS = [
        'damaged' => 'Arrived damaged',
        'faulty' => 'Faulty or not working',
        'wrong_item' => 'Wrong item, size or colour',
        'not_as_described' => 'Not as described',
        'missing' => 'Item missing from the delivery',
        'change_of_mind' => 'Changed my mind',
    ];

    /** Reasons where the shop is at fault: the delivery fee share is refunded too. */
    public const SHOP_FAULT = ['damaged', 'faulty', 'wrong_item', 'not_as_described', 'missing'];

    protected $fillable = [
        'order_id', 'seller_order_id', 'user_id', 'status', 'reason', 'details', 'photo_path', 'items_value',
        'refund_amount', 'restocked', 'admin_note', 'refund_reference', 'resolved_by', 'resolved_at', 'refunded_at',
    ];

    protected $casts = [
        'items_value' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'restocked' => 'boolean',
        'resolved_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ReturnStatus::openValues(), true);
    }

    public function reasonLabel(): string
    {
        return __(self::REASONS[$this->reason] ?? $this->reason);
    }

    public function getStatusBadgeAttribute(): string
    {
        return ReturnStatus::badgeFor($this->status);
    }

    public function statusLabel(): string
    {
        return ReturnStatus::labelFor($this->status);
    }

    public function statusEnum(): ?ReturnStatus
    {
        return ReturnStatus::tryFrom((string) $this->status);
    }
}
