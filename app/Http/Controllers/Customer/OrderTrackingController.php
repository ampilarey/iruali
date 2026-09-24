<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

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
        $request->validate(['order_code' => 'required|string|max:40']);

        $order = Order::where('order_number', trim($request->order_code))->first();
        if (! $order) {
            return back()->withInput()->withErrors(['order_code' => __('Order not found.')]);
        }

        // The status page is only reachable through a short-lived signed link,
        // so order ids in the URL can't be guessed or enumerated.
        return redirect()->to(URL::temporarySignedRoute('order.track.show', now()->addMinutes(30), $order));
    }

    // Show order status
    public function show(Order $order)
    {
        return view('orders.status', compact('order'));
    }
}
