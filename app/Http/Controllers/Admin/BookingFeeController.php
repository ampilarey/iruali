<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BookingFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin → order → Booking fee: give the customer until another day to pay the balance of a
 * pre-order with a booking fee (App\Services\BookingFeeService). They are sent the new date with a
 * fresh payment link. Cancelling and keeping the fee is the order's normal Cancel button.
 */
class BookingFeeController extends Controller
{
    public function moveDueDate(Request $request, Order $order, BookingFeeService $bookingFees): RedirectResponse
    {
        $data = $request->validate(['balance_due_on' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(90)->toDateString()]]);

        if (! $bookingFees->moveDueDate($order, Carbon::parse($data['balance_due_on']), $request->user())) {
            return back()->with('error', 'This order is not waiting for its balance.');
        }

        return back()->with('success', 'The balance is now due '.$order->balance_due_on?->format('j M Y').'. The customer has been sent the new date with a payment link.');
    }
}
