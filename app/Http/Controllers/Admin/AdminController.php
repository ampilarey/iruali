<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::whereHas('roles', function($q) {
                $q->where('name', 'seller');
            })->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'pending_sellers' => User::where('status', 'pending_approval')->count(),
            'pending_products' => Product::where('status', 'pending')->count(),
        ];

        $recent_users = User::latest()->take(5)->get();
        $recent_orders = Order::with('user')->latest()->take(5)->get();
        $pending_sellers = User::where('status', 'pending_approval')->get();

        return view('admin.dashboard', compact('stats', 'recent_users', 'recent_orders', 'pending_sellers'));
    }

    public function sellers()
    {
        $sellers = User::whereHas('roles', function($q) {
            $q->where('name', 'seller');
        })->with('roles')->paginate(10);

        return view('admin.sellers.index', compact('sellers'));
    }

    public function approveSeller($id)
    {
        $seller = User::findOrFail($id);
        $seller->update(['status' => 'active']);

        return redirect()->back()->with('success', 'Seller approved successfully.');
    }

    public function suspendSeller($id)
    {
        $seller = User::findOrFail($id);
        $seller->update(['status' => 'suspended']);

        return redirect()->back()->with('success', 'Seller suspended successfully.');
    }

    public function users()
    {
        $users = User::with('roles')->paginate(10);
        return view('admin.users.index', compact('users'));
    }

    public function products()
    {
        $products = Product::with(['category', 'user'])->paginate(10);
        return view('admin.products.index', compact('products'));
    }

    public function approveProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'active']);

        return redirect()->back()->with('success', 'Product approved successfully.');
    }

    public function rejectProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Product rejected successfully.');
    }

    public function orders()
    {
        $orders = Order::with(['user', 'items.product'])->paginate(10);
        return view('admin.orders.index', compact('orders'));
    }

    public function reports()
    {
        // Sales analytics, best-sellers, etc.
        return view('admin.reports.index');
    }

    public function settings()
    {
        return view('admin.settings.index');
    }
} 