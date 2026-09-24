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

        return view('checkout.index', compact('cart', 'points_balance', 'points_redeemed', 'points_redeemed_discount', 'voucherDiscount', 'voucherCode', 'goodsTotal', 'deliveryZones', 'deliveryQuotes', 'freeDeliveryOver'));
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
