<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * "Forgot your password?": emails a signed reset link, then sets the new password.
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Same reply whether or not the address exists, so the form can't be used to find accounts.
        return back()->with('status', __('If an account exists for that email, we have sent a link to reset the password.'));
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $resetUserId = null;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) use (&$resetUserId) {
                // has_password: an account made with Google, Facebook or Apple has one of its own now
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60), 'has_password' => true])->save();
                $user->tokens()->delete(); // sign out any app sessions too
                event(new PasswordReset($user));
                $resetUserId = $user->id;
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        // Set from My Account while signed in (a first password): back to the account page
        if ($resetUserId !== null && Auth::id() === $resetUserId) {
            NotificationService::success(__('Your password has been changed.'));

            return redirect()->to(route('account').'#security');
        }

        NotificationService::success(__('Your password has been changed. Please sign in.'));

        return redirect()->route('login');
    }
}
