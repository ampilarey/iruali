<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Get or create a cart for the current user or session
     */
    public function getOrCreateCart(): Cart
    {
        return $this->currentCart() ?? Cart::create(Auth::check()
            ? ['user_id' => Auth::id(), 'session_id' => $this->guestToken(), 'status' => 'active']
            : ['session_id' => $this->guestToken(), 'status' => 'active']);
    }

    /**
     * The shopper's active cart, without creating one.
     */
    public function currentCart(): ?Cart
    {
        if (Auth::check()) {
            return Cart::where('user_id', Auth::id())->where('status', 'active')->latest('id')->first();
        }

        $token = Session::get('cart_token');

        return $token ? Cart::whereNull('user_id')->where('session_id', $token)->where('status', 'active')->first() : null;
    }

    /**
     * Guests' carts are keyed by a token kept in the session, so the cart survives
     * the session id changing when they sign in.
     */
    public function guestToken(): string
    {
        if (! Session::has('cart_token')) {
            Session::put('cart_token', Str::random(40));
        }

        return Session::get('cart_token');
    }

    /**
     * Move what a guest put in the cart into the account's cart when they sign in.
     */
    public function mergeGuestCart(User $user): void
    {
        $token = Session::get('cart_token');
        if (! $token) {
            return;
        }

        $guest = Cart::whereNull('user_id')->where('session_id', $token)->where('status', 'active')->with('items')->first();
        if (! $guest || $guest->items->isEmpty()) {
            return;
        }

        $cart = Cart::where('user_id', $user->id)->where('status', 'active')->latest('id')->first();
        if (! $cart) {
            $guest->update(['user_id' => $user->id]);

            return;
        }

        foreach ($guest->items as $item) {
            $existing = $cart->items()->where('product_id', $item->product_id)->where('product_variant_id', $item->product_variant_id)
                ->whereNull('quote_request_id')->first(); // a quoted line keeps its own quantity
            $stock = $item->availableStock(); // the variant's stock when the line has one
            $existing
                ? $existing->update(['quantity' => max(1, min($existing->quantity + $item->quantity, $stock, 999))])
                : $item->update(['cart_id' => $cart->id, 'quantity' => max(1, min($item->quantity, $stock, 999))]);
        }

        $guest->items()->delete();
        $guest->update(['status' => 'abandoned']);
    }

    /**
     * Number of units in the shopper's cart (for the header badge).
     */
    public function count(): int
    {
        $cart = $this->currentCart();

        return $cart ? (int) $cart->items()->sum('quantity') : 0;
    }

    /**
     * Add a product to cart
     */
    public function addToCart(int $productId, int $quantity, ?int $variantId = null): bool
    {
        $cart = $this->getOrCreateCart();
        $product = Product::findOrFail($productId);
        // A shop on holiday takes no new orders: its products stay listed, carts keep what is already in them
        if ($product->seller?->isOnHoliday()) {
            throw new \App\Exceptions\ShopOnHolidayException($product);
        }
        // Listed before it goes on sale: nothing can be ordered yet (ComingSoonService)
        if ($product->isComingSoon()) {
            throw new \App\Exceptions\ProductComingSoonException($product);
        }
        $variant = $variantId ? ProductVariant::where('product_id', $product->id)->findOrFail($variantId) : null;

        // Check if product (and variant) is already in cart
        $existingItem = $cart->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->whereNull('quote_request_id') // a quoted line (bulk quote) stays as quoted; more units are a line of their own
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $quantity,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $variant ? $variant->setRelation('product', $product)->effectivePrice() : $product->final_price,
            ]);
        }

        return true;
    }

    /**
     * Which variant a shopper is buying, if the product is sold in variants.
     *
     * @return array{0: ?ProductVariant, 1: ?string} the variant (null for a plain product) and an error message
     */
    public function resolveVariant(Product $product, ?int $variantId): array
    {
        if (! $product->has_variants) {
            return [null, null];
        }

        if (! $variantId) {
            return [null, __('Please choose an option (size, colour...) first.')];
        }

        $variant = ProductVariant::where('product_id', $product->id)->find($variantId);
        if (! $variant || ! $variant->is_active) {
            return [null, __('This option is not available.')];
        }

        return [$variant->setRelation('product', $product), null];
    }

    /**
     * Units that can be sold of a product, or of one of its variants: its stock, or when it is sold
     * out and the shop takes pre-orders for it, the pre-order units still open (PreorderService).
     */
    public function availableStock(Product $product, ?ProductVariant $variant): int
    {
        $stock = $variant ? (int) $variant->stock_quantity : (int) $product->stock_quantity;

        return $stock > 0 || ($product->has_variants && ! $variant) ? $stock : app(PreorderService::class)->unitsLeft($product, $variant);
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItem(CartItem $item, int $quantity): bool
    {
        // A quoted line's quantity is the quote's, and an item request's the request's: they can only be removed
        if ($item->quote_request_id !== null || $item->product?->item_request_id !== null) {
            return false;
        }

        $item->update(['quantity' => $quantity]);

        return true;
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(CartItem $item): bool
    {
        $item->delete();

        return true;
    }

    /**
     * Clear all items from cart
     */
    public function clearCart(): bool
    {
        $cart = $this->getOrCreateCart();
        $cart->items()->delete();

        return true;
    }

    /**
     * Check if cart is empty
     */
    public function isCartEmpty(Cart $cart): bool
    {
        return $cart->items->count() === 0;
    }

    /**
     * Get cart summary for display
     */
    public function getCartSummary(Cart $cart): array
    {
        $subtotal = (float) $cart->total;
        $pointsDiscount = (float) app(DiscountService::class)->redeemedPoints($cart);
        // The same sums checkout uses: the shops' own discounts first (multi-buy offers, shop codes),
        // then the iruali voucher on what is left (see ShopDiscountService and DiscountService)
        $discounts = app(DiscountService::class)->calculateTotalDiscount($cart);
        $shop = $discounts['shop'];

        return [
            'item_count' => $cart->item_count,
            'subtotal' => $subtotal,
            'voucher_discount' => $discounts['voucher']['amount'],
            'points_discount' => $pointsDiscount,
            'total' => round(max(0, $subtotal - $shop['amount'] - $discounts['voucher']['amount'] - $pointsDiscount), 2),
            'voucher' => $discounts['voucher']['voucher'],
            'voucher_error' => $discounts['voucher']['error'] ?? null,
            'shop_discount' => $shop['amount'],
            'multibuy_discount' => $shop['multibuy_discount'],
            'shop_code_discount' => $shop['code_discount'],
            'shop_deals' => $shop,
        ];
    }
}
