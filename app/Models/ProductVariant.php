<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ProductVariant extends Model
{
    use HasTranslations;

    public $translatable = ['name'];

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'sku',
        'price_adjustment',
        'stock_quantity',
        'image',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'price_adjustment' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

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
