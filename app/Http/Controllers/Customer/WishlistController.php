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
        $wishlistItems = Wishlist::where('user_id', Auth::id())
            ->with('product.mainImage')
            ->get();

        return view('wishlist.index', compact('wishlistItems'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $userId = Auth::id();
        $productId = $request->product_id;

        $product = Product::find($productId);
        $result = Wishlist::addToWishlist($userId, $productId);

        if ($result['success']) {
            NotificationService::addedToWishlist($product->name);
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

        $productName = $wishlistItem->product->name;
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
