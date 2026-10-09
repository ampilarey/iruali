<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'reviewer_name',
        'reviewer_email',
        'rating',
        'title',
        'comment',
        'status',
        'is_approved',
        'verified_purchase',
        'helpful_count',
        'seller_reply',
        'seller_replied_at',
        'seller_reply_user_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
        'verified_purchase' => 'boolean',
        'status' => 'string',
        'seller_replied_at' => 'datetime',
    ];

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

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function getStarsAttribute()
    {
        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Photos the customer attached (up to three).
     *
     * @return HasMany<ReviewPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ReviewPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The shop user who wrote the reply.
     *
     * @return BelongsTo<User, $this>
     */
    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_reply_user_id');
    }

    public function scopeWithPhotos($query)
    {
        return $query->whereHas('photos');
    }

    public function hasReply(): bool
    {
        return filled($this->seller_reply);
    }

    /**
     * Can this user reply on behalf of the shop? Only the product's seller: its owner, or its staff
     * whose role may reply to reviews.
     */
    public function canBeRepliedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        $product = $this->relationLoaded('product') ? $this->product : Product::withTrashed()->find($this->product_id);

        return $product && $product->seller_id
            && ((int) $product->seller_id === (int) $user->id || \App\Support\CurrentShop::worksFor($user, (int) $product->seller_id, 'seller.reviews.reply'));
    }

    /**
     * Shop name shown on the reply: the product's shop (the reply may have been written by one of its staff).
     */
    public function replyShopName(): string
    {
        $seller = ($this->product ?? Product::withTrashed()->find($this->product_id))->seller ?? $this->replier;

        return $seller ? ($seller->business_name ?: $seller->name) : 'iruali';
    }

    /**
     * Photos (and their files) go with the review.
     */
    protected static function booted(): void
    {
        static::deleting(function (ProductReview $review) {
            $review->photos()->get()->each->delete();
        });
    }
}
