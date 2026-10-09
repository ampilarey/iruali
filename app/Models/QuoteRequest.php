<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Services\QuoteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business customer's request for a bulk price on one product, the shop's quote and what became
 * of it (QuoteStatus). QuoteService has the rules; the customer, the shop and the status are set
 * there, never from input.
 */
class QuoteRequest extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'product_name', 'variant_name', 'quantity',
        'island_id', 'delivery_island', 'delivery_atoll', 'needed_by', 'notes',
        'business_name', 'business_tin', 'business_address',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quoted_quantity' => 'integer',
        'needed_by' => 'date',
        'valid_until' => 'date',
        'list_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'quoted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ordered_at' => 'datetime',
        'declined_at' => 'datetime',
        'expired_at' => 'datetime',
        'last_message_at' => 'datetime',
        'customer_unread' => 'integer',
        'seller_unread' => 'integer',
    ];

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

    /**
     * The product, also when it has since gone to the bin.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
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

    /** @return HasMany<QuoteMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(QuoteMessage::class)->orderBy('id');
    }

    /**
     * The cart line(s) the accepted quote made (one at a time in practice).
     *
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @param  Builder<QuoteRequest>  $query
     * @return Builder<QuoteRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', QuoteStatus::openValues());
    }

    /**
     * New requests a shop has not answered for more than QuoteService::WAITING_DAYS days (Admin → Inbox).
     *
     * @param  Builder<QuoteRequest>  $query
     * @return Builder<QuoteRequest>
     */
    public function scopeWaitingForShop(Builder $query): Builder
    {
        return $query->where('status', QuoteStatus::New->value)->where('created_at', '<=', now()->subDays(QuoteService::WAITING_DAYS));
    }

    public function statusEnum(): ?QuoteStatus
    {
        return QuoteStatus::tryFrom((string) $this->status);
    }

    public function statusLabel(): string
    {
        return QuoteStatus::labelFor($this->status);
    }

    public function statusBadge(): string
    {
        return QuoteStatus::badgeFor($this->status);
    }

    public function hasStatus(QuoteStatus ...$statuses): bool
    {
        return in_array($this->statusEnum(), $statuses, true);
    }

    public function isOpen(): bool
    {
        return (bool) $this->statusEnum()?->isOpen();
    }

    /**
     * The quote's day has passed: it holds to the end of its "valid until" day (Maldives time).
     */
    public function isExpired(): bool
    {
        return $this->valid_until !== null && today()->gt($this->valid_until);
    }

    /** "Quote #123", how the request is named in the cart, emails and the Seller Centre. */
    public function label(): string
    {
        return __('Quote #:number', ['number' => $this->id]);
    }

    /** The product as the customer sees it now, or its name as it was when asked. */
    public function productName(): string
    {
        $name = $this->product ? (string) $this->product->name : '';

        return $name !== '' ? $name : (string) $this->product_name;
    }

    /** "Hoodie – M / Blue" */
    public function displayName(): string
    {
        return $this->variant_name ? $this->productName().' – '.$this->variant_name : $this->productName();
    }

    public function shopName(): string
    {
        return $this->seller ? $this->seller->shopName() : 'iruali';
    }

    /**
     * Price × quantity of the quote, worked out in laari.
     */
    public function lineTotal(): float
    {
        return (int) round((float) $this->unit_price * 100) * (int) $this->quoted_quantity / 100;
    }

    /**
     * How much under the shop's own price the quote is, in percent (null when it is not lower).
     */
    public function savingPercent(): ?float
    {
        $list = (float) $this->list_price;
        $price = (float) $this->unit_price;

        return $list > 0 && $price > 0 && $price < $list ? round(($list - $price) / $list * 100, 1) : null;
    }

    /** The delivery island as typed or picked: "Hithadhoo, Addu". */
    public function deliveryPlace(): string
    {
        return collect([$this->delivery_island, $this->delivery_atoll])->filter()->join(', ');
    }

    /** Who declined it, for people: the shop's name, "you"/"the customer" or iruali. */
    public function declinedByLabel(string $viewer): string
    {
        return match ($this->declined_by) {
            'shop' => $viewer === 'seller' ? __('You declined it') : __('Declined by :shop', ['shop' => $this->shopName()]),
            'customer' => $viewer === 'customer' ? __('You declined it') : __('Declined by the customer'),
            default => __('Closed by iruali'),
        };
    }
}
