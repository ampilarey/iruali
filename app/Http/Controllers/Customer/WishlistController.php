<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index()
    {
        // A product deleted since it was saved (it is in the bin) is left out
        $wishlistItems = Wishlist::where('user_id', Auth::id())
            ->whereHas('product')
            ->with(['product.mainImage', 'product.variants', 'variant'])
            ->get();

        return view('wishlist.index', compact('wishlistItems'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $userId = Auth::id();
        $productId = $request->product_id;

        // "exists" also matches a product in the bin: one deleted since the page was loaded
        $product = Product::find($productId);
        if (! $product) {
            NotificationService::error(__('This product is not available.'));

            return redirect()->route('wishlist');
        }

        $result = Wishlist::addToWishlist($userId, $productId);

        if ($result['success']) {
            NotificationService::addedToWishlist($product->localized_name);
        } else {
            NotificationService::info($result['message']);
        }

        return redirect()->route('wishlist');
    }

    public function remove($id)
    {
        $wishlistItem = Wishlist::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $productName = $wishlistItem->product->localized_name ?? __('Product');
        $wishlistItem->delete();

        NotificationService::removedFromWishlist($productName);

        return redirect()->route('wishlist');
    }

    public function clear()
    {
        Wishlist::where('user_id', Auth::id())->delete();

        NotificationService::success('Wishlist cleared successfully.');

        return redirect()->route('wishlist');
    }
}
