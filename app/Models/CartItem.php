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
        // A quoted line (bulk quote) keeps the price the shop quoted
        if ($this->quote_request_id !== null) {
            return round((float) $this->price, 2);
        }

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
     * Units that can still be bought of this line (the variant's stock when it has one).
     */
    public function availableStock(): int
    {
        if ($this->product_variant_id) {
            return $this->variant && $this->variant->is_active ? (int) $this->variant->stock_quantity : 0;
        }

        return (int) ($this->product->stock_quantity ?? 0);
    }

    /**
     * The variant as shown to the customer ("M / Blue"), or null for a plain product.
     */
    public function variantLabel(): ?string
    {
        return $this->variant?->displayName();
    }

    /**
     * The accepted bulk quote this line was made from: its quantity is locked and its price fixed
     * (QuoteService).
     *
     * @return BelongsTo<QuoteRequest, $this>
     */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }
}
