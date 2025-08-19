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
    }

    /**
     * Check if the authenticated user has admin role
     */
    private function checkAdminRole()
    {
        if (!auth()->check() || !auth()->user()->hasRole('admin')) {
            abort(403, 'Access denied. Admin role required.');
        }
    }

    public function dashboard()
    {
        $this->checkAdminRole();
        
        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::whereHas('roles', function($q) {
                $q->where('name', 'seller');
            })->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'pending_sellers' => User::where('status', 'inactive')->count(),
            'pending_products' => Product::where('is_active', false)->count(),
        ];

        $recent_users = User::latest()->take(5)->get();
        $recent_orders = Order::with('user')->latest()->take(5)->get();
        $pending_sellers = User::where('status', 'inactive')->get();

        return view('admin.dashboard', compact('stats', 'recent_users', 'recent_orders', 'pending_sellers'));
    }

    public function sellers()
    {
        $this->checkAdminRole();
        
        $sellers = User::whereHas('roles', function($q) {
            $q->where('name', 'seller');
        })->with('roles')->paginate(10);

        return view('admin.sellers.index', compact('sellers'));
    }

    public function approveSeller($id)
    {
        $this->checkAdminRole();
        
        $seller = User::findOrFail($id);
        $seller->update(['status' => 'active']);

        return redirect()->back()->with('success', 'Seller approved successfully.');
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
        
        $products = Product::with(['category', 'user'])->paginate(10);
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
        
        $orders = Order::with(['user', 'items.product'])->paginate(10);
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
        
        // Analytics data
        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::whereHas('roles', function($q) {
                $q->where('name', 'seller');
            })->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
        ];
        
        return view('admin.analytics.index', compact('stats'));
    }

    public function settings()
    {
        $this->checkAdminRole();
        
        return view('admin.settings.index');
    }
} 