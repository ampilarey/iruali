<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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

    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->price;
    }

    /**
     * What one unit costs now: the variant's price when the line has one, else the product's.
     */
    public function getUnitPriceAttribute(): float
    {
        if ($this->variant) {
            return $this->variant->setRelation('product', $this->product)->effectivePrice();
        }

        return (float) ($this->product->final_price ?? 0);
    }

    public function getFinalPriceAttribute()
    {
        return $this->unit_price;
    }

    /**
     * Units that can still be bought of this line (the variant's stock when it has one), or when it
     * is sold out and the shop takes pre-orders for it, the pre-order units still open.
     */
    public function availableStock(): int
    {
        if ($this->product_variant_id) {
            $stock = $this->variant && $this->variant->is_active ? (int) $this->variant->stock_quantity : 0;
        } else {
            $stock = (int) ($this->product->stock_quantity ?? 0);
        }

        // Sold out: the pre-order units still open, if the shop takes pre-orders (App\Services\PreorderService)
        if ($stock > 0 || ! $this->product || ($this->product_variant_id && ! $this->variant) || ($this->product->has_variants && ! $this->product_variant_id)) {
            return $stock;
        }

        return app(\App\Services\PreorderService::class)->unitsLeft($this->product, $this->variant);
    }

    /**
     * The variant as shown to the customer ("M / Blue"), or null for a plain product.
     */
    public function variantLabel(): ?string
    {
        return $this->variant?->displayName();
    }
}
