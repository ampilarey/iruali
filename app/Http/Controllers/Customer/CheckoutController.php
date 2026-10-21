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

    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return $this->signIn($request);
        }
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
        // Below a minimum order, or ordering paused altogether: back to the cart, which says why
        if ($problem = \App\Support\OrderRules::minimumProblem($cart, $goodsTotal) ?? \App\Support\OrderRules::pausedEntirely()) {
            return redirect()->route('cart')->with('error', $problem);
        }
        // Pre-order lines (remembered for placing the order), and a booking fee for those that take one:
        // booking-fee items are ordered on their own, so a mixed cart goes back to the cart, which says why
        $preorderLines = app(\App\Services\PreorderService::class)->checkoutLines($cart);
        $bookingFee = app(\App\Services\BookingFeeService::class)->checkout($cart, $preorderLines, $goodsTotal);
        if ($bookingFee !== null && $bookingFee['problem'] !== null) {
            return redirect()->route('cart')->with('error', $bookingFee['problem']);
        }
        $deliveryZones = DeliveryService::zones();
        $deliveryQuotes = app(DeliveryService::class)->quotes($goodsTotal);
        $freeDeliveryOver = (float) \App\Models\Setting::get('free_delivery_over');
        \App\Services\FunnelService::record('begin_checkout');

        // Saved addresses as cards (the default one pre-selected), plus the island list for a new address
        $addresses = $user->addresses()->defaultFirst()->with('islandRecord')->get();
        $selectedAddressId = old('address_id', $addresses->firstWhere('is_default', true)->id ?? $addresses->first()->id ?? '');
        $islandsByAtoll = app(DeliveryService::class)->islandsByAtoll();

        return view('checkout.index', compact('cart', 'points_balance', 'points_redeemed', 'points_redeemed_discount', 'voucherDiscount', 'voucherCode', 'goodsTotal', 'deliveryZones', 'deliveryQuotes', 'freeDeliveryOver', 'addresses', 'selectedAddressId', 'islandsByAtoll', 'preorderLines', 'bookingFee'));
    }

    /**
     * Not signed in: the sign-in steps inside checkout (as on Bake & Grill), beside the order. After
     * signing in the customer is back here with their cart, and setting a password can wait.
     */
    protected function signIn(Request $request)
    {
        if ($request->boolean('change')) {
            $request->session()->forget(PhoneLoginController::SESSION);
        }
        $cart = $this->cartService->currentCart();
        if (! $cart || $this->cartService->isCartEmpty($cart)) {
            return redirect()->route('cart')->with('error', __('Your cart is empty.'));
        }
        $cart->load(['items.product']);
        $request->session()->put('url.intended', route('checkout'));

        return view('checkout.sign-in', [
            'cart' => $cart,
            'goodsTotal' => (float) $this->discountService->calculateTotalDiscount($cart)['final_total'],
            'pending' => $request->session()->get(PhoneLoginController::SESSION),
            'smsAvailable' => app(\App\Services\Sms\SmsManager::class)->isLive() && \App\Services\Sms\SmsManager::enabled(),
        ]);
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
