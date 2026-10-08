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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
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
        $name = (string) ($this->product?->name ?? __('Product'));

        return $this->variant_name ? $name.' – '.$this->variant_name : $name;
    }
}
