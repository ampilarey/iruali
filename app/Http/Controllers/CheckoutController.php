<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $cart = $user->cart;
        $points_balance = $user->loyalty_points;
        $points_redeemed = session('points_redeemed', 0);
        $points_redeemed_discount = $points_redeemed;
        if (!$cart || $cart->items->count() === 0) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }
        return view('checkout.index', compact('cart', 'points_balance', 'points_redeemed', 'points_redeemed_discount'));
    }

    public function redeemPoints(Request $request)
    {
        $user = Auth::user();
        $cart = $user->cart;
        $max_points = min($user->loyalty_points, $cart->total);
        $request->validate([
            'points' => 'required|integer|min:1|max:' . $max_points,
        ]);
        session(['points_redeemed' => $request->points]);
        return back()->with('success', __('Loyalty points applied!'));
    }

    public function removePoints()
    {
        session()->forget('points_redeemed');
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