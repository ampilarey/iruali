<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class SellerController extends Controller
{
    public function showRegistration()
    {
        return view('seller.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'business_name' => 'required|string|max:255',
            'business_address' => 'required|string',
            'business_description' => 'required|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'business_name' => $request->business_name,
            'business_address' => $request->business_address,
            'business_description' => $request->business_description,
            'status' => 'pending_approval',
        ]);

        // Assign seller role
        $sellerRole = Role::where('name', 'seller')->first();
        $user->roles()->attach($sellerRole->id);

        return redirect()->route('seller.register')
            ->with('success', 'Seller registration submitted successfully. Please wait for admin approval.');
    }

    public function dashboard()
    {
        $user = Auth::user();
        
        if (!$user->hasRole('seller')) {
            abort(403, 'Access denied. Seller role required.');
        }

        $stats = [
            'total_products' => $user->products()->count(),
            'total_orders' => $user->orders()->count(),
            'total_revenue' => $user->orders()->where('status', 'delivered')->sum('total_amount'),
            'pending_orders' => $user->orders()->where('status', 'pending')->count(),
        ];

        $recent_orders = $user->orders()->with('items.product')->latest()->take(5)->get();
        $recent_products = $user->products()->latest()->take(5)->get();

        return view('seller.dashboard', compact('stats', 'recent_orders', 'recent_products'));
    }

    public function profile()
    {
        $user = Auth::user();
        return view('seller.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'business_name' => 'required|string|max:255',
            'business_address' => 'required|string',
            'business_description' => 'required|string',
        ]);

        $user->update($request->only([
            'name', 'phone', 'business_name', 'business_address', 'business_description'
        ]));

        return redirect()->route('seller.profile')
            ->with('success', 'Profile updated successfully.');
    }
} 