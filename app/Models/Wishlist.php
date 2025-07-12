<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if a product is in a user's wishlist
     */
    public static function isInWishlist(int $userId, int $productId): bool
    {
        return static::where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Get wishlist items for a user
     */
    public static function getUserWishlist(int $userId)
    {
        return static::where('user_id', $userId)
            ->with('product')
            ->get();
    }

    /**
     * Add a product to user's wishlist (with duplicate check)
     */
    public static function addToWishlist(int $userId, int $productId): array
    {
        // Check if already exists
        if (static::isInWishlist($userId, $productId)) {
            return ['success' => false, 'message' => 'Product is already in your wishlist.'];
        }

        try {
            static::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);

            return ['success' => true, 'message' => 'Product added to wishlist successfully.'];
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation
            if ($e->getCode() == 23000) {
                return ['success' => false, 'message' => 'Product is already in your wishlist.'];
            }
            
            return ['success' => false, 'message' => 'Failed to add product to wishlist.'];
        }
    }

    /**
     * Remove a product from user's wishlist
     */
    public static function removeFromWishlist(int $userId, int $productId): bool
    {
        return static::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete() > 0;
    }
}
