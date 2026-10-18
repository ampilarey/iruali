<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post for iruali's social channels (Admin → Social posts): written by staff or drafted when a
 * product goes live, approved by an admin, then posted (social:publish).
 */
class SocialPost extends Model
{
    public const CHANNELS = ['facebook' => 'Facebook page', 'instagram' => 'Instagram', 'telegram' => 'Telegram channel'];

    protected $fillable = ['kind', 'product_id', 'message', 'link', 'image_url', 'channels', 'status', 'scheduled_at', 'created_by'];

    protected $casts = ['channels' => 'array', 'results' => 'array', 'scheduled_at' => 'datetime', 'posted_at' => 'datetime'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A draft for a product that just went live.
     */
    public static function draftForProduct(Product $product): self
    {
        $product->loadMissing('mainImage');
        $image = $product->mainImage?->url;

        return static::create([
            'kind' => 'product', 'product_id' => $product->id,
            'message' => 'New on iruali: '.$product->getTranslation('name', 'en').' – '.\App\Support\Money::format((float) $product->final_price),
            'link' => route('products.show', $product),
            'image_url' => $image ? (str_starts_with($image, 'http') ? $image : url($image)) : null,
            'channels' => array_keys(\App\Services\SocialPublisher::configuredChannels()),
            'status' => 'pending',
        ]);
    }
}
