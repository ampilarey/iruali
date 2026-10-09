<?php

namespace App\Http\Controllers\Seller;

use App\Enums\QuoteStatus;
use App\Exceptions\QuoteException;
use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\QuoteService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;

/**
 * Seller Centre → Quote requests: businesses asking the shop for a bulk price. The shop sends a
 * quote (unit price, the quantity it can supply, how long it holds, a message) or declines with a
 * reason, and writes in each request's messages. A shop only ever sees its own requests.
 */
class QuoteController extends Controller
{
    /** The list's tabs: status filter => the statuses it shows. */
    public const TABS = [
        'new' => ['new'],
        'quoted' => ['quoted'],
        'accepted' => ['accepted'],
        'ordered' => ['ordered'],
        'declined' => ['declined'],
        'expired' => ['expired'],
        'all' => [],
    ];

    public function __construct(protected QuoteService $quotes) {}

    public function index(Request $request)
    {
        $this->quotes->expireDue();
        $shop = $this->shop();
        $status = array_key_exists((string) $request->query('status'), self::TABS) ? (string) $request->query('status') : 'new';

        $requests = QuoteRequest::where('seller_id', $shop->id)
            ->when(self::TABS[$status] !== [], fn ($q) => $q->whereIn('status', self::TABS[$status]))
            ->with(['product', 'customer'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $counts = QuoteRequest::where('seller_id', $shop->id)->selectRaw('status, count(*) as n')->groupBy('status')->toBase()->pluck('n', 'status');

        return view('seller.quotes.index', ['requests' => $requests, 'status' => $status, 'counts' => $counts]);
    }

    public function show(QuoteRequest $quote)
    {
        $this->authorizeShop($quote);
        $this->quotes->expireDue();
        $quote->refresh()->load(['product.mainImage', 'customer', 'order', 'messages.sender']);
        $this->quotes->markRead($quote, 'seller');

        return view('seller.quotes.show', ['quote' => $quote]);
    }

    /**
     * Send the quote, or change it while the customer has not accepted it yet.
     */
    public function quote(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $this->authorizeShop($quote);

        $validator = ValidatorFactory::make($request->all(), [
            'unit_price' => 'required|numeric|decimal:0,2|min:0.01|max:999999.99',
            'quantity' => 'required|integer|min:1|max:'.QuoteService::MAX_QUANTITY,
            'valid_until' => 'required|date|after_or_equal:today|before_or_equal:'.now()->addDays(QuoteService::MAX_VALID_DAYS)->toDateString(),
            'message' => 'nullable|string|max:'.QuoteService::MESSAGE_MAX_LENGTH,
        ], [
            'valid_until.before_or_equal' => __('A quote can hold for :days days at most.', ['days' => QuoteService::MAX_VALID_DAYS]),
        ], [
            'unit_price' => __('unit price'),
            'valid_until' => __('valid until'),
        ]);
        // Price × quantity must stay within what one order line can hold
        $validator->after(function (Validator $validator) use ($request) {
            if ($validator->errors()->isEmpty() && round((float) $request->input('unit_price') * (int) $request->input('quantity'), 2) > QuoteService::MAX_LINE_TOTAL) {
                $validator->errors()->add('unit_price', __('A quote can be at most :amount in total. Lower the quantity, or split it into more than one order.', ['amount' => Money::format(QuoteService::MAX_LINE_TOTAL)]));
            }
        });
        $data = $validator->validateWithBag('quote');

        try {
            $this->quotes->sendQuote($quote, $data);
        } catch (QuoteException $e) {
            return redirect()->route('seller.quotes.show', $quote)->with('error', $e->getMessage());
        }

        return redirect()->route('seller.quotes.show', $quote)->with('success', __('Your quote was sent to :business: :line, until :date.', [
            'business' => $quote->business_name,
            'line' => QuoteService::priceLine($quote),
            'date' => $quote->valid_until?->translatedFormat('j M Y'),
        ]));
    }

    public function decline(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $this->authorizeShop($quote);
        $data = $request->validateWithBag('decline', ['reason' => 'required|string|max:500'], [], ['reason' => __('reason')]);

        try {
            $this->quotes->decline($quote, 'shop', $data['reason']);
        } catch (QuoteException $e) {
            return redirect()->route('seller.quotes.show', $quote)->with('error', $e->getMessage());
        }

        return redirect()->route('seller.quotes.show', $quote)->with('success', __('Request declined. The customer has been told why.'));
    }

    public function message(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $this->authorizeShop($quote);
        $data = $request->validateWithBag('quoteMessage', ['body' => 'required|string|max:'.QuoteService::MESSAGE_MAX_LENGTH]);

        try {
            $this->quotes->postMessage($quote, $this->shop(), 'seller', $data['body']);
        } catch (QuoteException $e) {
            return redirect()->route('seller.quotes.show', $quote)->with('error', $e->getMessage());
        }

        return redirect()->to(route('seller.quotes.show', $quote).'#messages')->with('success', __('Message sent.'));
    }

    /**
     * The shop whose Seller Centre this is: the one place it is read (staff sign-ins resolve it here).
     */
    /** The shop the requests are for: the owner signed in, or a member of its staff. */
    protected function shop(): User
    {
        return \App\Support\CurrentShop::get();
    }

    protected function authorizeShop(QuoteRequest $quote): void
    {
        abort_unless((int) $quote->seller_id === (int) $this->shop()->id, 403);
    }

    /** Labels for the list's tabs. */
    public static function tabLabel(string $tab): string
    {
        return $tab === 'all' ? __('All') : QuoteStatus::labelFor($tab);
    }
}
