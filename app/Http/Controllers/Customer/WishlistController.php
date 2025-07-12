<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
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

        $result = Wishlist::addToWishlist($userId, $productId);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        } else {
            return back()->with('info', $result['message']);
        }
    }

    public function remove($id)
    {
        $wishlistItem = Wishlist::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $wishlistItem->delete();

        return back()->with('success', 'Product removed from wishlist.');
    }

    public function clear()
    {
        Wishlist::where('user_id', Auth::id())->delete();

        return back()->with('success', 'Wishlist cleared successfully.');
    }
}
