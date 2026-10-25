<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ItemRequest;
use App\Models\User;
use App\Services\BookingFeeService;
use App\Services\ItemRequestService;
use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin → Item requests (admins only: not in config/staff.php): customers' requests for items iruali
 * doesn't list. An admin sends a price (the shop that fills it, payment, days to ship) or declines
 * (ItemRequestService).
 */
class ItemRequestController extends Controller
{
    public const TABS = ['open', 'new', 'quoted', 'accepted', 'ordered', 'declined', 'cancelled', 'all'];

    public function __construct(protected ItemRequestService $requests) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('status'), self::TABS, true) ? $request->query('status') : 'open';

        return view('admin.item-requests.index', [
            'tab' => $tab,
            'requests' => ItemRequest::with(['user', 'island'])
                ->when($tab === 'open', fn ($q) => $q->open())
                ->when(! in_array($tab, ['open', 'all'], true), fn ($q) => $q->where('status', $tab))
                ->latest()->paginate(25)->withQueryString(),
            'waiting' => ItemRequest::where('status', ItemRequest::NEW)->count(),
        ]);
    }

    public function show(ItemRequest $itemRequest): View
    {
        return view('admin.item-requests.show', [
            'itemRequest' => $itemRequest->load(['user', 'island', 'seller', 'product', 'order', 'quotedBy']),
            'shops' => User::query()->where('is_seller', true)->where('preorders_allowed', true)
                ->whereHas('roles', fn ($r) => $r->where('name', 'seller'))->orderBy('business_name')->get()
                ->filter(fn (User $shop) => $shop->maySell())->values(),
            'categories' => Category::get()->sortBy(fn (Category $category) => $category->getTranslation('name', app()->getLocale()))->values(),
            'rates' => ProductImportService::rates(),
        ]);
    }

    public function quote(Request $request, ItemRequest $itemRequest): RedirectResponse
    {
        $data = $request->validate([
            'seller_id' => 'required|integer|exists:users,id',
            'category_id' => 'required|integer|exists:categories,id',
            'title' => 'nullable|string|max:200',
            'unit_price' => 'required|numeric|min:1|max:999999',
            'payment' => 'required|in:deposit,full',
            'deposit_percent' => 'required_if:payment,deposit|nullable|integer|min:10|max:'.BookingFeeService::MAX_PERCENT,
            'ships_in_days' => 'required|integer|min:1|max:180',
            'valid_days' => 'required|integer|min:1|max:30',
            'quote_message' => 'nullable|string|max:1000',
        ]);
        $data['deposit_percent'] = $data['payment'] === 'deposit' ? (int) $data['deposit_percent'] : null;

        try {
            $this->requests->quote($itemRequest, $request->user(), $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.item-requests.show', $itemRequest)->with('success', 'Price sent to the customer by email/SMS.');
    }

    public function decline(Request $request, ItemRequest $itemRequest): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->requests->decline($itemRequest, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.item-requests.show', $itemRequest)->with('success', 'Request declined; the customer has been told.');
    }
}
