<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        $stats = [
            'total_products' => $user->products()->count(),
            'active_products' => $user->products()->where('is_active', true)->count(),
            'pending_products' => $user->products()->where('is_active', false)->count(),
            'low_stock' => $user->products()->whereColumn('stock_quantity', '<=', 'reorder_point')->count(),
            'total_orders' => $this->sellerOrders()->count(),
            'pending_orders' => $this->sellerOrders()->where('status', 'pending')->count(),
            'total_revenue' => $this->revenue(),
        ];

        $recent_orders = $this->sellerOrders()->with('user')->latest()->take(5)->get();
        $recent_products = $user->products()->with('category')->latest()->take(5)->get();

        return view('seller.dashboard', compact('stats', 'recent_orders', 'recent_products'));
    }

    public function orders(Request $request)
    {
        $orders = $this->sellerOrders()
            ->with(['user', 'items' => fn ($q) => $this->onlyOwnItems($q), 'items.product'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('seller.orders.index', compact('orders'));
    }

    public function showOrder(Order $order)
    {
        abort_unless($this->sellerOrders()->whereKey($order->id)->exists(), 404);

        $order->load(['user', 'items' => fn ($q) => $this->onlyOwnItems($q), 'items.product']);

        return view('seller.orders.show', compact('order'));
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

    public function profile()
    {
        $user = Auth::user();

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
        ]);

        $user->update($validated);

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
