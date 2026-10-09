<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\DeliveryService;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
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
        $user = Auth::user();
        $cart = $this->cartService->getOrCreateCart();
        // A bulk quote that expired or was closed comes out of the cart; the cart page says why
        if (app(\App\Services\QuoteService::class)->pruneCart($cart)) {
            return redirect()->route('cart');
        }
        $cart->load(['items.product.mainImage']);

        if ($this->cartService->isCartEmpty($cart)) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        $points_balance = $user->loyalty_points;
        $points_redeemed = session('points_redeemed', 0);
        $points_redeemed_discount = $points_redeemed;

        // Same calculation the order uses: subtotal minus voucher and points
        $discounts = $this->discountService->calculateTotalDiscount($cart);
        $voucherDiscount = (float) $discounts['voucher']['amount'];
        $voucherCode = $discounts['voucher']['voucher']?->code;
        $goodsTotal = (float) $discounts['final_total'];
        $deliveryZones = DeliveryService::zones();
        $deliveryQuotes = app(DeliveryService::class)->quotes($goodsTotal);
        $freeDeliveryOver = (float) \App\Models\Setting::get('free_delivery_over');
        \App\Services\FunnelService::record('begin_checkout');

        // Saved addresses as cards (the default one pre-selected), plus the island list for a new address
        $addresses = $user->addresses()->defaultFirst()->with('islandRecord')->get();
        $selectedAddressId = old('address_id', $addresses->firstWhere('is_default', true)->id ?? $addresses->first()->id ?? '');
        $islandsByAtoll = app(DeliveryService::class)->islandsByAtoll();

        return view('checkout.index', compact('cart', 'points_balance', 'points_redeemed', 'points_redeemed_discount', 'voucherDiscount', 'voucherCode', 'goodsTotal', 'deliveryZones', 'deliveryQuotes', 'freeDeliveryOver', 'addresses', 'selectedAddressId', 'islandsByAtoll'));
    }

    public function redeemPoints(Request $request)
    {
        $user = Auth::user();
        $cart = $this->cartService->getOrCreateCart();

        $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $result = $this->discountService->applyLoyaltyPoints($request->points, $user, $cart);

        if (! $result['valid']) {
            return back()->withErrors(['points' => $result['message']]);
        }

        return back()->with('success', __('Loyalty points applied!'));
    }

    public function removePoints()
    {
        $this->discountService->removeLoyaltyPoints();

        return back()->with('success', __('Loyalty points removed.'));
    }
}
