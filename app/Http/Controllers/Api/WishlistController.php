<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\WishlistItemResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WishlistController extends BaseController
{
    /**
     * Get user's wishlist
     */
    public function index()
    {
        $user = Auth::user();
        // A product deleted since it was saved (it is in the bin) is left out
        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->whereHas('product')
            ->with(['product.mainImage', 'product.category', 'product.seller'])
            ->get();

        return $this->sendResponse([
            'items' => WishlistItemResource::collection($wishlistItems),
            'total_items' => $wishlistItems->count(),
        ], 'Wishlist retrieved successfully');
    }

    /**
     * Add product to wishlist
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Not a product in the bin (deleted, or taken off the site with the sample data)
            'product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')],
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
