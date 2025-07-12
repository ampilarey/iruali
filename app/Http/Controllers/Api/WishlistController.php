<?php

namespace App\Http\Controllers\Api;

use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WishlistController extends BaseController
{
    /**
     * Get user's wishlist
     */
    public function index()
    {
        $user = Auth::user();
        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->with(['product.mainImage', 'product.category', 'product.seller'])
            ->get();

        $wishlistData = $wishlistItems->map(function ($item) {
            return [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'description' => $item->product->description,
                    'price' => $item->product->price,
                    'final_price' => $item->product->final_price,
                    'compare_price' => $item->product->compare_price,
                    'sale_price' => $item->product->sale_price,
                    'discount_percentage' => $item->product->discount_percentage,
                    'is_on_sale' => $item->product->is_on_sale,
                    'stock_quantity' => $item->product->stock_quantity,
                    'is_in_stock' => $item->product->is_in_stock,
                    'sku' => $item->product->sku,
                    'slug' => $item->product->slug,
                    'main_image' => $item->product->main_image,
                    'images' => $item->product->images,
                    'category' => [
                        'id' => $item->product->category->id,
                        'name' => $item->product->category->name,
                        'slug' => $item->product->category->slug,
                    ],
                    'seller' => [
                        'id' => $item->product->seller->id,
                        'name' => $item->product->seller->name,
                    ],
                    'is_featured' => $item->product->is_featured,
                    'is_sponsored' => $item->product->is_sponsored,
                ],
                'added_at' => $item->created_at,
            ];
        });

        return $this->sendResponse([
            'items' => $wishlistData,
            'total_items' => $wishlistData->count(),
        ], 'Wishlist retrieved successfully');
    }

    /**
     * Add product to wishlist
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();
        $productId = $request->product_id;

        $result = Wishlist::addToWishlist($user->id, $productId);

        if ($result['success']) {
            return $this->sendResponse([], $result['message']);
        } else {
            return $this->sendError($result['message']);
        }
    }

    /**
     * Remove product from wishlist
     */
    public function remove(Product $product)
    {
        $user = Auth::user();
        $removed = Wishlist::removeFromWishlist($user->id, $product->id);

        if ($removed) {
            return $this->sendResponse([], 'Product removed from wishlist successfully');
        } else {
            return $this->sendNotFound('Product not found in wishlist');
        }
    }

    /**
     * Clear wishlist
     */
    public function clear()
    {
        $user = Auth::user();
        Wishlist::where('user_id', $user->id)->delete();

        return $this->sendResponse([], 'Wishlist cleared successfully');
    }
}
