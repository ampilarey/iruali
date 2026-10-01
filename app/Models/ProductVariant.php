<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ProductVariant extends Model
{
    use HasFactory, HasTranslations;

    public $translatable = ['name'];

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'attributes',
        'sku',
        'price',
        'price_adjustment',
        'stock_quantity',
        'low_stock_threshold',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'attributes' => 'array',
        'price' => 'decimal:2',
        'price_adjustment' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // The product's stock is the sum of its active variants' stock, so keep it in step
        static::saved(function (ProductVariant $variant) {
            if ($variant->wasChanged('stock_quantity') && (int) $variant->getOriginal('stock_quantity') <= 0 && $variant->stock_quantity > 0 && $variant->is_active) {
                app(\App\Services\StockAlertService::class)->variantRestocked($variant);
            }
            $variant->product?->syncStockFromVariants();
        });
        static::deleted(fn (ProductVariant $variant) => $variant->product?->syncStockFromVariants());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * What the customer pays: the variant's own price, or the product's price plus the adjustment.
     */
    public function effectivePrice(): float
    {
        if ($this->price !== null) {
            // A variant priced on its own still gets the product's live campaign discount
            return $this->product ? $this->product->applyCampaignDiscount((float) $this->price) : round((float) $this->price, 2);
        }

        return round((float) ($this->product?->final_price ?? 0) + (float) $this->price_adjustment, 2);
    }

    public function isInStock(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }

    /**
     * Low when at or under its own threshold, or the product's reorder point when it has none.
     */
    public function isLowStock(): bool
    {
        $threshold = $this->low_stock_threshold ?? $this->product?->reorder_point ?? 5;

        return $this->stock_quantity <= (int) $threshold;
    }

    /**
     * Had any sale: such a variant is deactivated instead of deleted, so orders keep their line.
     */
    public function hasOrderHistory(): bool
    {
        return $this->orderItems()->exists();
    }

    /**
     * "M / Blue" (the attribute values in order), falling back to the translated name or SKU.
     */
    public function displayName(): string
    {
        $attributes = $this->attributes_list;
        if ($attributes !== []) {
            return implode(' / ', array_values($attributes));
        }

        return (string) ($this->localized_name ?: $this->sku);
    }

    /**
     * "Size: M / Colour: Blue", with the option names translated when a translation exists.
     */
    public function displayNameWithKeys(): string
    {
        $attributes = $this->attributes_list;
        if ($attributes === []) {
            return $this->displayName();
        }

        return implode(' / ', array_map(fn ($k, $v) => __($k).': '.$v, array_keys($attributes), $attributes));
    }

    /**
     * The attributes as a flat string => string map (the column is nullable JSON).
     */
    public function getAttributesListAttribute(): array
    {
        $raw = $this->getAttribute('attributes');
        if (! is_array($raw)) {
            return [];
        }

        $list = [];
        foreach ($raw as $key => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $list[(string) $key] = (string) $value;
            }
        }

        return $list;
    }

    /**
     * Get the localized name with fallback
     */
    public function getLocalizedNameAttribute()
    {
        return $this->getTranslation('name', app()->getLocale(), false) ?: $this->getTranslation('name', config('app.fallback_locale'), false);
    }

    /**
     * Get all available translations for name
     */
    public function getAllNameTranslations()
    {
        return $this->getTranslations('name');
    }
}
