<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Services\FulfilmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    use \App\Traits\SecureFileUpload;

    public function dashboard()
    {
        $user = Auth::user();

        $stats = [
            'total_products' => $user->products()->count(),
            'active_products' => $user->products()->where('is_active', true)->count(),
            'pending_products' => $user->products()->where('is_active', false)->count(),
            'low_stock' => $user->products()->whereColumn('stock_quantity', '<=', 'reorder_point')->count(),
            'total_orders' => $this->sellerOrders()->count(),
            'pending_orders' => SellerOrder::where('seller_id', $user->id)->whereIn('status', ['pending', 'processing'])->count(),
            'total_revenue' => $this->revenue(),
        ];

        $recent_orders = $this->sellerOrders()->with('user')->latest()->take(5)->get();
        $recent_products = $user->products()->with('category')->latest()->take(5)->get();
        $onboarding = $user->isOnboarded() ? [] : app(\App\Services\OnboardingService::class)->items($user);

        return view('seller.dashboard', compact('stats', 'recent_orders', 'recent_products', 'onboarding'));
    }

    public function orders(Request $request)
    {
        $parts = SellerOrder::query()
            ->where('seller_id', Auth::id())
            ->with(['order.user', 'order.items' => fn ($q) => $this->onlyOwnItems($q), 'order.items.product'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('seller.orders.index', compact('parts'));
    }

    public function showOrder(Order $order, FulfilmentService $fulfilment)
    {
        $part = $fulfilment->partFor($order, Auth::user());
        abort_unless($part, 404);

        $nextStatuses = $fulfilment->nextStatuses($part);
        $order->load(['user', 'items' => fn ($q) => $this->onlyOwnItems($q), 'items.product']);
        $otherShops = $order->sellerOrders()->where('seller_id', '!=', Auth::id())->count();

        return view('seller.orders.show', compact('order', 'part', 'nextStatuses', 'otherShops'));
    }

    /**
     * A shop moves its own part of the order along (it works for orders shared with other shops too).
     */
    public function updateOrderStatus(Request $request, Order $order, FulfilmentService $fulfilment)
    {
        $part = $fulfilment->partFor($order, Auth::user());
        abort_unless($part, 404);

        $request->validate([
            'status' => 'required|in:processing,shipped,delivered',
            'tracking_note' => 'nullable|string|max:255',
        ]);

        if (! $fulfilment->advance($part, $request->status, $request->tracking_note)) {
            return back()->with('error', "Your part of this order is {$part->status}; it can't be moved to {$request->status}.");
        }

        return back()->with('success', 'Your part of the order is now '.$request->status.'.');
    }

    public function analytics()
    {
        $user = Auth::user();

        $monthlySales = $this->ownItems()
            ->where('orders.created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->get(['order_items.price', 'order_items.quantity', 'orders.created_at'])
            ->groupBy(fn ($row) => \Illuminate\Support\Carbon::parse($row->created_at)->format('Y-m'))
            ->map(fn ($rows) => $rows->sum(fn ($r) => $r->price * $r->quantity));

        $months = collect(range(11, 0))->mapWithKeys(function ($i) use ($monthlySales) {
            $key = now()->subMonths($i)->format('Y-m');

            return [$key => (float) ($monthlySales[$key] ?? 0)];
        });

        $topProducts = $this->ownItems()
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.quantity * order_items.price) as revenue'))
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(function ($row) use ($user) {
                $row->product = $user->products()->withTrashed()->find($row->product_id);

                return $row;
            });

        $stats = [
            'total_revenue' => $this->revenue(),
            'units_sold' => (int) $this->ownItems()->sum('order_items.quantity'),
            'total_orders' => $this->sellerOrders()->where('status', '!=', 'cancelled')->count(),
        ];
        $stats['average_order'] = $stats['total_orders'] > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0;

        return view('seller.analytics', compact('stats', 'months', 'topProducts'));
    }

    public function performance(\App\Services\SellerPerformanceService $performance)
    {
        return view('seller.performance', ['report' => $performance->report(Auth::user())]);
    }

    public function questions(Request $request)
    {
        $own = fn ($q) => $q->whereHas('product', fn ($p) => $p->withTrashed()->where('seller_id', Auth::id()));

        $questions = \App\Models\ProductQuestion::query()->tap($own)
            ->when($request->query('show') !== 'all', fn ($q) => $q->whereNull('answer'))
            ->with(['product', 'user'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unanswered = \App\Models\ProductQuestion::query()->tap($own)->whereNull('answer')->count();

        return view('seller.questions', compact('questions', 'unanswered'));
    }

    public function earnings(\App\Services\PayoutService $payouts)
    {
        $user = Auth::user();

        $user->load('bankAccount');
        $balances = $payouts->balances($user);
        $parts = SellerOrder::where('seller_id', $user->id)->with(['order', 'payout'])->latest()->paginate(20);
        $payoutHistory = $user->payouts()->with('batch')->latest('id')->take(20)->get();
        $rate = $user->effectiveCommissionRate();
        $adjustments = \App\Models\SellerAdjustment::where('seller_id', $user->id)->with('payout')->latest()->take(20)->get();

        return view('seller.earnings', compact('balances', 'parts', 'payoutHistory', 'rate', 'user', 'adjustments'));
    }

    public function returns()
    {
        $returns = \App\Models\ReturnRequest::whereHas('sellerOrder', fn ($q) => $q->where('seller_id', Auth::id()))
            ->with(['order', 'items.orderItem.product'])
            ->latest()
            ->paginate(20);

        return view('seller.returns', compact('returns'));
    }

    public function profile()
    {
        $user = Auth::user();
        $user->load('bankAccount');

        return view('seller.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'business_description' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:20|unique:users,phone,'.$user->id,
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'payout_bank_name' => 'nullable|string|max:100',
            'payout_account_name' => 'nullable|string|max:150',
            'payout_account_number' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 -]+$/'],
            'delivery_notes' => 'nullable|string|max:1000',
            'ships_to_islands' => 'nullable|boolean',
            'shop_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'shop_banner' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        unset($validated['shop_logo'], $validated['shop_banner']);
        if ($request->has('ships_to_islands') || $request->has('delivery_notes')) {
            $validated['ships_to_islands'] = $request->boolean('ships_to_islands');
        }
        foreach (['shop_logo', 'shop_banner'] as $image) {
            if ($request->hasFile($image) && ($path = $this->storeFileSecurely($request->file($image), 'shops', ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'], $image === 'shop_logo' ? 2048 : 4096))) {
                $this->deleteFile($user->{$image});
                $validated[$image] = $path;
            }
        }

        $user->update($validated);
        app(\App\Services\OnboardingService::class)->refresh($user->fresh());

        return redirect()->route('seller.profile')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Orders that contain at least one of the current seller's products.
     */
    protected function sellerOrders(): Builder
    {
        return Order::whereHas('items.product', fn ($q) => $q->withTrashed()->where('seller_id', Auth::id()));
    }

    /**
     * Restrict an order-items query to the current seller's products.
     */
    protected function onlyOwnItems($query)
    {
        return $query->whereHas('product', fn ($q) => $q->withTrashed()->where('seller_id', Auth::id()));
    }

    /**
     * Order items for the current seller's products on non-cancelled orders.
     */
    protected function ownItems(): Builder
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.seller_id', Auth::id())
            ->where('orders.status', '!=', 'cancelled')
            ->whereNull('orders.deleted_at');
    }

    protected function revenue(): float
    {
        return (float) $this->ownItems()->sum(DB::raw('order_items.price * order_items.quantity'));
    }
}
