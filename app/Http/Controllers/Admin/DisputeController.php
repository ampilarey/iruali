<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\DisputeService;
use App\Services\MessagingService;
use Illuminate\Http\Request;

/**
 * Admin → Disputes: the list, the detail with the thread, and the decision.
 */
class DisputeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');

        $disputes = Dispute::with(['order', 'customer', 'seller'])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'resolved', fn ($q) => $q->whereNotIn('status', DisputeStatus::openValues()))
            ->when(DisputeStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
            ->latest('opened_at')
            ->paginate(25)
            ->withQueryString();

        $counts = Dispute::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.disputes.index', compact('disputes', 'status', 'counts'));
    }

    public function show(Dispute $dispute, MessagingService $messaging)
    {
        $dispute->load(['order.user', 'customer', 'seller', 'sellerOrder.seller', 'returnRequest', 'conversation.messages.sender', 'conversation.customer']);
        if ($dispute->conversation) {
            $messaging->markRead($dispute->conversation, 'admin');
        }

        return view('admin.disputes.show', ['dispute' => $dispute, 'part' => $dispute->sellerOrder, 'order' => $dispute->order]);
    }

    public function resolve(Request $request, Dispute $dispute, DisputeService $disputes)
    {
        $data = $request->validate([
            'outcome' => 'required|in:full,partial,reject',
            'amount' => 'required_if:outcome,partial|nullable|numeric|min:0.01',
            'note' => ['nullable', 'string', 'max:1000', 'required_if:outcome,reject'],
        ], ['note.required_if' => 'Tell the customer why the claim was not upheld.']);

        if (! $disputes->resolve($dispute, $request->user(), $data['outcome'], isset($data['amount']) ? (float) $data['amount'] : null, $data['note'] ?? null)) {
            return back()->with('error', 'This dispute can\'t be resolved that way: it is already decided, or the amount is not between zero and the amount claimed.');
        }

        return back()->with('success', match ($data['outcome']) {
            'full' => 'Full refund agreed. The refund is flagged on the order (send it through BML and record the reference there); the shop\'s share is taken from its next payout. Both sides have been told.',
            'partial' => 'Partial refund agreed and flagged on the order. Both sides have been told.',
            default => 'Dispute closed without a refund. Both sides have been told.',
        });
    }

    public function requestInfo(Request $request, Dispute $dispute, DisputeService $disputes)
    {
        $data = $request->validate(['from' => 'required|in:customer,seller', 'note' => 'required|string|max:2000']);

        if (! $disputes->requestInfo($dispute, $request->user(), $data['from'], $data['note'])) {
            return back()->with('error', 'This dispute is already decided.');
        }

        return back()->with('success', 'Your question was posted in the conversation and the dispute is now waiting for the '.($data['from'] === 'seller' ? 'shop' : 'customer').'.');
    }
}
