<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory, HasTranslations;

    public $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    protected $fillable = [
        'name',
        'description',
        'slug',
        'parent_id',
        'image',
        'status',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'status' => 'string',
        'name' => 'array',
        'description' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
    ];

    /** The header menu's departments, cached a few minutes; dropped whenever a category changes. */
    public const NAV_CACHE_KEY = 'nav-departments';

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::NAV_CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Active top-level categories for the header menu.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    public static function navDepartments(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(self::NAV_CACHE_KEY, now()->addMinutes(10), fn () => static::active()->root()->get());
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function getFullPathAttribute()
    {
        $path = [$this->getTranslation('name', app()->getLocale(), false) ?: $this->getTranslation('name', config('app.fallback_locale'), false)];
        $parent = $this->parent;

        while ($parent) {
            $parentName = $parent->getTranslation('name', app()->getLocale(), false) ?: $parent->getTranslation('name', config('app.fallback_locale'), false);
            array_unshift($path, $parentName);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
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
}
