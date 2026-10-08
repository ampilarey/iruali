<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An old name (match key) or old web address (slug) of a brand, kept after a rename or a merge:
 * a shop typing the old name gets the brand it now belongs to, and the old address redirects.
 */
class BrandAlias extends Model
{
    protected $fillable = ['brand_id', 'key', 'slug'];

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
