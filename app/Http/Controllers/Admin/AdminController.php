<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Services\OrderService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Check if the authenticated user has admin role
     */
    private function checkAdminRole()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('admin')) {
            abort(403, 'Access denied. Admin role required.');
        }
    }

    public function dashboard()
    {
        $this->checkAdminRole();

        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::whereHas('roles', function ($q) {
                $q->where('name', 'seller');
            })->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'pending_sellers' => User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))->where('seller_approved', false)->count(),
            'pending_products' => Product::where('is_active', false)->count(),
        ];

        $recent_users = User::latest()->take(5)->get();
        $recent_orders = Order::with('user')->latest()->take(5)->get();
        $pending_sellers = User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))
            ->where('seller_approved', false)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent_users', 'recent_orders', 'pending_sellers'));
    }

    public function sellers()
    {
        $this->checkAdminRole();

        $sellers = User::whereHas('roles', function ($q) {
            $q->where('name', 'seller');
        })->with('roles')
            ->orderBy('seller_approved') // pending applications first
            ->latest('seller_applied_at')
            ->paginate(10);

        return view('admin.sellers.index', compact('sellers'));
    }

    public function approveSeller($id)
    {
        $this->checkAdminRole();

        $seller = User::findOrFail($id);
        $seller->update([
            'status' => 'active',
            'is_seller' => true,
            'seller_approved' => true,
            'seller_approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Seller approved successfully.');
    }

    public function rejectSeller($id)
    {
        $this->checkAdminRole();

        $seller = User::findOrFail($id);
        $sellerRole = Role::where('name', 'seller')->first();
        if ($sellerRole) {
            $seller->roles()->detach($sellerRole->id);
        }
        $seller->update([
            'is_seller' => false,
            'seller_approved' => false,
            'seller_approved_at' => null,
        ]);

        return redirect()->back()->with('success', 'Seller application rejected.');
    }

    public function suspendSeller($id)
    {
        $this->checkAdminRole();

        $seller = User::findOrFail($id);
        $seller->update(['status' => 'suspended']);

        return redirect()->back()->with('success', 'Seller suspended successfully.');
    }

    public function users()
    {
        $this->checkAdminRole();

        $users = User::with('roles')->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function products()
    {
        $this->checkAdminRole();

        $products = Product::with(['category', 'seller'])->paginate(10);

        return view('admin.products.index', compact('products'));
    }

    public function approveProduct($id)
    {
        $this->checkAdminRole();

        $product = Product::findOrFail($id);
        $product->update(['is_active' => true]);

        return redirect()->back()->with('success', 'Product approved successfully.');
    }

    public function rejectProduct($id)
    {
        $this->checkAdminRole();

        $product = Product::findOrFail($id);
        $product->update(['is_active' => false]);

        return redirect()->back()->with('success', 'Product rejected successfully.');
    }

    public function orders()
    {
        $this->checkAdminRole();

        $orders = Order::with(['user', 'items.product'])
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function reports()
    {
        $this->checkAdminRole();

        // Sales analytics, best-sellers, etc.
        return view('admin.reports.index');
    }

    public function analytics()
    {
        $this->checkAdminRole();

        $completed = Order::where('status', '!=', 'cancelled');

        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'revenue' => (float) (clone $completed)->sum('total_amount'),
            'revenue_30d' => (float) (clone $completed)->where('created_at', '>=', now()->subDays(30))->sum('total_amount'),
            'new_users_30d' => User::where('created_at', '>=', now()->subDays(30))->count(),
        ];
        $stats['average_order'] = ($count = (clone $completed)->count()) > 0 ? $stats['revenue'] / $count : 0;

        // Monthly revenue and signups for the last 12 months (grouped in PHP to stay DB-agnostic)
        $since = now()->subMonths(11)->startOfMonth();
        $revenueByMonth = (clone $completed)->where('created_at', '>=', $since)->get(['total_amount', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->format('Y-m'))
            ->map(fn ($rows) => $rows->sum('total_amount'));
        $usersByMonth = User::where('created_at', '>=', $since)->get(['created_at'])
            ->groupBy(fn ($u) => $u->created_at->format('Y-m'))
            ->map->count();
        $months = collect(range(11, 0))->mapWithKeys(function ($i) use ($revenueByMonth, $usersByMonth) {
            $key = now()->subMonths($i)->format('Y-m');

            return [$key => ['revenue' => (float) ($revenueByMonth[$key] ?? 0), 'users' => (int) ($usersByMonth[$key] ?? 0)]];
        });

        $ordersByStatus = Order::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $salesLines = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', '!=', 'cancelled')
            ->whereNull('orders.deleted_at');

        $topProducts = (clone $salesLines)
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.quantity * order_items.price) as revenue'))
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();
        $productNames = Product::withTrashed()->whereIn('id', $topProducts->pluck('product_id'))->get()->keyBy('id');
        $topProducts->each(fn ($row) => $row->product = $productNames[$row->product_id] ?? null);

        $topSellers = (clone $salesLines)
            ->select('products.seller_id', DB::raw('SUM(order_items.quantity * order_items.price) as revenue'), DB::raw('COUNT(DISTINCT orders.id) as orders'))
            ->groupBy('products.seller_id')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();
        $sellerNames = User::whereIn('id', $topSellers->pluck('seller_id'))->pluck('name', 'id');
        $topSellers->each(fn ($row) => $row->name = $sellerNames[$row->seller_id] ?? 'Unknown seller');

        return view('admin.analytics.index', compact('stats', 'months', 'ordersByStatus', 'topProducts', 'topSellers'));
    }

    public function settings()
    {
        $this->checkAdminRole();

        $settings = collect(Setting::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => Setting::get($key)]);

        return view('admin.settings.index', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $this->checkAdminRole();

        $validated = $request->validate([
            'announcement_text' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'loyalty_spend_per_point' => 'required|numeric|min:1|max:100000',
            'referral_referrer_points' => 'required|integer|min:0|max:100000',
            'referral_referee_points' => 'required|integer|min:0|max:100000',
            'delivery_fee_greater_male' => 'sometimes|required|numeric|min:0|max:100000',
            'delivery_fee_islands' => 'sometimes|required|numeric|min:0|max:100000',
            'free_delivery_over' => 'sometimes|required|numeric|min:0|max:1000000',
        ]);

        Setting::set($validated);

        return redirect()->route('admin.settings')->with('success', 'Settings saved.');
    }

    public function showOrder(Order $order, OrderService $orderService)
    {
        $this->checkAdminRole();

        $order->load(['user', 'items.product.seller']);
        $nextStatuses = $orderService->nextStatuses($order);

        return view('admin.orders.show', compact('order', 'nextStatuses'));
    }

    public function updateOrderStatus(Request $request, Order $order, OrderService $orderService)
    {
        $this->checkAdminRole();

        $request->validate(['status' => 'required|in:'.implode(',', array_keys(OrderService::TRANSITIONS))]);

        if (! $orderService->updateOrderStatus($order, $request->status)) {
            return back()->with('error', "An order that is {$order->status} can't be moved to {$request->status}.");
        }

        return back()->with('success', 'Order marked as '.$request->status.'.');
    }
}
