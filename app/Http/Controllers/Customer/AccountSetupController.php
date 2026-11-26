<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * After the first sign-in with a mobile number: the customer's name, a password for next time and,
 * if they like, an email. The password can wait ("Later", e.g. in the middle of checkout); it is
 * asked again at the next sign-in until set.
 */
class AccountSetupController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.account-setup', ['user' => $request->user()]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        // Only to finish a new account: a first password and a first email, straight after signing in
        // with a code. Anyone with the device later needs the current password (My Account → Security)
        if ($user->hasPassword() || ! CodeLoginController::recentlySignedInWithCode($request)) {
            return redirect()->route('account.edit')->withErrors(['email' => __('To change your password, use My Account → Security.')]);
        }
        $later = $request->input('action') === 'later';
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => $later ? ['nullable'] : ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ], ['email.unique' => __('That email is already on another account. Sign in with it, or leave this empty.')]);

        $changes = ['name' => trim($data['name'])];
        if (filled($data['email'] ?? null) && blank($user->email)) {
            $changes += ['email' => mb_strtolower(trim($data['email'])), 'email_verified_at' => null];
        }
        if (! $later) {
            $changes += ['password' => Hash::make((string) $data['password']), 'has_password' => true];
        }
        $user->forceFill($changes)->save();
        $request->session()->forget('code_signed_in_at'); // used once
        if (isset($changes['email'])) {
            app(AuthController::class)->sendEmailCodeTo($user); // confirm it now (User::claimByEmailProof())
        }

        NotificationService::success($later ? __('Saved. You can set a password any time in My Account.') : __('All set. Next time, sign in with your mobile number and this password.'));

        return redirect()->intended(route('home'));
    }
}
