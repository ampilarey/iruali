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
        'sku',
        'slug',
        'category_id',
        'seller_id',
        'price',
        'compare_price',
        'stock_quantity',
        'reorder_point',
        'is_active',
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
        'is_featured' => 'boolean',
        'is_sponsored' => 'boolean',
        'sponsored_until' => 'datetime',
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
        return $this->belongsTo(User::class, 'user_id');
    }

    public function islands()
    {
        return $this->belongsToMany(Island::class, 'product_island')
            ->withPivot('stock_quantity', 'reorder_point', 'is_active')
            ->withTimestamps();
    }

    public function getFinalPriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }

    public function getDiscountPercentageAttribute()
    {
        if ($this->sale_price && $this->price > $this->sale_price) {
            return round((($this->price - $this->sale_price) / $this->price) * 100);
        }
        return 0;
    }

    public function getIsOnSaleAttribute()
    {
        return $this->sale_price && $this->sale_price < $this->price;
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
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Boot method to automatically generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = $product->generateSlug();
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
}
