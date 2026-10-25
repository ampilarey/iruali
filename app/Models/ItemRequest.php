<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's request for an item iruali doesn't list, usually from a shop abroad
 * (App\Services\ItemRequestService). new → quoted (an admin's price, with a pre-order product only this
 * customer can buy) → accepted (in their cart) → ordered; or declined, or cancelled by the customer. A
 * price past its last day counts as expired (isExpired()) without changing the status.
 */
class ItemRequest extends Model
{
    public const NEW = 'new';

    public const QUOTED = 'quoted';

    public const ACCEPTED = 'accepted';

    public const ORDERED = 'ordered';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    /** Requests a customer can have waiting at once (new, quoted or accepted). */
    public const MAX_OPEN = 5;

    protected $fillable = ['link', 'title', 'details', 'options', 'quantity', 'island_id', 'budget', 'needed_by'];

    protected $casts = [
        'quantity' => 'integer',
        'budget' => 'decimal:2',
        'needed_by' => 'date',
        'unit_price' => 'decimal:2',
        'deposit_percent' => 'integer',
        'ships_in_days' => 'integer',
        'valid_until' => 'date',
        'quoted_at' => 'datetime',
        'declined_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ordered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Island, $this> */
    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function quotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by');
    }

    /** @param Builder<ItemRequest> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [self::NEW, self::QUOTED, self::ACCEPTED]);
    }

    /** "Request #12" */
    public function label(): string
    {
        return __('Request #:id', ['id' => $this->id]);
    }

    public function isExpired(): bool
    {
        return in_array($this->status, [self::QUOTED, self::ACCEPTED], true)
            && $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /** The customer can accept the price now. */
    public function canAccept(): bool
    {
        return $this->status === self::QUOTED && ! $this->isExpired() && $this->product_id !== null;
    }

    /** The customer can still cancel (nothing ordered yet). */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::NEW, self::QUOTED, self::ACCEPTED], true);
    }

    public function total(): float
    {
        return round((float) $this->unit_price * $this->quantity, 2);
    }

    /** What is paid at checkout: the booking fee, or the whole price. */
    public function payNow(): float
    {
        return $this->deposit_percent ? round($this->total() * $this->deposit_percent / 100, 2) : $this->total();
    }

    public function statusLabel(): string
    {
        if ($this->isExpired()) {
            return __('Price expired');
        }

        return [
            self::NEW => __('Waiting for our price'),
            self::QUOTED => __('Price ready'),
            self::ACCEPTED => __('In your cart'),
            self::ORDERED => __('Ordered'),
            self::DECLINED => __('We can\'t bring this'),
            self::CANCELLED => __('Cancelled'),
        ][$this->status] ?? $this->status;
    }
}
