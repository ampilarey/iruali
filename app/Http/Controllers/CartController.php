<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cart = $this->getOrCreateCart();
        $cart->load('items.product.mainImage');
        $voucher = null;
        $discount = 0;
        if (session('voucher_code')) {
            $voucher = \App\Models\Voucher::where('code', session('voucher_code'))->where('is_active', true)->first();
            if ($voucher) {
                $discount = $this->calculateDiscount($cart, $voucher);
            }
        }
        return view('cart.index', compact('cart', 'voucher', 'discount'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id'
        ]);

        $cart = $this->getOrCreateCart();
        $product = Product::findOrFail($request->product_id);

        // Check if product is already in cart
        $existingItem = $cart->items()
            ->where('product_id', $request->product_id)
            ->where('product_variant_id', $request->variant_id)
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $request->quantity
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $request->product_id,
                'product_variant_id' => $request->variant_id,
                'quantity' => $request->quantity,
                'price' => $product->final_price
            ]);
        }

        return redirect()->route('cart.index')
            ->with('success', 'Product added to cart successfully!');
    }

    public function update(Request $request, CartItem $item)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $item->update(['quantity' => $request->quantity]);

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully',
            'cart_total' => $item->cart->total,
            'item_count' => $item->cart->item_count
        ]);
    }

    public function remove(CartItem $item)
    {
        $item->delete();

        return redirect()->route('cart.index')
            ->with('success', 'Item removed from cart successfully!');
    }

    public function clear()
    {
        $cart = $this->getOrCreateCart();
        $cart->items()->delete();

        return redirect()->route('cart.index')
            ->with('success', 'Cart cleared successfully!');
    }

    public function applyVoucher(Request $request)
    {
        $request->validate(['voucher_code' => 'required|string']);
        $voucher = \App\Models\Voucher::where('code', $request->voucher_code)->where('is_active', true)->first();
        $cart = $this->getOrCreateCart();
        if (!$voucher) {
            return back()->withErrors(['voucher_code' => __('Invalid or inactive voucher.')]);
        }
        if ($voucher->valid_from && now()->lt($voucher->valid_from)) {
            return back()->withErrors(['voucher_code' => __('Voucher not yet valid.')]);
        }
        if ($voucher->valid_until && now()->gt($voucher->valid_until)) {
            return back()->withErrors(['voucher_code' => __('Voucher expired.')]);
        }
        if ($voucher->max_uses && $voucher->used_count >= $voucher->max_uses) {
            return back()->withErrors(['voucher_code' => __('Voucher usage limit reached.')]);
        }
        if ($voucher->min_order && $cart->total < $voucher->min_order) {
            return back()->withErrors(['voucher_code' => __('Order does not meet minimum amount for this voucher.')]);
        }
        session(['voucher_code' => $voucher->code]);
        return back()->with('success', __('Voucher applied!'));
    }

    public function removeVoucher()
    {
        session()->forget('voucher_code');
        return back()->with('success', __('Voucher removed.'));
    }

    private function calculateDiscount($cart, $voucher)
    {
        if ($voucher->type === 'percent') {
            return round($cart->total * ($voucher->amount / 100), 2);
        }
        return min($voucher->amount, $cart->total);
    }

    private function getOrCreateCart()
    {
        if (Auth::check()) {
            $cart = Cart::where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => Auth::id(),
                    'status' => 'active'
                ]);
            }
        } else {
            $sessionId = session()->getId();
            $cart = Cart::where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                $cart = Cart::create([
                    'session_id' => $sessionId,
                    'status' => 'active'
                ]);
            }
        }

        return $cart;
    }
}
