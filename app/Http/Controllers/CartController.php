<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    protected $cartService;
    protected $discountService;

    public function __construct(CartService $cartService, DiscountService $discountService)
    {
        $this->cartService = $cartService;
        $this->discountService = $discountService;
    }

    public function index()
    {
        $cart = $this->cartService->getOrCreateCart();
        $cart->load('items.product.mainImage');
        
        $cartSummary = $this->cartService->getCartSummary($cart);
        
        return view('cart.index', [
            'cart' => $cart,
            'voucher' => $cartSummary['voucher'],
            'discount' => $cartSummary['voucher_discount']
        ]);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id'
        ]);

        $this->cartService->addToCart(
            $request->product_id,
            $request->quantity,
            $request->variant_id
        );

        return redirect()->route('cart.index')
            ->with('success', 'Product added to cart successfully!');
    }

    public function update(Request $request, CartItem $item)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $this->cartService->updateCartItem($item, $request->quantity);

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully',
            'cart_total' => $item->cart->total,
            'item_count' => $item->cart->item_count
        ]);
    }

    public function remove(CartItem $item)
    {
        $this->cartService->removeFromCart($item);

        return redirect()->route('cart.index')
            ->with('success', 'Item removed from cart successfully!');
    }

    public function clear()
    {
        $this->cartService->clearCart();

        return redirect()->route('cart.index')
            ->with('success', 'Cart cleared successfully!');
    }

    public function applyVoucher(Request $request)
    {
        $request->validate(['voucher_code' => 'required|string']);
        
        $cart = $this->cartService->getOrCreateCart();
        $result = $this->discountService->validateVoucher($request->voucher_code, $cart);
        
        if (!$result['valid']) {
            return back()->withErrors(['voucher_code' => $result['message']]);
        }
        
        $this->discountService->applyVoucher($request->voucher_code);
        return back()->with('success', __('Voucher applied!'));
    }

    public function removeVoucher()
    {
        $this->discountService->removeVoucher();
        return back()->with('success', __('Voucher removed.'));
    }

}
