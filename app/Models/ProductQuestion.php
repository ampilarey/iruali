<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductQuestion extends Model
{
    protected $fillable = ['product_id', 'user_id', 'question', 'answer', 'answered_by', 'answered_at'];

    protected $casts = ['answered_at' => 'datetime', 'answered_as_shop' => 'boolean'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * Was the answer given for the shop (its owner or one of its staff) rather than by iruali?
     * Answers from before answered_as_shop existed count as the shop's when its owner wrote them.
     */
    public function answeredByShop(?int $shopId = null): bool
    {
        if ($this->answered_by === null) {
            return false;
        }

        return $this->answered_as_shop ?? (int) $this->answered_by === (int) ($shopId ?? $this->product?->seller_id);
    }
}
