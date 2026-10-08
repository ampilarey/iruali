<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The message thread about one shop's part of an order: the customer, the shop and iruali support.
 */
class Conversation extends Model
{
    public const ROLES = ['customer', 'seller', 'admin'];

    protected $fillable = [
        'order_id', 'seller_order_id', 'customer_id', 'seller_id', 'last_message_at',
        'customer_unread_count', 'seller_unread_count', 'admin_unread_count', 'status',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'customer_unread_count' => 'integer',
        'seller_unread_count' => 'integer',
        'admin_unread_count' => 'integer',
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

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * How this user takes part: 'customer', 'seller', 'admin' or null for an outsider.
     */
    public function roleOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }
        if ($user->id === $this->customer_id) {
            return 'customer';
        }
        if ($this->seller_id && $user->id === $this->seller_id) {
            return 'seller';
        }

        return $user->isAdmin() ? 'admin' : null;
    }

    public function shopName(): string
    {
        return $this->seller ? ($this->seller->business_name ?: $this->seller->name) : 'iruali';
    }

    public function unreadFor(string $role): int
    {
        return (int) ($this->{$role.'_unread_count'} ?? 0);
    }
}
