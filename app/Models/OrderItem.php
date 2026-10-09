<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'variant_name',
        'variant_sku',
        'quantity',
        'price',
        'quote_request_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        // A pre-order line (App\Services\PreorderService)
        'is_preorder' => 'boolean',
        'preorder_ship_date' => 'date',
        'preorder_allocated_quantity' => 'integer',
        'preorder_allocated_at' => 'datetime',
        'preorder_late_at' => 'datetime',
    ];

    /**
     * Keep each shop's part of the order (status and earnings) in step with the items.
     */
    protected static function booted(): void
    {
        static::created(function (OrderItem $item) {
            if ($item->order) {
                app(\App\Services\FulfilmentService::class)->refreshPart($item->order, $item->product?->seller_id ?: null);
            }
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * The product name plus the variant as it was when ordered ("Hoodie – M / Blue").
     */
    public function displayName(): string
    {
        $name = (string) ($this->product->name ?? __('Product'));

        return $this->variant_name ? $name.' – '.$this->variant_name : $name;
    }

    /**
     * What the shop took off this line: its multi-buy saving plus its share of the shop's code.
     */
    public function shopDiscount(): float
    {
        return round((float) $this->multibuy_discount + (float) $this->shop_code_discount, 2);
    }

    /**
     * The line as the customer paid for it, before any iruali voucher or points: price × quantity
     * less the shop's discounts.
     */
    public function netTotal(): float
    {
        return round((float) $this->price * (int) $this->quantity - $this->shopDiscount(), 2);
    }

    // ---- Pre-orders -----------------------------------------------------------------------

    /**
     * Units of this pre-order line still waiting for their stock (0 for an ordinary line).
     */
    public function preorderWaitingQuantity(): int
    {
        if (! $this->is_preorder || $this->preorder_allocated_at !== null) {
            return 0;
        }

        return max(0, (int) $this->quantity - (int) $this->preorder_allocated_quantity);
    }

    public function isPreorderWaiting(): bool
    {
        return $this->preorderWaitingQuantity() > 0;
    }

    /**
     * Units this line took from stock: all of them for an ordinary line, only those the arrived
     * stock has covered for a pre-order. What a cancellation may put back on the shelf.
     */
    public function stockedQuantity(): int
    {
        return $this->is_preorder && $this->preorder_allocated_at === null
            ? min((int) $this->quantity, (int) $this->preorder_allocated_quantity)
            : (int) $this->quantity;
    }

    /**
     * The bulk quote this line was bought on (its price is the quoted one).
     *
     * @return BelongsTo<QuoteRequest, $this>
     */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }
}
