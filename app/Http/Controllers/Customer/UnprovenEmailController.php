<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Someone proved an email (a code reached the inbox) that is typed on an account they may not own:
 * the account signs in with a confirmed mobile number, and the email on it was never confirmed
 * (User::emailIsUnproven()). They are not signed in to it. If it is their account, they sign in with
 * its mobile number and confirm the email there; if it is not, the email comes off that account so
 * they can open their own.
 */
class UnprovenEmailController extends Controller
{
    public const SESSION = 'proved_email';

    /** Called by the sign-in code checks: remember what was proved, for this page only. */
    public static function hold(Request $request, User $account, string $email): RedirectResponse
    {
        $request->session()->put(self::SESSION, ['user_id' => $account->id, 'email' => mb_strtolower(trim($email)), 'at' => now()->timestamp]);

        return redirect()->route('login.email-taken');
    }

    public function show(Request $request)
    {
        [$account, $email] = $this->proof($request);
        if (! $account) {
            return redirect()->route('login');
        }

        return view('auth.email-taken', ['email' => $email, 'phone' => '•••'.substr((string) $account->phone, -4)]);
    }

    public function release(Request $request): RedirectResponse
    {
        [$account, $email] = $this->proof($request);
        $request->session()->forget(self::SESSION);
        if (! $account) {
            return redirect()->route('login');
        }
        $account->releaseUnprovenEmail();

        return redirect()->route('register')->withInput(['email' => $email])
            ->with('status', __('Your email is free to use now. Create your account with it.'));
    }

    /** @return array{0: ?User, 1: string} */
    protected function proof(Request $request): array
    {
        $held = (array) $request->session()->get(self::SESSION, []);
        $account = isset($held['user_id']) ? User::find($held['user_id']) : null;
        $fresh = (int) ($held['at'] ?? 0) > now()->subMinutes(15)->timestamp;
        // Still the same unconfirmed email on that account, proved in the last 15 minutes
        if (! $account || ! $fresh || mb_strtolower((string) $account->email) !== ($held['email'] ?? '') || ! $account->emailIsUnproven()) {
            return [null, ''];
        }

        return [$account, (string) $held['email']];
    }
}
