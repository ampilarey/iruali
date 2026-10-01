<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Services\DisputeService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A customer opening a dispute on one shop's part of their order.
 */
class DisputeController extends Controller
{
    public function store(Request $request, Order $order, SellerOrder $part, DisputeService $disputes)
    {
        abort_unless($order->user_id === $request->user()->id && $part->order_id === $order->id, 403);

        $types = $disputes->availableTypes($part, $request->user());
        if ($types === []) {
            NotificationService::error(__('A dispute can\'t be opened on these items right now.'));

            return redirect()->route('orders.show', $order);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in($types)],
            'amount_claimed' => 'required|numeric|min:0.01|max:'.max(0.01, $disputes->maxClaim($part)),
            'details' => 'required|string|min:10|max:2000',
        ], [
            'amount_claimed.max' => __('You can claim at most :amount on these items.', ['amount' => \App\Support\Money::format($disputes->maxClaim($part))]),
            'details.min' => __('Please tell us a little more about what happened.'),
        ]);

        $dispute = $disputes->open($part, $request->user(), $data['type'], (float) $data['amount_claimed'], $data['details']);
        if (! $dispute) {
            NotificationService::error(__('A dispute can\'t be opened on these items right now.'));

            return redirect()->route('orders.show', $order);
        }

        NotificationService::success(__('Your dispute is open. The shop has been told and iruali will decide within 5 business days.'));

        return redirect()->route('orders.show', $order)->withFragment('dispute-'.$dispute->id);
    }
}
