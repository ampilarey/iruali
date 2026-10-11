<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerWaitlist;
use App\Models\Setting;
use App\Models\User;
use App\Services\SellerWaitlistService;
use App\Support\Audit;
use App\Support\SellerApplications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Sellers → New shop applications: open, paused or hidden (App\Support\SellerApplications),
 * with an optional reopening day and the message would-be sellers see. Admins only.
 */
class SellerApplicationsController extends Controller
{
    public function edit()
    {
        return view('admin.sellers.applications', [
            'stored' => SellerApplications::stored(),
            'status' => SellerApplications::status(),
            'reopensOn' => SellerApplications::reopensOn(),
            'messageEn' => (string) Setting::get('seller_applications_message_en', ''),
            'messageDv' => (string) Setting::get('seller_applications_message_dv', ''),
            'waiting' => SellerWaitlist::waiting()->count(),
            'pending' => User::where('is_seller', true)->where('seller_approved', false)->whereHas('roles', fn ($q) => $q->where('name', 'seller'))->count(),
        ]);
    }

    public function update(Request $request, SellerWaitlistService $waitlist): RedirectResponse
    {
        $data = $request->validate([
            'seller_applications' => ['required', Rule::in(SellerApplications::STATUSES)],
            'seller_applications_until' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'seller_applications_message_en' => ['nullable', 'string', 'max:300'],
            'seller_applications_message_dv' => ['nullable', 'string', 'max:300'],
        ], [
            'seller_applications_until.after' => 'Pick a day after today, or leave it empty to reopen them yourself.',
        ]);

        $status = $data['seller_applications'];
        $until = $status === SellerApplications::OPEN ? '' : ($data['seller_applications_until'] ?? '');
        Setting::set([
            'seller_applications' => $status,
            'seller_applications_until' => $until,
            'seller_applications_message_en' => trim((string) ($data['seller_applications_message_en'] ?? '')),
            'seller_applications_message_dv' => trim((string) ($data['seller_applications_message_dv'] ?? '')),
        ]);
        Audit::record('seller_applications.saved', null, array_filter(['status' => $status, 'reopens' => $until]));

        if ($status === SellerApplications::OPEN) {
            $told = $waitlist->notifyAll();

            return redirect()->route('admin.seller-applications')->with('success', $told > 0
                ? "New shop applications are open. {$told} ".($told === 1 ? 'person' : 'people').' on the waiting list will get an email.'
                : 'New shop applications are open.');
        }

        $what = $status === SellerApplications::HIDDEN ? 'hidden: the "Sell on iruali" links are off the site' : 'paused: "Sell on iruali" shows your message';

        return redirect()->route('admin.seller-applications')
            ->with('success', "New shop applications are {$what}".($until !== '' ? ', until '.SellerApplications::reopensOn()?->format('j M Y').'.' : '.'));
    }
}
