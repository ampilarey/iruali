<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OTP;

class OrderTrackingController extends Controller
{
    // Show tracking form
    public function form()
    {
        return view('orders.track');
    }

    // Handle tracking form submission
    public function submit(Request $request)
    {
        $request->validate([
            'order_code' => 'nullable|string',
            'mobile' => 'nullable|string',
            'otp' => 'nullable|string',
        ]);

        if ($request->filled('order_code')) {
            $order = Order::where('order_number', $request->order_code)->first();
            if ($order) {
                return redirect()->route('order.track.show', $order);
            } else {
                return back()->withErrors(['order_code' => __('Order not found.')]);
            }
        }

        if ($request->filled('mobile') && $request->filled('otp')) {
            $order = Order::where('mobile', $request->mobile)->latest()->first();
            if ($order && OTP::verify($request->mobile, $request->otp, 'order_tracking')) {
                return redirect()->route('order.track.show', $order);
            } else {
                return back()->withErrors(['otp' => __('Invalid OTP or mobile.')]);
            }
        }

        return back()->withErrors(['order_code' => __('Please enter order code or mobile/OTP.')]);
    }

    // Show order status
    public function show(Order $order)
    {
        return view('orders.status', compact('order'));
    }
} 