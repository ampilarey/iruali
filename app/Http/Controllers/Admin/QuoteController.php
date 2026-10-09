<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuoteStatus;
use App\Exceptions\QuoteException;
use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use App\Services\QuoteService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin → Quotes: every bulk quote request with status filters (and "waiting", the new ones a shop
 * has not answered for more than two days: the inbox row). Staff can write in a request's messages
 * as iruali support and close a request with a reason; both are in the audit log. What customers
 * and shops do with their own requests is not audited.
 */
class QuoteController extends Controller
{
    public const FILTERS = ['waiting', 'new', 'quoted', 'accepted', 'ordered', 'declined', 'expired', 'all'];

    public function __construct(protected QuoteService $quotes) {}

    public function index(Request $request)
    {
        $this->quotes->expireDue();
        $status = in_array($request->query('status'), self::FILTERS, true) ? (string) $request->query('status') : 'all';

        $requests = QuoteRequest::query()
            ->when($status === 'waiting', fn ($q) => $q->waitingForShop())
            ->when(! in_array($status, ['waiting', 'all'], true), fn ($q) => $q->where('status', $status))
            ->with(['customer', 'seller', 'product'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $counts = QuoteRequest::query()->selectRaw('status, count(*) as n')->groupBy('status')->toBase()->pluck('n', 'status')->all();
        $counts['waiting'] = QuoteRequest::query()->waitingForShop()->count();
        $counts['all'] = array_sum(array_intersect_key($counts, array_flip(QuoteStatus::values())));

        return view('admin.quotes.index', ['requests' => $requests, 'status' => $status, 'counts' => $counts]);
    }

    public function show(QuoteRequest $quote)
    {
        $this->quotes->expireDue();
        $quote->refresh()->load(['customer', 'seller', 'product', 'order', 'messages.sender']);

        return view('admin.quotes.show', ['quote' => $quote]);
    }

    /**
     * Close a request on iruali's behalf (a shop that cannot be reached, a mistaken price): the
     * customer and the shop are told why, and a quote in the cart comes out of it.
     */
    public function close(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => __('reason')]);
        $before = $quote->status;

        try {
            $this->quotes->decline($quote, 'admin', $data['reason']);
        } catch (QuoteException $e) {
            return redirect()->route('admin.quotes.show', $quote)->with('error', $e->getMessage());
        }

        Audit::record('quote.closed', $quote, ['from' => $before, 'to' => $quote->status, 'reason' => $quote->decline_reason]);

        return redirect()->route('admin.quotes.show', $quote)->with('success', __('Request closed. The customer and the shop have been told.'));
    }

    public function message(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $data = $request->validateWithBag('quoteMessage', ['body' => 'required|string|max:'.QuoteService::MESSAGE_MAX_LENGTH]);

        try {
            $message = $this->quotes->postMessage($quote, $request->user(), 'admin', $data['body']);
        } catch (QuoteException $e) {
            return redirect()->route('admin.quotes.show', $quote)->with('error', $e->getMessage());
        }

        Audit::record('quote.message', $quote, ['message_id' => $message->id]);

        return redirect()->to(route('admin.quotes.show', $quote).'#messages')->with('success', __('Message sent to the customer and the shop.'));
    }
}
