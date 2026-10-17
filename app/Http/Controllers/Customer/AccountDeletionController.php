<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerAccountService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * My Account → Security → Delete my account: a customer erases their own personal data (orders
 * stay for the records, without their details) and is signed out. Needs the password, or a code
 * sign-in a moment ago; not for shop or staff accounts, and not while an order or refund is open.
 */
class AccountDeletionController extends Controller
{
    public function destroy(Request $request, CustomerAccountService $accounts)
    {
        $user = $request->user();
        $needsPassword = $user->hasPassword() && ! CodeLoginController::recentlySignedInWithCode($request);
        $request->validateWithBag('deleteAccount', [
            'password' => $needsPassword ? 'required|current_password' : 'nullable',
            'confirm' => 'accepted',
        ]);

        if ($accounts->notACustomer($user)) {
            NotificationService::error(__('Shop and staff accounts are closed by the iruali team. Please contact us.'));

            return redirect()->to(route('account').'#security');
        }
        if ($problem = $accounts->erase($user, null, 'Deleted by the customer from My Account')) {
            NotificationService::error($problem);

            return redirect()->to(route('account').'#security');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        NotificationService::success(__('Your account has been deleted. Thank you for shopping with iruali.'));

        return redirect()->route('home');
    }
}
