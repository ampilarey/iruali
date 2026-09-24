<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\DiscountService;
use Illuminate\Http\JsonResponse;
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
        return $this->cartResponse($this->cartService->getOrCreateCart(), 'Cart retrieved successfully');
    }

    /**
     * Add item to cart
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $product = Product::findOrFail($request->product_id);

        if (! $product->is_active) {
            return $this->sendError('This product is not available');
        }

        $variantId = $request->product_variant_id;
        if ($variantId && ! ProductVariant::where('id', $variantId)->where('product_id', $product->id)->exists()) {
            return $this->sendValidationError(['product_variant_id' => ['The selected variant does not belong to this product.']]);
        }

        $cart = $this->cartService->getOrCreateCart();
        $alreadyInCart = (int) $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->sum('quantity');

        if ($product->stock_quantity < $alreadyInCart + $request->quantity) {
            return $this->sendError('Insufficient stock available');
        }

        $this->cartService->addToCart($product->id, (int) $request->quantity, $variantId ? (int) $variantId : null);

        return $this->cartResponse($cart, 'Item added to cart successfully', 201);
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, CartItem $item)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        if (! $this->ownsItem($item)) {
            return $this->sendForbidden('Unauthorized access to cart item');
        }

        if ($item->product->stock_quantity < $request->quantity) {
            return $this->sendError('Insufficient stock available');
        }

        $this->cartService->updateCartItem($item, (int) $request->quantity);

        return $this->cartResponse($item->cart, 'Cart item updated successfully');
    }

    /**
     * Remove item from cart
     */
    public function remove(CartItem $item)
    {
        if (! $this->ownsItem($item)) {
            return $this->sendForbidden('Unauthorized access to cart item');
        }

        $cart = $item->cart;
        $this->cartService->removeFromCart($item);

        return $this->cartResponse($cart, 'Item removed from cart successfully');
    }

    /**
     * Clear cart
     */
    public function clear()
    {
        $this->cartService->clearCart();

        $cart = $this->cartService->getOrCreateCart();
        $cart->update(['voucher_code' => null]);

        return $this->cartResponse($cart, 'Cart cleared successfully');
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

        $cart = $this->cartService->getOrCreateCart();

        if ($cart->items()->doesntExist()) {
            return $this->sendError('Your cart is empty');
        }

        $result = $this->discountService->validateVoucher($request->voucher_code, $cart);

        if (! $result['valid']) {
            return $this->sendError($result['message']);
        }

        $cart->update(['voucher_code' => $result['voucher']->code]);

        return $this->cartResponse($cart, 'Voucher applied successfully');
    }

    /**
     * Remove voucher from cart
     */
    public function removeVoucher()
    {
        $cart = $this->cartService->getOrCreateCart();
        $cart->update(['voucher_code' => null]);

        return $this->cartResponse($cart, 'Voucher removed successfully');
    }

    protected function ownsItem(CartItem $item): bool
    {
        $cart = $item->cart;

        return $cart
            && (int) $cart->user_id === (int) Auth::id()
            && $cart->status === 'active';
    }

    protected function cartResponse(Cart $cart, string $message, int $code = 200): JsonResponse
    {
        $cart = $cart->fresh(['items.product.mainImage']);

        return $this->sendResponse(new CartResource($cart), $message, $code);
    }
}
