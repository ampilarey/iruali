<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    /**
     * Get or create a cart for the current user or session
     */
    public function getOrCreateCart(): Cart
    {
        if (Auth::check()) {
            $cart = Cart::where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => Auth::id(),
                    'session_id' => Session::getId(), // Always provide session_id
                    'status' => 'active'
                ]);
            }
        } else {
            $sessionId = Session::getId();
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

    /**
     * Add a product to cart
     */
    public function addToCart(int $productId, int $quantity, ?int $variantId = null): bool
    {
        $cart = $this->getOrCreateCart();
        $product = Product::findOrFail($productId);

        // Check if product is already in cart
        $existingItem = $cart->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $quantity
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $product->final_price
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
            'points_redeemed' => $pointsRedeemed
        ];
    }

    /**
     * Get applied voucher from session
     */
    public function getAppliedVoucher()
    {
        $voucherCode = Session::get('voucher_code');
        if (!$voucherCode) {
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

        if (!$voucher) {
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
            'voucher' => $totals['voucher']
        ];
    }
} 