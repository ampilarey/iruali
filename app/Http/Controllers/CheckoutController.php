<?php

namespace App\Http\Controllers;

use App\Services\CartService;
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
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }
        
        $points_balance = $user->loyalty_points;
        $points_redeemed = session('points_redeemed', 0);
        $points_redeemed_discount = $points_redeemed;
        
        return view('checkout.index', compact('cart', 'points_balance', 'points_redeemed', 'points_redeemed_discount'));
    }

    public function redeemPoints(Request $request)
    {
        $user = Auth::user();
        $cart = $this->cartService->getOrCreateCart();
        
        $request->validate([
            'points' => 'required|integer|min:1',
        ]);
        
        $result = $this->discountService->applyLoyaltyPoints($request->points, $user, $cart);
        
        if (!$result['valid']) {
            return back()->withErrors(['points' => $result['message']]);
        }
        
        return back()->with('success', __('Loyalty points applied!'));
    }

    public function removePoints()
    {
        $this->discountService->removeLoyaltyPoints();
        return back()->with('success', __('Loyalty points removed.'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'shipping_address' => 'required|string',
            'shipping_city' => 'required|string',
            'shipping_state' => 'required|string',
            'shipping_zip' => 'required|string',
            'shipping_country' => 'required|string',
            'payment_method' => 'required|in:card,paypal',
        ]);

        // Redirect to order creation
        return redirect()->route('orders.store')->with([
            'shipping_address' => $request->shipping_address,
            'shipping_city' => $request->shipping_city,
            'shipping_state' => $request->shipping_state,
            'shipping_zip' => $request->shipping_zip,
            'shipping_country' => $request->shipping_country,
        ]);
    }
} 