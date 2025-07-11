<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OTP;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show login form
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Show registration form
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle user registration
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'is_seller' => 'boolean',
            'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $referredBy = null;
        if ($request->filled('referral_code')) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
            if ($referrer) {
                $referredBy = $referrer->id;
            }
        }
        // Generate unique referral code for new user
        do {
            $newReferralCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (User::where('referral_code', $newReferralCode)->exists());

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'is_seller' => $request->boolean('is_seller'),
            'is_active' => true,
            'referral_code' => $newReferralCode,
            'referred_by' => $referredBy,
        ]);

        // Assign default role
        $defaultRole = $user->is_seller ? 'seller' : 'customer';
        $role = Role::where('name', $defaultRole)->first();
        $user->roles()->attach($role->id);

        // Reward referral (optional: adjust points/logic as needed)
        if ($referredBy) {
            // Reward referrer
            $referrer->increment('loyalty_points', 100); // e.g., 100 points
            // Reward referee
            $user->increment('loyalty_points', 50); // e.g., 50 points
        }

        // Send verification OTPs
        OTP::createForEmail($user->email, 'verification');
        OTP::createForPhone($user->phone, 'verification');

        Auth::login($user);

        return redirect()->route('verification.notice')
            ->with('success', __('auth.registration_successful'));
    }

    /**
     * Handle user login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember' => 'boolean'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // Check if user is banned
            if ($user->isBanned()) {
                Auth::logout();
                return back()->withErrors([
                    'email' => __('auth.account_banned', ['reason' => $user->banned_reason])
                ]);
            }

            // Check if user is active
            if (!$user->isActive()) {
                Auth::logout();
                return back()->withErrors([
                    'email' => __('auth.account_inactive')
                ]);
            }

            // Update login tracking
            $user->updateLoginTracking($request->ip());

            // Check if 2FA is required
            if ($user->isTwoFactorEnabled()) {
                session(['2fa_user_id' => $user->id]);
                return redirect()->route('2fa.show');
            }

            // Check if email/phone verification is required
            if (!$user->isEmailVerified() || !$user->isPhoneVerified()) {
                return redirect()->route('verification.notice');
            }

            return $this->redirectBasedOnRole($user);
        }

        return back()->withErrors([
            'email' => __('auth.failed')
        ]);
    }

    /**
     * Show 2FA form
     */
    public function show2FA()
    {
        if (!session('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.2fa');
    }

    /**
     * Handle 2FA verification
     */
    public function verify2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $userId = session('2fa_user_id');
        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        $code = $request->code;

        // Check if it's a recovery code
        $recoveryCodes = $user->getRecoveryCodes();
        if (in_array($code, $recoveryCodes)) {
            // Remove used recovery code
            $recoveryCodes = array_diff($recoveryCodes, [$code]);
            $user->update([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes)))
            ]);
            
            session()->forget('2fa_user_id');
            Auth::login($user);
            return $this->redirectBasedOnRole($user);
        }

        // Verify TOTP code
        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $code);

        if ($valid) {
            session()->forget('2fa_user_id');
            Auth::login($user);
            return $this->redirectBasedOnRole($user);
        }

        return back()->withErrors([
            'code' => __('auth.invalid_2fa_code')
        ]);
    }

    /**
     * Show verification notice
     */
    public function showVerificationNotice()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        return view('auth.verification-notice', compact('user'));
    }

    /**
     * Send email verification OTP
     */
    public function sendEmailOTP(Request $request)
    {
        $email = $request->email ?? Auth::user()->email;
        
        $otp = OTP::createForEmail($email, 'verification');
        
        // Send email with OTP
        // Mail::to($email)->send(new EmailVerificationOTP($otp));
        
        return back()->with('success', __('auth.email_otp_sent'));
    }

    /**
     * Send SMS verification OTP
     */
    public function sendSMSOTP(Request $request)
    {
        $phone = $request->phone ?? Auth::user()->phone;
        
        $otp = OTP::createForPhone($phone, 'verification');
        
        // Send SMS with OTP
        // $this->sendSMS($phone, "Your verification code is: {$otp->code}");
        
        return back()->with('success', __('auth.sms_otp_sent'));
    }

    /**
     * Verify email OTP
     */
    public function verifyEmailOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $otp = OTP::verify($request->email, $request->code, 'verification');

        if (!$otp) {
            return back()->withErrors([
                'code' => __('auth.invalid_otp')
            ]);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['email_verified_at' => now()]);

        return back()->with('success', __('auth.email_verified'));
    }

    /**
     * Verify phone OTP
     */
    public function verifyPhoneOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $otp = OTP::verify($request->phone, $request->code, 'verification');

        if (!$otp) {
            return back()->withErrors([
                'code' => __('auth.invalid_otp')
            ]);
        }

        $user = User::where('phone', $request->phone)->first();
        $user->update(['phone_verified_at' => now()]);

        return back()->with('success', __('auth.phone_verified'));
    }

    /**
     * Show 2FA setup
     */
    public function show2FASetup()
    {
        $user = Auth::user();
        $secret = $this->google2fa->generateSecretKey();
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('auth.2fa-setup', compact('secret', 'qrCodeUrl'));
    }

    /**
     * Enable 2FA
     */
    public function enable2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'secret' => 'required|string',
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = Auth::user();
        $valid = $this->google2fa->verifyKey($request->secret, $request->code);

        if (!$valid) {
            return back()->withErrors([
                'code' => __('auth.invalid_2fa_code')
            ]);
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($request->secret),
            'two_factor_recovery_codes' => encrypt(json_encode($user->generateRecoveryCodes()))
        ]);

        return redirect()->route('profile.2fa')
            ->with('success', __('auth.2fa_enabled'));
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(Request $request)
    {
        $user = Auth::user();
        $user->disableTwoFactor();

        return back()->with('success', __('auth.2fa_disabled'));
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Redirect user based on their role
     */
    private function redirectBasedOnRole(User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isSeller()) {
            return redirect()->route('seller.dashboard');
        }

        return redirect()->route('home');
    }

} 