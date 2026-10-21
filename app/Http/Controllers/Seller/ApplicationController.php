<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SellerWaitlist;
use App\Models\User;
use App\Services\SellerWaitlistService;
use App\Support\CurrentShop;
use App\Support\SellerApplications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Sell on iruali": the application form, or, while new shop applications are paused or hidden
 * (App\Support\SellerApplications), the admins' message with "Tell me when you reopen". The page
 * answers guests too, so nobody signs up only to find applications closed.
 */
class ApplicationController extends Controller
{
    public function create()
    {
        $user = Auth::user();

        if ($user && ($team = $this->teamAccount($user))) {
            return $team;
        }
        if ($user?->hasRole('seller')) {
            return redirect()->route('seller.dashboard');
        }
        if ($user && ($staffOf = $this->staffOf($user))) {
            return $staffOf;
        }
        if (! SellerApplications::open()) {
            return view('seller.applications-closed', [
                'user' => $user,
                'message' => SellerApplications::message(),
                'reopensOn' => SellerApplications::reopensOn(),
                'onList' => $user?->email && SellerWaitlist::where('email', mb_strtolower($user->email))->waiting()->exists(),
            ]);
        }
        if (! $user) {
            return redirect()->guest(route('login'));
        }

        $islandsByAtoll = app(\App\Services\DeliveryService::class)->islandsByAtoll();

        return view('seller.apply', compact('user', 'islandsByAtoll'));
    }

    /**
     * "Tell me when you reopen": signed-in people are told at their account's email, guests give one.
     */
    public function waitlist(Request $request, SellerWaitlistService $waitlist): RedirectResponse
    {
        if (SellerApplications::open()) {
            return redirect()->route('seller.apply');
        }

        $user = Auth::user();
        $data = $request->validate([
            'email' => [$user?->email ? 'nullable' : 'required', 'email', 'max:190'],
        ], [
            'email.required' => __('Enter your email so we can tell you when applications reopen.'),
        ]);
        $email = $user?->email ?: $data['email'];
        $waitlist->join($email, $user);

        return redirect()->route('seller.apply')
            ->with('success', __('Thank you. We will email :email when iruali takes new shops again.', ['email' => $email]));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($team = $this->teamAccount($user)) {
            return $team;
        }
        if ($user->hasRole('seller')) {
            return redirect()->route('seller.dashboard');
        }
        if ($staffOf = $this->staffOf($user)) {
            return $staffOf;
        }
        if (! SellerApplications::open()) {
            return redirect()->route('seller.apply')
                ->with('error', __('iruali is not taking new shops right now, so your application was not sent.'));
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_description' => 'required|string|min:20|max:2000',
            'phone' => 'required|string|max:20|unique:users,phone,'.$user->id,
            'address' => 'required|string|max:500',
            'agree_seller_terms' => 'accepted',
        ] + \App\Services\ShopCoverage::rules(true), \App\Services\ShopCoverage::messages() + [
            'agree_seller_terms.accepted' => 'You must agree to the seller terms.',
            'business_description.min' => 'Please tell us a little more about what you sell (at least 20 characters).',
        ]);
        // Optional: business documents for the "Verified business" badge (all of them, or none)
        $verification = app(\App\Services\SellerVerificationService::class)->validateApplication($request);

        $role = Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller']);
        // The home island picked from the list also fills the old free-text island and atoll
        $home = \App\Models\Island::findOrFail((int) $validated['home_island_id']);

        $user->forceFill([
            'business_name' => $validated['business_name'],
            'business_description' => $validated['business_description'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'city' => $home->englishName(),
            'state' => $home->atoll ?: $user->state,
            'is_seller' => true,
            'seller_approved' => false,
            'seller_approved_at' => null,
            'seller_applied_at' => now(),
        ])->save();

        app(\App\Services\ShopCoverage::class)->save($user, $validated);
        $user->roles()->syncWithoutDetaching([$role->id]);
        if ($verification) {
            app(\App\Services\SellerVerificationService::class)->submit($user, $verification);
        }
        \App\Support\TeamAlerts::send('shop_application', 'iruali: new shop application: '.$validated['business_name'].' ('.$home->englishName().').', route('admin.sellers'));

        return redirect()->route('seller.dashboard')
            ->with('success', 'Application received! You can start adding products now; they go live once an admin approves your shop.');
    }

    /**
     * iruali's own team (admin, support, finance, catalogue) runs the website and never sells: iruali
     * sells through a shop account of its own, like any shop (StaffRoleController keeps it the other
     * way round: a shop account is never made staff).
     */
    protected function teamAccount(User $user): ?RedirectResponse
    {
        if (! $user->isStaff()) {
            return null;
        }

        return redirect()->route('home')->with('error', __('This is an iruali team account, for running the website, so it cannot open a shop. To sell on iruali, open the shop with a separate account (its own email and mobile number).'));
    }

    /**
     * One shop per account: someone on a shop's staff opens a shop of their own with another account.
     */
    protected function staffOf(User $user): ?RedirectResponse
    {
        $membership = CurrentShop::membershipOf($user);
        if (! $membership) {
            return null;
        }

        return redirect()->route('seller.dashboard')->with('error', __('This account works for :shop, so it cannot open a shop of its own. To sell on iruali yourself, sign up with a different email.', ['shop' => $membership->shop?->shopName()]));
    }
}
