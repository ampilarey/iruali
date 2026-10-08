<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'is_main',
        'sort_order',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The best URL for this image at a given display width (a WebP copy when one exists).
     */
    public function variant(int $width): string
    {
        return \App\Support\ImageVariants::url($this->url, $width);
    }

    public function srcset(): string
    {
        return implode(', ', array_map(fn ($w) => $this->variant($w).' '.$w.'w', \App\Support\ImageVariants::WIDTHS));
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
