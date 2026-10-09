<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'voucher_code',
        'voucher_discount',
        'loyalty_points_earned', 'loyalty_points_awarded_at',
        'points_redeemed',
        'points_redeemed_discount',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'shipping_phone',
        'delivery_zone',
        'billing_address',
        'payment_method',
        'payment_status',
        'paid_at',
        'refund_status', 'refund_amount', 'refund_reason', 'refund_reference', 'refunded_at',
        'notes',
        'tracking_number',
        'guest_email', 'guest_name', 'guest_token',
        'wallet_amount', 'wallet_refunded_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'loyalty_points_awarded_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'wallet_amount' => 'decimal:2',
        'wallet_refunded_at' => 'datetime',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        // iruali's GST as it was when the order was placed (App\Services\GstService)
        'gst_platform_registered' => 'boolean',
        'gst_rate' => 'decimal:2',
        'delivery_gst' => 'decimal:2',
        'gst_captured_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * One part per shop: its own fulfilment status and the shop's earnings.
     *
     * @return HasMany<SellerOrder, $this>
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getFormattedOrderNumberAttribute()
    {
        return sprintf('ORD-%06d', $this->id);
    }

    public function getStatusBadgeAttribute(): string
    {
        return OrderStatus::badgeFor($this->status);
    }

    /**
     * The status as an enum case (null for a legacy value the enum does not know).
     */
    public function statusEnum(): ?OrderStatus
    {
        return OrderStatus::tryFrom((string) $this->status);
    }

    public function paymentStatusEnum(): ?PaymentStatus
    {
        return PaymentStatus::tryFrom((string) $this->payment_status);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to include only soft deleted orders
     */
    public function scopeOnlyTrashed($query)
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope to include both active and soft deleted orders
     */
    public function scopeWithTrashed($query)
    {
        return $query->withTrashed();
    }

    /**
     * Check if order is soft deleted
     */
    public function isTrashed(): bool
    {
        return $this->trashed();
    }

    /**
     * Restore a soft deleted order
     */
    public function restoreOrder(): bool
    {
        return $this->restore();
    }

    /**
     * Force delete an order (permanently remove)
     */
    public function forceDeleteOrder(): bool
    {
        return $this->forceDelete();
    }

    /**
     * Message threads about this order (one per shop).
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Placed without an account (guest checkout). Guest orders keep their token even after
     * the guest signs up and the order is attached to the new account.
     */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Who the order is for: the account holder, or the guest who gave their details at checkout.
     */
    public function customerName(): ?string
    {
        return $this->user->name ?? $this->guest_name;
    }

    public function customerEmail(): ?string
    {
        return $this->user->email ?? $this->guest_email;
    }

    /**
     * The guest's order page: a signed link that carries the order's token (only for orders that have one).
     */
    public function guestUrl(string $route = 'show'): ?string
    {
        if (! $this->guest_token) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::signedRoute('guest.orders.'.$route, ['order' => $this->getKey(), 'token' => $this->guest_token]);
    }

    /**
     * Where the customer views this order: My Orders for accounts, the signed guest page otherwise.
     */
    public function customerUrl(): string
    {
        return $this->isGuest() && $this->guest_token ? $this->guestUrl() : route('orders.show', $this);
    }

    public function customerReceiptUrl(): string
    {
        return $this->isGuest() && $this->guest_token ? $this->guestUrl('receipt') : route('orders.receipt', $this);
    }

    // ---- Wallet and gift cards ------------------------------------------------------------

    /**
     * What still has to be paid by card after the wallet's share.
     */
    public function cardAmount(): float
    {
        return round(max(0, (float) $this->total_amount - (float) $this->wallet_amount), 2);
    }

    public function giftCard(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GiftCard::class);
    }

    public function isGiftCardOrder(): bool
    {
        return $this->relationLoaded('giftCard') ? $this->giftCard !== null : $this->giftCard()->exists();
    }

    /**
     * Leave out orders placed by the smoke-test customer (php artisan iruali:smoke --place-order).
     */
    public function scopeWithoutSmokeTests($query)
    {
        return $query->whereNotIn('orders.user_id', User::query()->where('is_smoke_test', true)->select('id'));
    }
}
