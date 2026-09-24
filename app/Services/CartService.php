<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
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
            $existing = $cart->items()->where('product_id', $item->product_id)->where('product_variant_id', $item->product_variant_id)->first();
            $existing
                ? $existing->update(['quantity' => $existing->quantity + $item->quantity])
                : $item->update(['cart_id' => $cart->id]);
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

        // Check if product (and variant) is already in cart
        $existingItem = $cart->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
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
                'price' => $product->price,
            ]);
        }

        return true;
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItem(CartItem $item, int $quantity): bool
    {
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
     * Get cart totals with applied discounts
     */
    public function getCartTotals(Cart $cart): array
    {
        $subtotal = $cart->total;
        $voucher = $this->getAppliedVoucher();
        $voucherDiscount = 0;

        if ($voucher) {
            $voucherDiscount = $this->calculateVoucherDiscount($cart, $voucher);
        }

        $pointsRedeemed = Session::get('points_redeemed', 0);
        $pointsDiscount = $pointsRedeemed;

        $total = $subtotal - $voucherDiscount - $pointsDiscount;
        $total = max(0, $total); // Ensure total is not negative

        return [
            'subtotal' => $subtotal,
            'voucher_discount' => $voucherDiscount,
            'points_discount' => $pointsDiscount,
            'total' => $total,
            'voucher' => $voucher,
            'points_redeemed' => $pointsRedeemed,
        ];
    }

    /**
     * Get applied voucher from session
     */
    public function getAppliedVoucher()
    {
        $voucherCode = Session::get('voucher_code');
        if (! $voucherCode) {
            return null;
        }

        return \App\Models\Voucher::where('code', $voucherCode)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Calculate voucher discount
     */
    public function calculateVoucherDiscount(Cart $cart, $voucher): float
    {
        if ($voucher->type === 'percent') {
            return round($cart->total * ($voucher->amount / 100), 2);
        }

        return min($voucher->amount, $cart->total);
    }

    /**
     * Validate and apply voucher
     */
    public function applyVoucher(string $voucherCode, Cart $cart): array
    {
        $voucher = \App\Models\Voucher::where('code', $voucherCode)
            ->where('is_active', true)
            ->first();

        if (! $voucher) {
            return ['success' => false, 'message' => __('Invalid or inactive voucher.')];
        }

        if ($voucher->valid_from && now()->lt($voucher->valid_from)) {
            return ['success' => false, 'message' => __('Voucher not yet valid.')];
        }

        if ($voucher->valid_until && now()->gt($voucher->valid_until)) {
            return ['success' => false, 'message' => __('Voucher expired.')];
        }

        if ($voucher->max_uses && $voucher->used_count >= $voucher->max_uses) {
            return ['success' => false, 'message' => __('Voucher usage limit reached.')];
        }

        if ($voucher->min_order && $cart->total < $voucher->min_order) {
            return ['success' => false, 'message' => __('Order does not meet minimum amount for this voucher.')];
        }

        Session::put('voucher_code', $voucher->code);

        return ['success' => true, 'message' => __('Voucher applied!')];
    }

    /**
     * Remove applied voucher
     */
    public function removeVoucher(): bool
    {
        Session::forget('voucher_code');

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
        $totals = $this->getCartTotals($cart);

        return [
            'item_count' => $cart->item_count,
            'subtotal' => $totals['subtotal'],
            'voucher_discount' => $totals['voucher_discount'],
            'points_discount' => $totals['points_discount'],
            'total' => $totals['total'],
            'voucher' => $totals['voucher'],
        ];
    }
}
