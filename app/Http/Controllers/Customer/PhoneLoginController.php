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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Continue with your mobile number" (as on Bake & Grill): the customer types a Maldivian mobile
 * number; an account with a password signs in with it, anyone else gets a 6-digit SMS code. A new
 * number gets an account on the spot (no email needed), then "set your name and password"
 * (AccountSetupController), which can wait during checkout. "Forgot password" sends a code. A
 * number already on an account can only be used with its code or password. Staff use email and
 * password.
 */
class PhoneLoginController extends AuthController
{
    public const SESSION = 'phone_login';

    public function show(Request $request)
    {
        if ($request->boolean('change')) {
            $request->session()->forget(self::SESSION);
        }

        return view('auth.login-phone', [
            'pending' => $request->session()->get(self::SESSION),
            'smsAvailable' => $this->smsAvailable(),
        ]);
    }

    /**
     * Step 1: the number. Password if the account has one, otherwise a code.
     */
    public function start(Request $request)
    {
        $request->validate(['phone' => 'required|string|max:20']);
        $phone = PhoneNumber::normalize((string) $request->input('phone'));
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => __('Enter a Maldivian mobile number (7 digits starting with 7 or 9).')]);
        }

        $user = User::findByVerifiedPhone($phone);
        if ($user?->isStaff()) {
            return back()->withInput()->withErrors(['phone' => __('Staff sign in with their email and password.')]);
        }

        $pending = ['phone' => $phone, 'shown' => '•••'.substr($phone, -4), 'remember' => $request->boolean('remember')];
        if ($user && $user->hasPassword()) {
            $request->session()->put(self::SESSION, $pending + ['step' => 'password']);

            return redirect()->route('login.phone');
        }

        return $this->sendCode($request, $pending + ['purpose' => 'login']);
    }

    /**
     * Step 2a: the password.
     */
    public function password(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        if (($pending['step'] ?? null) !== 'password') {
            return redirect()->route('login.phone');
        }
        $request->validate(['password' => 'required|string']);

        $tries = 'phone-login:'.sha1($pending['phone']).':password';
        if (RateLimiter::tooManyAttempts($tries, 5)) {
            return back()->withErrors(['password' => __('Too many wrong tries. Use "Forgot password?" to get a code, or wait a few minutes.')]);
        }
        $user = User::findByVerifiedPhone($pending['phone']);
        if (! $user || $user->isStaff() || ! $user->hasPassword() || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($tries, 600);

            return back()->withErrors(['password' => __('That password is not right.')]);
        }
        RateLimiter::clear($tries);

        return $this->signIn($request, $user, (bool) $pending['remember'], false);
    }

    /**
     * "Forgot password?" or "Send a new code": a code to the number.
     */
    public function resend(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        if (! $pending) {
            return redirect()->route('login.phone');
        }

        return $this->sendCode($request, $pending + ['purpose' => ($pending['step'] ?? null) === 'password' ? 'reset' : ($pending['purpose'] ?? 'login')]);
    }

    /**
     * Step 2b: the code. A new number becomes an account here.
     */
    public function verify(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        if (($pending['step'] ?? null) !== 'code') {
            return redirect()->route('login.phone');
        }
        $request->validate(['code' => 'required|digits:6']);

        $tries = 'phone-login:'.sha1($pending['phone']).':tries';
        if (RateLimiter::tooManyAttempts($tries, 5)) {
            OTP::where('phone', $pending['phone'])->where('purpose', 'login')->update(['is_used' => true]);
            $request->session()->forget(self::SESSION);

            return redirect()->route('login.phone')->withErrors(['phone' => __('Too many wrong codes. Please ask for a new one.')]);
        }
        if (! OTP::verify($pending['phone'], (string) $request->input('code'), 'login')) {
            RateLimiter::hit($tries, 600);

            return back()->withErrors(['code' => __('auth.invalid_otp')]);
        }
        RateLimiter::clear($tries);

        $user = User::findByVerifiedPhone($pending['phone']);
        if ($user?->isStaff()) {
            $request->session()->forget(self::SESSION);

            return redirect()->route('login')->withErrors(['email' => __('Staff sign in with their email and password.')]);
        }
        if (! $user) {
            $user = $this->createAccount($pending['phone']);
        }

        return $this->signIn($request, $user, (bool) $pending['remember'], true, ($pending['purpose'] ?? 'login') === 'reset');
    }

    /**
     * A new customer from a verified number. The number comes off any account that typed it but
     * never verified it: the code proves who it belongs to.
     */
    protected function createAccount(string $phone): User
    {
        User::whereNull('phone_verified_at')->whereNotNull('phone')->get(['id', 'phone'])
            ->filter(fn (User $u) => PhoneNumber::normalize($u->phone) === $phone)
            ->each(fn (User $u) => $u->forceFill(['phone' => null])->save());

        // Like a sign-up with email: a referral code of their own, and the friend's code from a shared /r/{code} link
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());
        $shared = strtoupper((string) request()->cookie(RewardsController::COOKIE, ''));
        $referrer = $shared !== '' ? User::where('referral_code', $shared)->first() : null;

        $user = new User(['name' => '', 'phone' => $phone]);
        $user->forceFill(['referral_code' => $code, 'referred_by' => $referrer?->id, 'preferred_language' => app()->getLocale(), 'email' => null, 'phone_verified_at' => now(), 'password' => Hash::make(Str::random(40)), 'has_password' => false, 'is_active' => true])->save();
        $user->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer'])->id);

        return $user;
    }

    protected function signIn(Request $request, User $user, bool $remember, bool $byCode, bool $resettingPassword = false)
    {
        if ($user->isBanned() || ! $user->isActive()) {
            $request->session()->forget(self::SESSION);

            return redirect()->route('login.phone')->withErrors(['phone' => $user->isBanned() ? __('auth.account_banned', ['reason' => $user->banned_reason]) : __('auth.account_inactive')]);
        }
        $request->session()->forget(self::SESSION);

        if ($user->isTwoFactorEnabled()) {
            session(['2fa_user_id' => $user->id, '2fa_remember' => $remember]);

            return redirect()->route('2fa.show');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        if ($byCode) {
            $request->session()->put('code_signed_in_at', now()->timestamp); // a new password without the old one, for 15 minutes
        }
        $user->updateLoginTracking($request->ip());
        NotificationService::loginSuccess();

        if ($resettingPassword) {
            return redirect()->to(route('account').'#security')->with('success', __('You are signed in. Set a new password below.'));
        }
        // No name or password yet: set them up now (it can wait during checkout)
        if (trim((string) $user->name) === '' || ! $user->hasPassword()) {
            return redirect()->route('account.setup');
        }

        return $this->redirectBasedOnRole($user);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    protected function sendCode(Request $request, array $pending)
    {
        if (! $this->smsAvailable()) {
            return back()->withInput()->withErrors(['phone' => __('Codes by text message are not available right now. Please sign in with your email instead.')]);
        }
        $key = 'phone-login:'.sha1($pending['phone']);
        if (RateLimiter::tooManyAttempts($key.':m', 1) || RateLimiter::tooManyAttempts($key.':h', 5)) {
            return back()->withInput()->withErrors(['phone' => __('Please wait a minute before asking for another code.')]);
        }
        RateLimiter::hit($key.':m', 60);
        RateLimiter::hit($key.':h', 3600);

        $otp = OTP::createForPhone($pending['phone'], 'login');
        try {
            Notification::route('sms', $pending['phone'])->notifyNow(new SignInCode($otp)); // now: they are waiting for it
        } catch (Throwable $e) {
            report($e);
        }
        $request->session()->put(self::SESSION, ['step' => 'code'] + $pending);
        RateLimiter::clear($key.':tries');

        return redirect()->route('login.phone')->with('status', __('We have texted a 6-digit code to :to.', ['to' => $pending['shown']]));
    }

    protected function smsAvailable(): bool
    {
        return app(SmsManager::class)->isLive() && SmsManager::enabled();
    }
}
