<?php

namespace App\Models;

use App\Support\ImageVariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo a customer attached to their review, stored on the public disk under reviews/.
 */
class ReviewPhoto extends Model
{
    protected $fillable = ['product_review_id', 'path', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk(ImageVariants::DISK)->url($this->path);
    }

    /**
     * The WebP copy at the given width when it exists, else the original.
     */
    public function variant(int $width): string
    {
        return ImageVariants::url($this->url, $width);
    }

    /**
     * The file and its WebP copies go with the row.
     */
    protected static function booted(): void
    {
        static::deleting(function (ReviewPhoto $photo) {
            ImageVariants::delete($photo->path);
            Storage::disk(ImageVariants::DISK)->delete($photo->path);
        });
    }
}
