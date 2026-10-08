<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Opting out of marketing email (abandoned-cart nudges and the like). The link in the email is
 * signed, so it works without signing in; the account page has a switch for the same preference.
 */
class MarketingController extends Controller
{
    public function unsubscribe(Request $request, int $user)
    {
        $row = User::find($user);
        if ($row && ! $row->marketing_opt_out_at) {
            $row->forceFill(['marketing_opt_out_at' => now()])->save();
        }

        return view('marketing.unsubscribed', ['email' => $row->email ?? '']);
    }

    /**
     * Account page switch: on or off.
     */
    public function update(Request $request)
    {
        $on = $request->boolean('marketing_emails');
        $request->user()->forceFill(['marketing_opt_out_at' => $on ? null : now()])->save();

        NotificationService::success($on ? __('You will receive our offers and reminders by email.') : __('You will no longer receive marketing emails.'));

        return back();
    }
}
