<?php

namespace App\Http\Controllers\Customer;

use App\Models\OTP;
use App\Models\SmsMessage;
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
 * Sign in with a mobile number or an email (as on Bake & Grill). An account with a password signs
 * in with it, or asks for a code by email. To save SMS, a text message is sent only when nothing
 * else will do: a new number (it gets an account on the spot, no email needed, then "set your name
 * and password", which can wait during checkout), "forgot password", and an account without a
 * password or an email. A number already on an account can only be used with its code or
 * password. Staff use email and password (/login/email).
 *
 * The same steps run inside checkout (CheckoutController::signIn, as on Bake & Grill): they come
 * back to checkout, a registered customer may also ask for a code by SMS there, and setting a
 * name and password waits until the next sign-in (checkout asks for the name if it is missing).
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
     * Step 1: the number or email. Password if the account has one, otherwise a code.
     */
    public function start(Request $request)
    {
        $raw = trim((string) $request->input('login', $request->input('phone')));
        if ($raw === '' || mb_strlen($raw) > 255) {
            return back()->withInput()->withErrors(['login' => __('Enter your mobile number or email.')]);
        }
        $remember = $request->boolean('remember');

        if (str_contains($raw, '@')) {
            $email = mb_strtolower($raw);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return back()->withInput()->withErrors(['login' => __('Enter a valid email address.')]);
            }
            $user = User::where('email', $email)->first();
            if (! $user) {
                return back()->withInput()->withErrors(['login' => __('No account uses that email. New to iruali? Continue with your mobile number.')]);
            }
            if ($user->isStaff()) {
                // Staff type their password right here; it goes to the staff sign-in (AuthController::login:
                // lockout after wrong tries, then the authenticator code). Never a code by text or email.
                $request->session()->put(self::SESSION, ['step' => 'password', 'staff' => true, 'email' => $email, 'key' => $email, 'shown' => $email, 'remember' => $remember, 'checkout' => $request->boolean('checkout')]);

                return $this->home(['checkout' => $request->boolean('checkout')]);
            }
            $pending = ['user_id' => $user->id, 'key' => $email, 'shown' => $this->maskEmail($email), 'remember' => $remember, 'checkout' => $request->boolean('checkout')];
        } else {
            $phone = PhoneNumber::normalize($raw);
            if (! $phone) {
                return back()->withInput()->withErrors(['login' => __('Enter a Maldivian mobile number (7 digits starting with 7 or 9), or your email.')]);
            }
            $user = User::findByVerifiedPhone($phone);
            if ($user?->isStaff()) {
                return back()->withInput()->withErrors(['login' => __('Staff sign in with their email and password.')]);
            }
            $pending = ['user_id' => $user?->id, 'phone' => $phone, 'key' => $phone, 'shown' => '•••'.substr($phone, -4), 'remember' => $remember, 'checkout' => $request->boolean('checkout')];
        }

        if ($user && $user->hasPassword()) {
            $request->session()->put(self::SESSION, $pending + ['step' => 'password', 'email_code' => filled($user->email), 'sms_code' => $user->isPhoneVerified() && filled($user->phone)]);

            return $this->home($pending);
        }

        // No password: a code by email when the account has one (saves an SMS), else by SMS
        return $this->sendCode($request, $pending, $user && filled($user->email) ? 'mail' : 'sms', 'login');
    }

    /**
     * Step 2a: the password.
     */
    public function password(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        if (($pending['step'] ?? null) !== 'password') {
            return $this->home($pending);
        }
        $request->validate(['password' => 'required|string']);

        $tries = 'phone-login:'.sha1($pending['key']).':password';
        if (RateLimiter::tooManyAttempts($tries, 5)) {
            return back()->withErrors(['password' => __('Too many wrong tries. Use "Forgot password?" to get a code, or wait a few minutes.')]);
        }
        $user = $this->pendingUser($pending);
        if (! $user || $user->isStaff() || ! $user->hasPassword() || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($tries, 600);

            return back()->withErrors(['password' => __('That password is not right.')]);
        }
        RateLimiter::clear($tries);

        return $this->signIn($request, $user, (bool) $pending['remember'], false, false, (bool) ($pending['checkout'] ?? false));
    }

    /**
     * "Email me a code instead" (from the password step): no SMS needed.
     */
    public function emailCode(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        $user = $pending ? $this->pendingUser($pending) : null;
        if (! $user || blank($user->email)) {
            return $this->home($pending);
        }

        return $this->sendCode($request, $pending, 'mail', 'login');
    }

    /**
     * "Text me a code instead" of the password: only inside checkout, where a quick code is worth
     * an SMS (anywhere else a registered customer uses the password or an email code).
     */
    public function smsCode(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        $user = $pending ? $this->pendingUser($pending) : null;
        if (! $user || ! ($pending['checkout'] ?? false)) {
            return $this->home($pending);
        }
        if (! $user->isPhoneVerified() || blank($user->phone)) {
            return $this->home($pending)->withErrors(['login' => __('This account has no verified mobile number. Use your password, or ask for a code by email.')]);
        }

        return $this->sendCode($request, $pending, 'sms', 'login');
    }

    /**
     * "Forgot password?" (an SMS to the account's verified number, else an email) or "Send a new
     * code" (the same way as before).
     */
    public function resend(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        if (! $pending) {
            return $this->home(null);
        }
        if (($pending['step'] ?? null) === 'password') {
            $user = $this->pendingUser($pending);

            return $this->sendCode($request, $pending, $user?->isPhoneVerified() && $user->phone ? 'sms' : 'mail', 'reset');
        }

        return $this->sendCode($request, $pending, $pending['channel'] ?? 'sms', $pending['purpose'] ?? 'login');
    }

    /**
     * Step 2b: the code. A new number becomes an account here.
     */
    public function verify(Request $request)
    {
        $pending = $request->session()->get(self::SESSION);
        if (($pending['step'] ?? null) !== 'code') {
            return $this->home($pending);
        }
        $request->validate(['code' => 'required|digits:6']);

        $tries = 'phone-login:'.sha1($pending['key']).':tries';
        if (RateLimiter::tooManyAttempts($tries, 5)) {
            OTP::where($pending['channel'] === 'mail' ? 'email' : 'phone', $pending['target'])->where('purpose', 'login')->update(['is_used' => true]);
            $request->session()->forget(self::SESSION);

            return $this->home($pending)->withErrors(['login' => __('Too many wrong codes. Please ask for a new one.')]);
        }
        if (! OTP::verify($pending['target'], (string) $request->input('code'), 'login')) {
            RateLimiter::hit($tries, 600);

            return back()->withErrors(['code' => __('auth.invalid_otp')]);
        }
        RateLimiter::clear($tries);

        $user = $this->pendingUser($pending);
        if ($user?->isStaff()) {
            $request->session()->forget(self::SESSION);

            return redirect()->route('login.email')->withErrors(['email' => __('Staff sign in with their email and password.')]);
        }
        if (! $user) {
            if ($pending['channel'] !== 'sms' || empty($pending['phone'])) {
                return $this->home($pending);
            }
            $user = $this->createAccount($pending['phone']);
        }
        if ($pending['channel'] === 'mail' && ! $user->isEmailVerified()) {
            $user->forceFill(['email_verified_at' => now()])->save(); // the code reached that inbox
        }

        return $this->signIn($request, $user, (bool) $pending['remember'], true, ($pending['purpose'] ?? 'login') === 'reset', (bool) ($pending['checkout'] ?? false));
    }

    /**
     * Where the steps are shown: checkout when they started there, else the sign-in page.
     *
     * @param  array<string, mixed>|null  $pending
     */
    protected function home(?array $pending): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route(($pending['checkout'] ?? false) ? 'checkout' : 'login');
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    protected function pendingUser(array $pending): ?User
    {
        if (! empty($pending['user_id'])) {
            return User::find($pending['user_id']);
        }

        return ! empty($pending['phone']) ? User::findByVerifiedPhone($pending['phone']) : null;
    }

    protected function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 2).'•••@'.$domain;
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

    protected function signIn(Request $request, User $user, bool $remember, bool $byCode, bool $resettingPassword = false, bool $atCheckout = false)
    {
        if ($user->isBanned() || ! $user->isActive()) {
            $request->session()->forget(self::SESSION);

            return redirect()->route($atCheckout ? 'checkout' : 'login')->withErrors(['login' => $user->isBanned() ? __('auth.account_banned', ['reason' => $user->banned_reason]) : __('auth.account_inactive')]);
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
        // At checkout: straight back to the order. A name and password can wait (asked at the next sign-in)
        if ($atCheckout) {
            return redirect()->route('checkout');
        }
        // No name or password yet: set them up now
        if (trim((string) $user->name) === '' || ! $user->hasPassword()) {
            return redirect()->route('account.setup');
        }

        return $this->redirectBasedOnRole($user);
    }

    /**
     * A 6-digit code by SMS (to the number typed or the account's verified number) or by email
     * (to the account's email).
     *
     * @param  array<string, mixed>  $pending
     */
    protected function sendCode(Request $request, array $pending, string $channel, string $purpose)
    {
        $user = $this->pendingUser($pending);
        if ($channel === 'mail') {
            $target = $user && filled($user->email) ? mb_strtolower((string) $user->email) : null;
        } else {
            $target = $pending['phone'] ?? ($user?->isPhoneVerified() ? PhoneNumber::normalize((string) $user->phone) : null);
        }
        if (! $target) {
            return $this->home($pending)->withErrors(['login' => __('We could not send a code. Please try again.')]);
        }
        if ($channel === 'sms' && ! $this->smsAvailable()) {
            return back()->withInput()->withErrors(['login' => $user && filled($user->email)
                ? __('Codes by text message are not available right now. Please ask for a code by email.')
                : __('Codes by text message are not available right now. Please try again later.')]);
        }
        $key = 'phone-login:'.sha1($target);
        if (RateLimiter::tooManyAttempts($key.':m', 1) || RateLimiter::tooManyAttempts($key.':h', 5)) {
            return back()->withInput()->withErrors(['login' => __('Please wait a minute before asking for another code.')]);
        }
        RateLimiter::hit($key.':m', 60);
        RateLimiter::hit($key.':h', 3600);

        $otp = $channel === 'mail' ? OTP::createForEmail($target, 'login') : OTP::createForPhone($target, 'login');
        $lastSms = (int) SmsMessage::max('id');
        try {
            Notification::route($channel, $target)->notifyNow(new SignInCode($otp)); // now: they are waiting for it
            $sent = $channel === 'mail' || SmsMessage::where('id', '>', $lastSms)->where('to', $target)->latest('id')->value('status') !== 'failed';
        } catch (Throwable $e) {
            report($e);
            $sent = false;
        }
        if (! $sent) {
            // The gateway refused or could not be reached: say so (and let them try again at once)
            $otp->forceFill(['is_used' => true])->save();
            RateLimiter::clear($key.':m');

            return $this->home($pending)->withErrors(['login' => $channel === 'sms' && $user && filled($user->email)
                ? __('We could not send the text message just now. Please try again, or ask for a code by email.')
                : __('We could not send the code just now. Please try again in a minute.')]);
        }
        $shown = $channel === 'mail' ? $this->maskEmail($target) : '•••'.substr($target, -4);
        $request->session()->put(self::SESSION, ['step' => 'code', 'channel' => $channel, 'target' => $target, 'purpose' => $purpose, 'shown_to' => $shown] + $pending);
        RateLimiter::clear('phone-login:'.sha1($pending['key']).':tries');

        return $this->home($pending)->with('status', $channel === 'mail'
            ? __('We have emailed a 6-digit code to :to.', ['to' => $shown])
            : __('We have texted a 6-digit code to :to.', ['to' => $shown]));
    }

    protected function smsAvailable(): bool
    {
        return app(SmsManager::class)->isLive() && SmsManager::enabled();
    }
}
