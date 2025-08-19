<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\DiscountService;
use App\Http\Resources\CartResource;
use App\Services\NotificationService;
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
        $cart->load('items.product');
        
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
            'quantity' => 'required|integer|min:1'
        ]);

        $product = Product::find($request->product_id);
        $this->cartService->addToCart(
            $request->product_id,
            $request->quantity
        );

        NotificationService::addedToCart($product->name);

        return redirect()->route('cart');
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
            'cart' => new CartResource($item->cart),
        ]);
    }

    public function remove(CartItem $item)
    {
        $productName = $item->product->name;
        $this->cartService->removeFromCart($item);

        NotificationService::removedFromCart($productName);

        return redirect()->route('cart');
    }

    public function clear()
    {
        $this->cartService->clearCart();

        NotificationService::success('Cart cleared successfully!');

        return redirect()->route('cart');
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
        NotificationService::voucherApplied($request->voucher_code);
        return back();
    }

    public function removeVoucher()
    {
        $this->discountService->removeVoucher();
        NotificationService::voucherRemoved();
        return back();
    }

}
