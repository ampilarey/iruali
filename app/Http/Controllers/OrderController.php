<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreOrderRequest;

class OrderController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Order::class);

        $orders = Order::where('user_id', Auth::id())
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load('items.product');

        return view('orders.show', compact('order'));
    }

    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $user = Auth::user();
        $cart = $user->carts()->where('status', 'active')->latest()->first();

        if (!$cart || $cart->items->count() === 0) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Create order
        $voucher = null;
        $voucher_discount = 0;
        if (session('voucher_code')) {
            $voucher = \App\Models\Voucher::where('code', session('voucher_code'))->where('is_active', true)->first();
            if ($voucher) {
                if ($voucher->type === 'percent') {
                    $voucher_discount = round($cart->total * ($voucher->amount / 100), 2);
                } else {
                    $voucher_discount = min($voucher->amount, $cart->total);
                }
            }
        }
        $points_redeemed = session('points_redeemed', 0);
        $points_redeemed_discount = $points_redeemed;
        $order_total = $cart->total - $voucher_discount - $points_redeemed_discount;
        if ($order_total < 0) $order_total = 0;
        // Calculate loyalty points (1 point per 100 MVR spent after all discounts)
        $loyalty_points_earned = floor($order_total / 100);
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => 'pending',
            'total_amount' => $order_total,
            'voucher_code' => $voucher ? $voucher->code : null,
            'voucher_discount' => $voucher_discount,
            'loyalty_points_earned' => $loyalty_points_earned,
            'points_redeemed' => $points_redeemed,
            'points_redeemed_discount' => $points_redeemed_discount,
            'shipping_address' => $request->shipping_address,
            'shipping_city' => $request->shipping_city,
            'shipping_state' => $request->shipping_state,
            'shipping_zip' => $request->shipping_zip,
            'shipping_country' => $request->shipping_country,
        ]);
        // Increment voucher usage
        if ($voucher) {
            $voucher->increment('used_count');
            session()->forget('voucher_code');
        }
        // Deduct redeemed points
        if ($points_redeemed > 0) {
            $user->decrement('loyalty_points', $points_redeemed);
            session()->forget('points_redeemed');
        }
        // Award loyalty points (always)
        if ($loyalty_points_earned > 0) {
            $user->increment('loyalty_points', $loyalty_points_earned);
        }

        // Referral reward: only after first order
        if ($user->referred_by && $user->orders()->count() === 1) {
            $referrer = $user->referredBy;
            if ($referrer) {
                $referrer->increment('loyalty_points', 100); // e.g., 100 points
                $user->increment('loyalty_points', 50); // e.g., 50 points (additive to order points)
                // Optionally, notify both users
                // Notification::send([$referrer, $user], new ReferralRewardedNotification());
            }
        }

        // Create order items from cart items
        foreach ($cart->items as $cartItem) {
            $order->items()->create([
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->product->final_price,
            ]);
        }

        // Clear cart
        $cart->items()->delete();
        $cart->status = 'ordered';
        $cart->save();

        return redirect()->route('orders.show', $order)->with('success', 'Order placed successfully!');
    }
}
