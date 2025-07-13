<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'meta_description'
    ];

    protected $casts = [
        'status' => 'string',
        'name' => 'array',
        'description' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent()
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
