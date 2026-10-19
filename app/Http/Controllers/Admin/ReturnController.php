<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\ReturnService;
use Illuminate\Http\Request;

/**
 * Reviewing return requests, approving the refund and recording when it is sent.
 */
class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');

        $returns = ReturnRequest::with(['order', 'user', 'sellerOrder.seller'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['requested', 'approved']))
            ->when(in_array($status, ['requested', 'approved', 'refunded', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = ReturnRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $refundsDue = \App\Models\Order::where('refund_status', 'due')->with('user')->latest('updated_at')->get();

        return view('admin.returns.index', compact('returns', 'status', 'counts', 'refundsDue'));
    }

    public function show(ReturnRequest $return, ReturnService $returns)
    {
        $return->load(['order.user', 'user', 'sellerOrder.seller', 'items.orderItem.product']);

        return view('admin.returns.show', [
            'return' => $return,
            'suggested' => $returns->suggestedRefund($return),
            'maxRefund' => $returns->refundableLeft($return),
        ]);
    }

    public function approve(Request $request, ReturnRequest $return, ReturnService $returns)
    {
        $data = $request->validate([
            'refund_amount' => 'required|numeric|min:0',
            'restock' => 'nullable|boolean',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        if (! $returns->approve($return, (float) $data['refund_amount'], $request->boolean('restock'), $data['admin_note'] ?? null, $request->user())) {
            return back()->with('error', $return->order->payment_status !== 'paid' && (float) $data['refund_amount'] > 0
                ? 'This order has not been paid, so there is no money to refund. Confirm the payment first, or approve with a refund of 0 to take the items back.'
                : 'This return can\'t be approved: it was already handled, or the refund is more than is left on the order.');
        }

        return back()->with('success', 'Return approved. The customer has been told, and the shop\'s share was taken from its next payout.');
    }

    public function reject(Request $request, ReturnRequest $return, ReturnService $returns)
    {
        $data = $request->validate(['admin_note' => 'required|string|max:1000']);

        if (! $returns->reject($return, $data['admin_note'], $request->user())) {
            return back()->with('error', 'This return was already handled.');
        }

        return back()->with('success', 'Return rejected. The customer has been told why.');
    }

    public function refunded(Request $request, ReturnRequest $return, ReturnService $returns)
    {
        $data = $request->validate(['refund_reference' => 'required|string|max:100']);
        if ($problem = \App\Support\RefundApproval::problem($return, $request->user())) {
            return back()->with('error', $problem);
        }

        if (! $returns->markRefunded($return, $data['refund_reference'])) {
            return back()->with('error', 'Only an approved return on a paid order, with an amount to send back, can be marked as refunded.');
        }

        return back()->with('success', 'Refund recorded. The customer has been emailed.');
    }

    public function approveRefund(Request $request, ReturnRequest $return)
    {
        if ($return->status !== 'approved' || ! \App\Support\RefundApproval::needed($return)) {
            return back()->with('error', 'This refund does not need an approval.');
        }
        \App\Support\RefundApproval::approve($return, $request->user());

        return back()->with('success', 'Refund approved. Another staff member can now send and record it.');
    }
}
