<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Island extends Model
{
    use HasTranslations;

    public $translatable = ['name'];

    protected $fillable = [
        'name', 'atoll', 'is_active'
    ];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_island')
            ->withPivot('stock_quantity', 'reorder_point', 'is_active')
            ->withTimestamps();
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