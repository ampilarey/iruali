<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    public function create()
    {
        $user = Auth::user();

        if ($user->hasRole('seller')) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.apply', compact('user'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('seller')) {
            return redirect()->route('seller.dashboard');
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_description' => 'required|string|min:20|max:2000',
            'phone' => 'required|string|max:20|unique:users,phone,'.$user->id,
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'agree_seller_terms' => 'accepted',
        ], [
            'agree_seller_terms.accepted' => 'You must agree to the seller terms.',
            'business_description.min' => 'Please tell us a little more about what you sell (at least 20 characters).',
        ]);

        $role = Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller']);

        $user->forceFill([
            'business_name' => $validated['business_name'],
            'business_description' => $validated['business_description'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'state' => $validated['state'] ?? $user->state,
            'is_seller' => true,
            'seller_approved' => false,
            'seller_approved_at' => null,
            'seller_applied_at' => now(),
        ])->save();

        $user->roles()->syncWithoutDetaching([$role->id]);

        return redirect()->route('seller.dashboard')
            ->with('success', 'Application received! You can start adding products now; they go live once an admin approves your shop.');
    }
}
