<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Support\TeamAlerts;
use Illuminate\Http\Request;

/**
 * "How was your order?": a 1-5 rating and a comment, from the link in the delivered email or on the
 * order page (a signed link, so guests can use it without signing in). A low rating alerts the team.
 */
class OrderFeedbackController extends Controller
{
    public const LOW = 2;

    public function show(Order $order)
    {
        return view('feedback.order', [
            'order' => $order,
            'feedback' => OrderFeedback::where('order_id', $order->id)->first(),
        ]);
    }

    public function store(Request $request, Order $order)
    {
        abort_unless($order->status === 'delivered', 403);
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:1000']);

        $feedback = OrderFeedback::firstOrCreate(['order_id' => $order->id], [
            'user_id' => $order->user_id, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null,
        ]);

        if ($feedback->wasRecentlyCreated && $feedback->rating <= self::LOW) {
            TeamAlerts::send('complaint', 'iruali: order '.$order->order_number.' was rated '.$feedback->rating.'/5'.($feedback->comment ? ': '.mb_substr($feedback->comment, 0, 120) : '.'), route('admin.orders.show', $order));
        }

        return redirect()->to($request->fullUrl())->with('success', __('Thank you for rating your order.'));
    }
}
