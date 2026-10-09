<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'product_variant_id',
    ];

    protected $casts = [
        'saved_price' => 'decimal:2',
        'notified_price' => 'decimal:2',
        'price_drop_notified_at' => 'datetime',
    ];

    /** A price-drop alert needs the price to fall by at least this share of the last known price… */
    public const DROP_MIN_PERCENT = 5;

    /** …and by at least this many rufiyaa. */
    public const DROP_MIN_AMOUNT = 10;

    protected static function booted(): void
    {
        // Remember what the product cost when it was saved (price-drop alerts compare with it)
        static::creating(function (Wishlist $item) {
            if ($item->saved_price === null) {
                $item->saved_price = $item->currentPrice();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Check if a product is in a user's wishlist
     */
    public static function isInWishlist(int $userId, int $productId): bool
    {
        return static::where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Get wishlist items for a user
     */
    public static function getUserWishlist(int $userId)
    {
        return static::where('user_id', $userId)
            ->with('product')
            ->get();
    }

    /**
     * Add a product to user's wishlist (with duplicate check)
     */
    public static function addToWishlist(int $userId, int $productId): array
    {
        // Check if already exists
        if (static::isInWishlist($userId, $productId)) {
            return ['success' => false, 'message' => 'Product is already in your wishlist.'];
        }

        try {
            static::create([
                'user_id' => $userId,
                'product_id' => $productId,
            ]);

            return ['success' => true, 'message' => 'Product added to wishlist successfully.'];
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation
            if ($e->getCode() == 23000) {
                return ['success' => false, 'message' => 'Product is already in your wishlist.'];
            }

            return ['success' => false, 'message' => 'Failed to add product to wishlist.'];
        }
    }

    /**
     * Remove a product from user's wishlist
     */
    public static function removeFromWishlist(int $userId, int $productId): bool
    {
        return static::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete() > 0;
    }

    // ---- Price-drop alerts ----------------------------------------------------------------

    /**
     * What the saved item costs now, as shoppers see it: the variant's price when one was saved,
     * else the product's headline price (the lowest variant price when variants are priced
     * differently), campaign discount and markdown included. Null when the product is gone.
     */
    public function currentPrice(): ?float
    {
        $product = $this->product;
        if (! $product) {
            return null;
        }

        $variant = $this->product_variant_id ? $this->variant : null;
        if ($variant) {
            return round($variant->setRelation('product', $product)->effectivePrice(), 2);
        }

        return round((float) ($product->from_price ?? $product->final_price), 2);
    }

    /**
     * The price a drop is measured from: the last price the customer was told about, else the
     * price when they saved it.
     */
    public function referencePrice(): ?float
    {
        $reference = $this->notified_price ?? $this->saved_price;

        return $reference === null ? null : (float) $reference;
    }

    /**
     * Is $now low enough under $reference for an alert: at least DROP_MIN_PERCENT and at least
     * DROP_MIN_AMOUNT below it? Worked out in laari so 5 % of MVR 200.00 is exactly MVR 10.00.
     */
    public static function isPriceDrop(?float $reference, ?float $now): bool
    {
        if ($reference === null || $now === null) {
            return false;
        }

        $was = (int) round($reference * 100);
        $drop = $was - (int) round($now * 100);

        return $drop >= self::DROP_MIN_AMOUNT * 100 && $drop * 100 >= $was * self::DROP_MIN_PERCENT;
    }

    /**
     * How much less the item costs than when it was saved (the wishlist page shows it), or null
     * when it costs the same or more, or the saved price is not known.
     */
    public function savedPriceDrop(): ?float
    {
        $now = $this->currentPrice();
        if ($this->saved_price === null || $now === null) {
            return null;
        }

        $drop = round((float) $this->saved_price - $now, 2);

        return $drop >= 0.01 ? $drop : null;
    }

    /**
     * Can the item be bought right now: the product is on show and not sold out (nor the saved
     * variant)? Price-drop alerts skip the rest.
     */
    public function isBuyable(): bool
    {
        $product = $this->product;
        if (! $product || ! $product->is_active) {
            return false;
        }

        $variant = $this->product_variant_id ? $this->variant : null;
        if ($variant) {
            return $variant->isInStock();
        }

        return $product->effectiveStock() > 0;
    }
}
