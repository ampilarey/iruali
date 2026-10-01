<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\OTP;
use App\Models\Role;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Notifications\VerifyPhoneCode;
use App\Services\NotificationService;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * Sign up, sign in (with two-step codes) and email verification.
 */
class AuthController extends Controller
{
    public function __construct(protected Google2FA $google2fa, protected SmsManager $sms) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Create the account, sign the customer in and email them a verification code.
     * Sellers are not created here: they apply from the Seller Centre after signing up.
     */
    public function register(RegisterUserRequest $request)
    {
        $referrer = $request->filled('referral_code') ? User::where('referral_code', $request->referral_code)->first() : null;

        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->filled('phone') ? preg_replace('/\s+/', '', $request->phone) : null,
            'password' => Hash::make($request->password),
            'is_active' => true,
            'referral_code' => $code,
            'referred_by' => $referrer?->id,
            'preferred_language' => app()->getLocale(),
        ]);

        $user->roles()->attach(Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer'])->id);

        // Orders placed as a guest with this email now show under My Orders
        \App\Models\Order::whereNull('user_id')->where('guest_email', mb_strtolower($user->email))->update(['user_id' => $user->id]);

        $this->sendEmailCode($user);
        if ($user->phone && $this->sms->isLive()) {
            $this->sendPhoneCode($user);
        }

        Auth::login($user);
        $request->session()->regenerate();

        NotificationService::registrationSuccess();

        return redirect()->route('verification.notice');
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|string|email', 'password' => 'required|string']);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($request->only('email', 'password'), $remember)) {
            return back()->withInput($request->only('email', 'remember'))->withErrors(['email' => __('auth.failed')]);
        }

        $user = Auth::user();

        if ($user->isBanned() || ! $user->isActive()) {
            Auth::logout();

            return back()->withErrors(['email' => $user->isBanned() ? __('auth.account_banned', ['reason' => $user->banned_reason]) : __('auth.account_inactive')]);
        }

        if ($user->isTwoFactorEnabled()) {
            // The password was right but the user is not signed in until the 2FA code is checked
            Auth::logout();
            session(['2fa_user_id' => $user->id, '2fa_remember' => $remember]);

            return redirect()->route('2fa.show');
        }

        $request->session()->regenerate();
        $user->updateLoginTracking($request->ip());
        NotificationService::loginSuccess();
        if ($user->isStaff()) {
            \App\Support\Audit::record('staff.login', $user, ['two_factor' => false]);
        }

        // Unverified emails get a reminder banner on every page; they are not locked out.
        return $this->redirectBasedOnRole($user);
    }

    public function show2FA()
    {
        if (! session('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.2fa');
    }

    public function verify2FA(Request $request)
    {
        $request->validate(['code' => 'required|string|min:6|max:11']);

        $user = User::find(session('2fa_user_id'));
        if (! $user) {
            return redirect()->route('login');
        }

        $code = strtoupper(trim($request->code));
        $valid = $user->useRecoveryCode($code)
            || (ctype_digit($code) && strlen($code) === 6 && $this->google2fa->verifyKey(decrypt($user->two_factor_secret), $code));

        if (! $valid) {
            return back()->withErrors(['code' => __('auth.invalid_2fa_code')]);
        }

        $remember = (bool) session('2fa_remember', false);
        session()->forget(['2fa_user_id', '2fa_remember']);
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->updateLoginTracking($request->ip());
        if ($user->isStaff()) {
            \App\Support\Audit::record('staff.login', $user, ['two_factor' => true]);
        }

        return $this->redirectBasedOnRole($user);
    }

    public function showVerificationNotice(Request $request)
    {
        $user = $request->user();

        return view('auth.verification-notice', [
            'user' => $user,
            // The phone box only appears when codes can actually be texted
            'phonePending' => $user->phone && ! $user->isPhoneVerified() && $this->sms->isLive(),
        ]);
    }

    /**
     * Email a fresh code to the signed-in user (never to an address from the request).
     */
    public function sendEmailOTP(Request $request)
    {
        $user = $request->user();
        if ($user->isEmailVerified()) {
            return redirect()->route('account');
        }

        $this->sendEmailCode($user);

        return back()->with('status', __('We have emailed a new code to :email.', ['email' => $user->email]));
    }

    public function verifyEmailOTP(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();

        if (! OTP::verify($user->email, $request->code, 'verification')) {
            return back()->withErrors(['code' => __('auth.invalid_otp')]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        NotificationService::emailVerified();

        return redirect()->intended(route('account'));
    }

    /**
     * Text a fresh code to the signed-in user's own number: at most one a minute and five an hour.
     */
    public function sendPhoneOTP(Request $request)
    {
        $user = $request->user();
        if (! $user->phone || $user->isPhoneVerified()) {
            return redirect()->route('account');
        }
        if (! $this->sms->isLive()) {
            return back()->withErrors(['phone_code' => __('Text messages are not available right now. Please try again later.')]);
        }

        if (RateLimiter::tooManyAttempts('phone-otp:m:'.$user->id, 1) || RateLimiter::tooManyAttempts('phone-otp:h:'.$user->id, 5)) {
            return back()->withErrors(['phone_code' => __('Please wait a minute before asking for another code.')]);
        }

        $this->sendPhoneCode($user);

        return back()->with('status', __('We have texted a new code to :phone.', ['phone' => $user->phone]));
    }

    /**
     * Phone codes are always checked against the signed-in user's own number.
     */
    public function verifyPhoneOTP(Request $request)
    {
        // The verification page posts phone_code; older clients post code
        $request->merge(['phone_code' => $request->input('phone_code', $request->input('code'))]);
        $request->validate(['phone_code' => 'required|digits:6']);
        $user = $request->user();

        if (! $user->phone || ! OTP::verify($user->phone, $request->phone_code, 'verification')) {
            return back()->withErrors(['phone_code' => __('auth.invalid_otp')]);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();
        NotificationService::phoneVerified();

        return back();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        NotificationService::logoutSuccess();

        return redirect()->route('home');
    }

    protected function sendEmailCode(User $user): void
    {
        $otp = OTP::createForEmail($user->email, 'verification');

        try {
            $user->notify(new VerifyEmailCode($otp));
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function sendPhoneCode(User $user): void
    {
        RateLimiter::hit('phone-otp:m:'.$user->id, 60);
        RateLimiter::hit('phone-otp:h:'.$user->id, 3600);

        $otp = OTP::createForPhone($user->phone, 'verification');

        try {
            $user->notify(new VerifyPhoneCode($otp));
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function redirectBasedOnRole(User $user)
    {
        $default = match (true) {
            $user->isStaff() => route('admin.dashboard'),
            $user->isSeller() => route('seller.dashboard'),
            default => route('home'),
        };

        // Send users back to the page that required login (e.g. /checkout)
        return redirect()->intended($default);
    }
}
