<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

/**
 * My Account: profile details, password and two-step sign-in.
 */
class ProfileController extends Controller
{
    public function show()
    {
        return view('account.index', ['user' => Auth::user()]);
    }

    public function edit()
    {
        return view('account.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,15}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'preferred_language' => ['nullable', Rule::in(['en', 'dv'])],
        ], ['phone.regex' => __('Enter a phone number with digits only, e.g. 777 1234.')]);

        $data['phone'] = filled($data['phone'] ?? null) ? preg_replace('/\s+/', '', $data['phone']) : null;
        if ($data['phone'] !== $user->phone) {
            $data['phone_verified_at'] = null;
        }

        $user->fill($data)->save();
        if ($data['preferred_language'] ?? null) {
            session(['locale' => $data['preferred_language']]);
        }

        NotificationService::success(__('Your details have been saved.'));

        return redirect()->route('account');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->password)])->save();
        $user->tokens()->delete();
        Auth::logoutOtherDevices($request->password);

        NotificationService::success(__('Your password has been changed.'));

        return redirect()->route('account');
    }

    /**
     * Two-step sign-in: shows the QR code to scan, or the current status and the recovery codes
     * (once, right after enabling).
     */
    public function twoFactor(Request $request, Google2FA $google2fa)
    {
        $user = $request->user();

        if ($user->isTwoFactorEnabled()) {
            return view('account.two-factor', ['user' => $user, 'recoveryCodes' => session('2fa_recovery_codes')]);
        }

        $secret = session('2fa_pending_secret');
        if (! $secret) {
            $secret = $google2fa->generateSecretKey();
            session(['2fa_pending_secret' => $secret]);
        }

        $otpauth = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $qr = (new Writer(new ImageRenderer(new RendererStyle(200, 0), new SvgImageBackEnd)))->writeString($otpauth);

        return view('account.two-factor', compact('user', 'secret', 'otpauth', 'qr'));
    }

    public function enableTwoFactor(Request $request, Google2FA $google2fa)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();
        $secret = session('2fa_pending_secret');

        if (! $secret || ! $google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => __('That code did not match. Check the time on your phone and try again.')]);
        }

        $codes = $user->generateRecoveryCodes();
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt($secret)])->save();
        $user->setRecoveryCodes($codes);
        session()->forget('2fa_pending_secret');

        NotificationService::twoFactorEnabled();

        return redirect()->route('profile.2fa.setup')->with('2fa_recovery_codes', $codes);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['current_password' => 'required|current_password']);

        $request->user()->disableTwoFactor();
        NotificationService::twoFactorDisabled();

        return redirect()->route('account');
    }
}
