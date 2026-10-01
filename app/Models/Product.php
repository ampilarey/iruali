<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    public $translatable = ['name', 'description'];

    protected $fillable = [
        'name',
        'description',
        'search_text',
        'sku',
        'slug',
        'category_id',
        'seller_id',
        'price',
        'compare_price',
        'stock_quantity',
        'has_variants',
        'reorder_point',
        'is_active',
        'approved_at',
        'is_featured',
        'is_sponsored',
        'sponsored_until',
        'main_image',
        'images',
        'tags',
        'brand',
        'model',
        'weight',
        'dimensions',
        'requires_shipping',
        'is_digital',
        'digital_file',
        'wholesale_pricing',
        'meta_title',
        'meta_description',
        'flash_sale_ends_at',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'images' => 'array',
        'tags' => 'array',
        'wholesale_pricing' => 'array',
        'is_active' => 'boolean',
        'has_variants' => 'boolean',
        'is_featured' => 'boolean',
        'is_sponsored' => 'boolean',
        'sponsored_until' => 'datetime',
        'approved_at' => 'datetime',
        'requires_shipping' => 'boolean',
        'is_digital' => 'boolean',
        'flash_sale_ends_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_main', true);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function islands()
    {
        return $this->belongsToMany(Island::class, 'product_island')
            ->withPivot('stock_quantity', 'reorder_point', 'is_active')
            ->withTimestamps();
    }

    public function getFinalPriceAttribute()
    {
        // A live campaign the shop joined (and an admin approved) beats the list price
        return $this->campaignPrice() ?? $this->sale_price ?? $this->price;
    }

    /**
     * The "was" price shown struck through: sellers set it as compare_price. During a campaign the
     * list price itself is the "was" price when no higher compare price is set.
     */
    public function getWasPriceAttribute(): ?float
    {
        $was = (float) ($this->compare_price ?? 0);
        if ($this->campaignPrice() !== null) {
            $was = max($was, (float) ($this->sale_price ?? $this->price));
        }

        return $was > (float) $this->final_price ? $was : null;
    }

    public function getSavingsAttribute(): float
    {
        return $this->was_price ? round($this->was_price - (float) $this->final_price, 2) : 0.0;
    }

    public function getDiscountPercentageAttribute()
    {
        return $this->was_price ? (int) round($this->savings / $this->was_price * 100) : 0;
    }

    public function getIsOnSaleAttribute()
    {
        return $this->was_price !== null;
    }

    /**
     * When a marked-down price ends, if the seller set an end time (drives the deal countdown).
     */
    public function getDealEndsAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->is_on_sale && $this->flash_sale_ends_at && $this->flash_sale_ends_at->isFuture() ? $this->flash_sale_ends_at : null;
    }

    public function scopeOnSale($query)
    {
        return $query->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price');
    }

    public function getIsInStockAttribute()
    {
        return $this->stock_quantity > 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopePending($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRejected($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Get the localized name with fallback
     */
    public function getLocalizedNameAttribute()
    {
        return $this->getTranslation('name', app()->getLocale(), false) ?: $this->getTranslation('name', config('app.fallback_locale'), false);
    }

    /**
     * Get the localized description with fallback
     */
    public function getLocalizedDescriptionAttribute()
    {
        return $this->getTranslation('description', app()->getLocale(), false) ?: $this->getTranslation('description', config('app.fallback_locale'), false);
    }

    /**
     * Get all available translations for a field
     */
    public function getAllTranslations($field)
    {
        return $this->getTranslations($field);
    }

    /**
     * Check if a translation exists for a field
     */
    public function hasTranslation($field, $locale = null)
    {
        $locale = $locale ?: app()->getLocale();

        return $this->hasTranslation($field, $locale);
    }

    /**
     * Get the route key for the model.
     * Use slug instead of ID for URLs
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Generate a unique slug for the product
     */
    public function generateSlug()
    {
        $baseSlug = \Illuminate\Support\Str::slug($this->name['en'] ?? $this->name);
        $slug = $baseSlug;
        $counter = 1;

        // Check if slug already exists
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Boot method to automatically generate slug
     */
    /**
     * Everything a search may match, lower-cased: both names, brand, model, SKU and the start of the description.
     */
    public function buildSearchText(): string
    {
        $names = $this->getTranslations('name');
        $descriptions = $this->getTranslations('description');
        $parts = array_merge(array_values($names), [$this->brand, $this->model, $this->sku], array_map(fn ($d) => mb_substr(strip_tags((string) $d), 0, 300), array_values($descriptions)));

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts, fn ($p) => filled($p))))));
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = $product->generateSlug();
            }
        });

        // One plain column to search instead of five JSON casts per row
        static::saving(function ($product) {
            if ($product->isDirty(['name', 'description', 'brand', 'model', 'sku']) || $product->search_text === null) {
                $product->search_text = $product->buildSearchText();
            }
        });

        // Tell shoppers who asked to be notified when a sold-out product is back
        // A product a shop deletes must not stay in anyone's cart or saved list
        static::deleting(function ($product) {
            \App\Models\CartItem::where('product_id', $product->id)->delete();
            if (class_exists(\App\Models\SavedItem::class)) {
                \App\Models\SavedItem::where('product_id', $product->id)->delete();
            }
        });

        static::updated(function ($product) {
            if ($product->wasChanged('stock_quantity') && (int) $product->getOriginal('stock_quantity') <= 0 && (int) $product->stock_quantity > 0 && $product->is_active) {
                app(\App\Services\StockAlertService::class)->productRestocked($product);
            }
        });

        static::updating(function ($product) {
            // Regenerate slug if name has changed
            if ($product->isDirty('name')) {
                $product->slug = $product->generateSlug();
            }
        });
    }

    /**
     * Scope to include only soft deleted products
     */
    public function scopeOnlyTrashed($query)
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope to include both active and soft deleted products
     */
    public function scopeWithTrashed($query)
    {
        return $query->withTrashed();
    }

    /**
     * Check if product is soft deleted
     */
    public function isTrashed(): bool
    {
        return $this->trashed();
    }

    /**
     * Restore a soft deleted product
     */
    public function restoreProduct(): bool
    {
        return $this->restore();
    }

    /**
     * Force delete a product (permanently remove)
     */
    public function forceDeleteProduct(): bool
    {
        return $this->forceDelete();
    }

    /**
     * Approved by an admin at some point: the shop may switch it off and on without re-approval.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null || $this->is_active;
    }

    // ---- Variants -------------------------------------------------------------------------

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Units that can be sold: the sum of the active variants' stock when the product has variants,
     * else its own stock column.
     */
    public function effectiveStock(): int
    {
        if (! $this->has_variants) {
            return (int) $this->stock_quantity;
        }

        $variants = $this->relationLoaded('variants') ? $this->variants->where('is_active', true) : $this->activeVariants()->get();

        return (int) $variants->sum('stock_quantity');
    }

    /**
     * Keep the stock column equal to the active variants' total, so listings, "in stock" filters
     * and sort orders need no extra query. Called whenever a variant is saved or deleted.
     */
    public function syncStockFromVariants(): void
    {
        if (! $this->has_variants) {
            return;
        }

        $total = (int) $this->variants()->where('is_active', true)->sum('stock_quantity');
        if ((int) $this->stock_quantity !== $total) {
            $this->unsetRelation('variants');
            $this->update(['stock_quantity' => $total]);
        }
    }

    /**
     * Running low: any active variant at or under its threshold, or the product at its reorder point.
     */
    public function isLowStock(): bool
    {
        if ($this->has_variants) {
            $variants = $this->relationLoaded('variants') ? $this->variants->where('is_active', true) : $this->activeVariants()->get();

            return $variants->contains(fn ($v) => $v->isLowStock());
        }

        return (int) $this->stock_quantity <= (int) ($this->reorder_point ?? 0);
    }

    /**
     * [min, max] of the active variants' prices, or null when the product has no variants.
     *
     * @return array{0: float, 1: float}|null
     */
    public function variantPriceRange(): ?array
    {
        if (! $this->has_variants) {
            return null;
        }

        $variants = $this->relationLoaded('variants') ? $this->variants->where('is_active', true) : $this->activeVariants()->get();
        if ($variants->isEmpty()) {
            return null;
        }

        $prices = $variants->map(fn ($v) => $v->setRelation('product', $this)->effectivePrice());

        return [(float) $prices->min(), (float) $prices->max()];
    }

    /**
     * The lowest variant price when variants are priced differently (cards show "from MVR x").
     */
    public function getFromPriceAttribute(): ?float
    {
        $range = $this->variantPriceRange();

        return $range && $range[0] < $range[1] ? $range[0] : null;
    }

    // ---- Campaigns ------------------------------------------------------------------------

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_products')
            ->withPivot(['seller_id', 'discount_percent', 'approved_at'])
            ->withTimestamps();
    }

    public function campaignParticipations(): HasMany
    {
        return $this->hasMany(CampaignProduct::class);
    }

    /**
     * The biggest discount (percent) from a live campaign this product is approved in, or 0.
     */
    public function campaignDiscountPercent(): float
    {
        return (float) (Campaign::liveDiscounts()[(int) $this->id] ?? 0);
    }

    /**
     * The price after the live campaign discount, or null when no campaign applies.
     */
    public function campaignPrice(): ?float
    {
        $pct = $this->campaignDiscountPercent();
        if ($pct <= 0) {
            return null;
        }

        return $this->applyCampaignDiscount((float) ($this->sale_price ?? $this->price));
    }

    /**
     * Take the live campaign discount off a price (used for variants priced on their own too).
     */
    public function applyCampaignDiscount(float $price): float
    {
        $pct = $this->campaignDiscountPercent();

        return $pct > 0 ? round($price * (1 - $pct / 100), 2) : round($price, 2);
    }
}
