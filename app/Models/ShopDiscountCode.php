<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code a shop runs on its own items and pays for itself: a percent or a fixed MVR amount
 * off the shop's eligible items, with an optional minimum spend, start and end, a total use limit
 * and a limit per customer. Codes are unique per shop and matched without regard to case (they are
 * kept in capitals). ShopDiscountService applies them.
 */
class ShopDiscountCode extends Model
{
    public const TYPES = ['percent', 'fixed'];

    /** seller_id is set by the controller from the signed-in shop, never from input. */
    protected $fillable = [
        'code', 'type', 'value', 'min_spend', 'starts_at', 'ends_at', 'max_uses', 'max_uses_per_customer', 'applies_to', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_spend' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_uses' => 'integer',
        'max_uses_per_customer' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The form a code is stored and compared in: trimmed, in capitals.
     */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function setCodeAttribute(mixed $value): void
    {
        $this->attributes['code'] = static::normalize((string) $value);
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The products the code is limited to (when applies_to is "selected").
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shop_discount_code_product');
    }

    /** @return HasMany<ShopDiscountRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(ShopDiscountRedemption::class);
    }

    public function isPercent(): bool
    {
        return $this->type === 'percent';
    }

    public function appliesToAllProducts(): bool
    {
        return $this->applies_to !== 'selected';
    }

    /**
     * Does the code cover this product? (The products relation is loaded once for a cart.)
     */
    public function covers(int $productId): bool
    {
        return $this->appliesToAllProducts() || $this->products->contains('id', $productId);
    }

    /**
     * "10%" or "MVR 50.00": what the code takes off.
     */
    public function valueLabel(): string
    {
        return $this->isPercent() ? static::percentText((float) $this->value).'%' : Money::format($this->value);
    }

    /**
     * Where the code stands by its own settings (use limits are counted separately):
     * paused, scheduled (not started), expired or live.
     */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'paused',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at !== null && $this->ends_at->isPast() => 'expired',
            default => 'live',
        };
    }

    /**
     * 5, 12.5: a percent without trailing zeros.
     */
    public static function percentText(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }
}
