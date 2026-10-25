<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ItemRequest;
use App\Services\DeliveryService;
use App\Services\ItemRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Can't find it? We'll bring it for you", the customer's side (ItemRequestService): ask for an item,
 * follow it under My account → Item requests, accept the price into the cart, or cancel. A customer only
 * ever sees their own.
 */
class ItemRequestController extends Controller
{
    public function __construct(protected ItemRequestService $requests) {}

    public function index(Request $request): View
    {
        return view('item-requests.index', [
            'requests' => ItemRequest::where('user_id', $request->user()->id)->with('product.mainImage')->latest()->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        [$link, $title] = $this->shared($request);

        return view('item-requests.create', [
            'address' => $request->user()->defaultAddress(),
            'islandsByAtoll' => app(DeliveryService::class)->islandsByAtoll(),
            'prefill' => $title ?? mb_substr(trim((string) $request->query('q', '')), 0, 200),
            'prefillLink' => $link,
        ]);
    }

    /**
     * "Share to iruali": the installed app is a share target (public/site.webmanifest), so a link shared
     * from another app opens this form as ?title=…&text=…&url=…. Apps put the link in url or inside text;
     * the title is the shared page's name, or the text without the link.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function shared(Request $request): array
    {
        $text = trim((string) $request->query('text', ''));
        $candidates = array_filter([trim((string) $request->query('url', '')), preg_match('#https?://\S+#i', $text, $m) ? $m[0] : null]);
        $link = collect($candidates)->first(fn ($url) => mb_strlen($url) <= 500 && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url));

        $title = trim((string) $request->query('title', ''));
        if ($title === '' && $text !== '') {
            $title = trim(preg_replace('#https?://\S+#i', '', $text) ?? '');
        }
        $title = trim($title, " \t\n\r-–|:");

        return [$link ?: null, $title !== '' ? mb_substr($title, 0, 200) : null];
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['island_id' => $request->filled('island_id') ? $request->input('island_id') : null]);
        $data = $request->validate([
            'link' => 'nullable|url:http,https|max:500',
            'title' => 'required|string|min:3|max:200',
            'details' => 'nullable|string|max:2000',
            'options' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1|max:50',
            'island_id' => 'nullable|integer|exists:islands,id',
            'budget' => 'nullable|numeric|min:1|max:999999',
            'needed_by' => 'nullable|date|after_or_equal:today|before:'.now()->addYear()->toDateString(),
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'link.url' => __('Enter the full link to the item, starting with https://'),
            'title.required' => __('Tell us what the item is.'),
        ]);

        try {
            $itemRequest = $this->requests->submit($request->user(), collect($data)->except('photo')->all(), $request->file('photo'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('item-requests.show', $itemRequest)
            ->with('success', __('Thank you. We will look for it and send you a price, usually within 2 working days.'));
    }

    public function show(Request $request, ItemRequest $itemRequest): View
    {
        abort_unless((int) $itemRequest->user_id === (int) $request->user()->id, 404);

        return view('item-requests.show', ['itemRequest' => $itemRequest->load(['product.mainImage', 'island', 'order'])]);
    }

    public function accept(Request $request, ItemRequest $itemRequest): RedirectResponse
    {
        abort_unless((int) $itemRequest->user_id === (int) $request->user()->id, 404);

        try {
            $this->requests->accept($itemRequest, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cart')->with('success', __('It is in your cart at the price we sent. Check out to order it.'));
    }

    public function cancel(Request $request, ItemRequest $itemRequest): RedirectResponse
    {
        abort_unless((int) $itemRequest->user_id === (int) $request->user()->id, 404);

        try {
            $this->requests->cancel($itemRequest, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('item-requests.show', $itemRequest)->with('success', __('Your request is cancelled.'));
    }
}
