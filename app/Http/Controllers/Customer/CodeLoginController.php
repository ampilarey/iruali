<?php

namespace App\Http\Controllers\Customer;

use App\Models\OTP;
use App\Models\User;
use App\Notifications\SignInCode;
use App\Services\NotificationService;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Sign in with a 6-digit code instead of a password: by SMS to a verified mobile number, or by
 * email. The page answers the same whether or not an account matches, so it can't be used to find
 * out who has an account. Codes last 10 minutes; at most one a minute and five an hour per number
 * or email, and five wrong tries before a new code is needed. Accounts with two-step sign-in still
 * give their authenticator code; staff sign in with their password.
 *
 * Signing in this way also lets the customer set a new password without the old one for 15
 * minutes (My Account → Security), which makes it the "forgot password" route by SMS too.
 */
class CodeLoginController extends AuthController
{
    public const SESSION = 'login_code';

    public const PASSWORD_WINDOW_MINUTES = 15;

    public function show(Request $request)
    {
        if ($request->boolean('change')) {
            $request->session()->forget(self::SESSION); // "Use another number or email"
        }

        return view('auth.login-code', [
            'pending' => $request->session()->get(self::SESSION),
            'smsAvailable' => $this->smsAvailable(),
        ]);
    }

    public function send(Request $request)
    {
        $request->validate(['login' => 'required|string|max:255']);
        $input = trim((string) $request->input('login'));

        if (str_contains($input, '@')) {
            $request->validate(['login' => 'email']);
            $target = mb_strtolower($input);
            $channel = 'mail';
            $user = User::where('email', $target)->first();
        } else {
            $target = PhoneNumber::normalize($input);
            if (! $target) {
                return back()->withInput()->withErrors(['login' => __('Enter your email, or a Maldivian mobile number (7 digits starting with 7 or 9).')]);
            }
            if (! $this->smsAvailable()) {
                return back()->withInput()->withErrors(['login' => __('Codes by text message are not available right now. Please use your email instead.')]);
            }
            $channel = 'sms';
            $user = User::findByVerifiedPhone($target);
        }

        $key = 'login-code:'.sha1($target);
        if (RateLimiter::tooManyAttempts($key.':m', 1) || RateLimiter::tooManyAttempts($key.':h', 5)) {
            return back()->withInput()->withErrors(['login' => __('Please wait a minute before asking for another code.')]);
        }
        RateLimiter::hit($key.':m', 60);
        RateLimiter::hit($key.':h', 3600);

        // Only a real, active customer account gets a code; the answer below is the same either way
        if ($user && ! $user->isStaff() && $user->isActive() && ! $user->isBanned()) {
            $otp = $channel === 'sms' ? OTP::createForPhone($target, 'login') : OTP::createForEmail($target, 'login');
            try {
                Notification::route($channel, $target)->notifyNow(new SignInCode($otp)); // now: they are waiting for it
            } catch (Throwable $e) {
                report($e);
            }
        }

        $request->session()->put(self::SESSION, [
            'target' => $target,
            'channel' => $channel,
            'shown' => $channel === 'sms' ? '•••'.substr($target, -4) : $this->maskEmail($target),
            'remember' => $request->boolean('remember'),
        ]);
        RateLimiter::clear($key.':tries');

        return redirect()->route('login.code')->with('status', $channel === 'sms'
            ? __('If that number belongs to an iruali account, we have texted it a code.')
            : __('If that email belongs to an iruali account, we have emailed it a code.'));
    }

    public function verify(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        if (! $pending) {
            return redirect()->route('login.code');
        }
        $request->validate(['code' => 'required|digits:6']);

        $tries = 'login-code:'.sha1($pending['target']).':tries';
        if (RateLimiter::tooManyAttempts($tries, 5)) {
            OTP::where($pending['channel'] === 'sms' ? 'phone' : 'email', $pending['target'])->where('purpose', 'login')->update(['is_used' => true]);
            $request->session()->forget(self::SESSION);

            return redirect()->route('login.code')->withErrors(['login' => __('Too many wrong codes. Please ask for a new one.')]);
        }

        $otp = OTP::verify($pending['target'], (string) $request->input('code'), 'login');
        $user = $otp ? ($pending['channel'] === 'sms' ? User::findByVerifiedPhone($pending['target']) : User::where('email', $pending['target'])->first()) : null;
        if (! $user || $user->isStaff()) {
            RateLimiter::hit($tries, 600);

            return back()->withErrors(['code' => __('auth.invalid_otp')]);
        }
        if ($user->isBanned() || ! $user->isActive()) {
            $request->session()->forget(self::SESSION);

            return redirect()->route('login')->withErrors(['email' => $user->isBanned() ? __('auth.account_banned', ['reason' => $user->banned_reason]) : __('auth.account_inactive')]);
        }

        RateLimiter::clear($tries);
        $request->session()->forget(self::SESSION);
        if ($pending['channel'] === 'mail' && ! $user->isEmailVerified()) {
            $user->forceFill(['email_verified_at' => now()])->save(); // the code reached that inbox
        }

        if ($user->isTwoFactorEnabled()) {
            session(['2fa_user_id' => $user->id, '2fa_remember' => (bool) $pending['remember']]);

            return redirect()->route('2fa.show');
        }

        Auth::login($user, (bool) $pending['remember']);
        $request->session()->regenerate();
        $request->session()->put('code_signed_in_at', now()->timestamp);
        $user->updateLoginTracking($request->ip());
        NotificationService::loginSuccess();

        return $this->redirectBasedOnRole($user);
    }

    /**
     * True when the customer signed in with a code in the last 15 minutes (a new password may then be
     * set without the old one).
     */
    public static function recentlySignedInWithCode(Request $request): bool
    {
        $at = (int) $request->session()->get('code_signed_in_at', 0);

        return $at > 0 && now()->timestamp - $at <= self::PASSWORD_WINDOW_MINUTES * 60;
    }

    protected function smsAvailable(): bool
    {
        return app(SmsManager::class)->isLive() && SmsManager::enabled();
    }

    protected function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 2).'•••@'.$domain;
    }
}
