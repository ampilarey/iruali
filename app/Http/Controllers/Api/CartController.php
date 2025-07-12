<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends BaseController
{
    protected $cartService;
    protected $discountService;

    public function __construct(CartService $cartService, DiscountService $discountService)
    {
        $this->cartService = $cartService;
        $this->discountService = $discountService;
    }

    /**
     * Get user's cart
     */
    public function index()
    {
        $user = Auth::user();
        $cart = $this->cartService->getOrCreateCart($user);

        $cart->load(['items.product.mainImage']);

        $cartData = [
            'id' => $cart->id,
            'items' => $cart->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'slug' => $item->product->slug,
                        'price' => $item->product->price,
                        'final_price' => $item->product->final_price,
                        'main_image' => $item->product->main_image,
                        'stock_quantity' => $item->product->stock_quantity,
                        'is_in_stock' => $item->product->is_in_stock,
                    ],
                ];
            }),
            'total_items' => $cart->item_count,
            'subtotal' => $cart->total,
            'total' => $cart->total,
            'voucher_code' => session('voucher_code'),
            'voucher_discount' => session('voucher_discount', 0),
            'points_redeemed' => session('points_redeemed', 0),
            'points_redeemed_discount' => session('points_redeemed_discount', 0),
        ];

        return $this->sendResponse($cartData, 'Cart retrieved successfully');
    }

    /**
     * Add item to cart
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        // Check stock availability
        if ($product->stock_quantity < $request->quantity) {
            return $this->sendError('Insufficient stock available');
        }

        $result = $this->cartService->addToCart($user, $product, $request->quantity);

        if (!$result['success']) {
            return $this->sendError($result['message']);
        }

        return $this->sendResponse($result['cart'], 'Item added to cart successfully');
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, CartItem $item)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();

        // Ensure the cart item belongs to the authenticated user
        if ($item->cart->user_id !== $user->id) {
            return $this->sendForbidden('Unauthorized access to cart item');
        }

        // Check stock availability
        if ($item->product->stock_quantity < $request->quantity) {
            return $this->sendError('Insufficient stock available');
        }

        $result = $this->cartService->updateCartItem($item, $request->quantity);

        if (!$result['success']) {
            return $this->sendError($result['message']);
        }

        return $this->sendResponse($result['cart'], 'Cart item updated successfully');
    }

    /**
     * Remove item from cart
     */
    public function remove(CartItem $item)
    {
        $user = Auth::user();

        // Ensure the cart item belongs to the authenticated user
        if ($item->cart->user_id !== $user->id) {
            return $this->sendForbidden('Unauthorized access to cart item');
        }

        $result = $this->cartService->removeFromCart($item);

        if (!$result['success']) {
            return $this->sendError($result['message']);
        }

        return $this->sendResponse($result['cart'], 'Item removed from cart successfully');
    }

    /**
     * Clear cart
     */
    public function clear()
    {
        $user = Auth::user();
        $result = $this->cartService->clearCart($user);

        if (!$result['success']) {
            return $this->sendError($result['message']);
        }

        return $this->sendResponse([], 'Cart cleared successfully');
    }

    /**
     * Apply voucher to cart
     */
    public function applyVoucher(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'voucher_code' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();
        $cart = $this->cartService->getOrCreateCart($user);

        $result = $this->discountService->applyVoucher($request->voucher_code, $cart);

        if (!$result['valid']) {
            return $this->sendError($result['message']);
        }

        return $this->sendResponse([
            'voucher_code' => $request->voucher_code,
            'discount_amount' => $result['amount'],
            'cart_total' => $result['new_total'],
        ], 'Voucher applied successfully');
    }

    /**
     * Remove voucher from cart
     */
    public function removeVoucher()
    {
        $user = Auth::user();
        $cart = $this->cartService->getOrCreateCart($user);

        $result = $this->discountService->removeVoucher($cart);

        return $this->sendResponse([
            'cart_total' => $result['new_total'],
        ], 'Voucher removed successfully');
    }
}
