<?php

namespace App\Models;

use App\Services\DeliveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * An inhabited island (database/data/islands.json, loaded by a migration): its name in English and
 * Dhivehi, its atoll in both, and its map position for "Use my location". Admins switch islands on
 * and off in Admin → Islands; switched-off islands are left out of every picker.
 */
class Island extends Model
{
    use HasTranslations;

    public $translatable = ['name'];

    protected $fillable = [
        'name', 'atoll', 'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'salat_id' => 'integer',
    ];

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_island')
            ->withPivot('stock_quantity', 'reorder_point', 'is_active')
            ->withTimestamps();
    }

    /**
     * Shops whose home island this is.
     *
     * @return HasMany<User, $this>
     */
    public function homeShops(): HasMany
    {
        return $this->hasMany(User::class, 'home_island_id');
    }

    public function scopeActive($query)
    {
        return $query->where('islands.is_active', true);
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

    /** The English name, which decides the delivery zone and goes on orders. */
    public function englishName(): string
    {
        return (string) ($this->getTranslation('name', 'en', false) ?: $this->getTranslation('name', config('app.fallback_locale'), false));
    }

    /** The atoll in the page's language: "Kaafu" or "ކ". */
    public function localizedAtoll(): string
    {
        return app()->getLocale() === 'dv' && filled($this->atoll_dv) ? (string) $this->atoll_dv : (string) $this->atoll;
    }

    /** "Malé, Kaafu" (or in Dhivehi). */
    public function label(): string
    {
        return collect([$this->localized_name, $this->localizedAtoll()])->filter()->join(', ');
    }

    /** Malé, Hulhumalé, Villimalé (and the other names of Greater Malé). */
    public function isGreaterMale(): bool
    {
        return app(DeliveryService::class)->zoneForIsland($this) === DeliveryService::AREA_GREATER_MALE;
    }

    public function hasPosition(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
