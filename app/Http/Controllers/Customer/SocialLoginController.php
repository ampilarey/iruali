<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\OTP;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\NotificationService;
use App\Services\SocialLogin\Provider;
use App\Services\SocialLogin\SocialLoginException;
use App\Services\SocialLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * "Continue with Google / Facebook / Apple" on the sign-in and sign-up pages, and the connected
 * accounts on My Account (see SocialLoginService and docs/SOCIAL_LOGIN.md).
 */
class SocialLoginController extends Controller
{
    public function __construct(protected SocialLoginService $social) {}

    /**
     * Off to the provider, with this attempt's state remembered in the session.
     */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        return redirect()->away($this->social->start($this->provider($provider), $request->session()));
    }

    /**
     * Back from the provider (Google and Facebook come here directly; Apple through applePost).
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->provider($provider);
        $callback = $provider === 'apple' ? ($this->social->takeHandoff($request->query('handoff')) ?? []) : $request->query();

        try {
            $profile = $this->social->finish($driver, $callback, $request->session());
            $referral = $request->cookie(RewardsController::COOKIE); // a shared /r/{code} link, as on the sign-up form
            [$user, $created] = $this->social->resolve($profile, is_string($referral) ? $referral : null);
            $this->social->allowed($user, $driver->label());
        } catch (SocialLoginException $e) {
            return redirect()->route('login')->withErrors(['social' => $e->getMessage()]);
        }

        return $this->signIn($request, $user, $created);
    }

    /**
     * Apple's answer is a form post from appleid.apple.com (response_mode=form_post). A cross-site
     * post comes without the session cookie, so this route runs without the session (and so
     * without its CSRF token: the state, checked in callback(), protects the sign-in) and hands
     * the fields to the callback, which the browser then opens with its session.
     */
    public function applePost(Request $request): RedirectResponse
    {
        $key = $this->social->handoff($request->only(['code', 'state', 'user', 'error']));

        return redirect()->to(route('social.callback', ['provider' => 'apple', 'handoff' => $key]), 303);
    }

    /**
     * My Account → Security: stop signing in with a provider, while another way in remains.
     */
    public function unlink(Request $request, int $account): RedirectResponse
    {
        $user = $request->user();
        $row = SocialAccount::query()->where('user_id', $user->id)->findOrFail($account);

        if (! $user->hasPassword() && $user->socialAccounts()->count() <= 1) {
            return redirect()->to(route('account').'#security')->withErrors(['social' => __('Set a password first: :provider is the only way you can sign in now.', ['provider' => $row->providerLabel()])]);
        }

        $row->delete();
        NotificationService::success(__(':provider is no longer connected to your account.', ['provider' => $row->providerLabel()]));

        return redirect()->to(route('account').'#security');
    }

    /**
     * An account made with Google, Facebook or Apple has no password: email the customer a link
     * to set one (the "forgot password" link, which works while signed in too).
     */
    public function passwordLink(Request $request): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $request->user()->email]);
        $status === Password::RESET_THROTTLED
            ? NotificationService::error(__('Please wait a minute before asking for another link.'))
            : NotificationService::success(__('We have emailed you a link to set a password.'));

        return redirect()->to(route('account').'#security');
    }

    protected function provider(string $name): Provider
    {
        $provider = $this->social->provider($name);
        abort_unless($provider && $provider->configured(), 404);

        return $provider;
    }

    /**
     * Sign the customer in as the password form does: the two-step code first when it is on, the
     * guest cart merged into the account (on the Login event), a fresh session.
     */
    protected function signIn(Request $request, User $user, bool $created): RedirectResponse
    {
        $request->session()->regenerate();

        if ($user->isTwoFactorEnabled()) {
            session(['2fa_user_id' => $user->id, '2fa_remember' => false]);

            return redirect()->route('2fa.show');
        }

        Auth::login($user);
        $user->updateLoginTracking((string) $request->ip());

        if ($created && ! $user->isEmailVerified()) {
            $this->sendEmailCode($user);
            NotificationService::registrationSuccess();

            return redirect()->route('verification.notice');
        }

        $created ? NotificationService::success(__('Welcome to iruali! Your account is ready.')) : NotificationService::loginSuccess();

        return redirect()->intended($user->isSeller() ? route('seller.dashboard') : route('home'));
    }

    protected function sendEmailCode(User $user): void
    {
        try {
            $user->notify(new VerifyEmailCode(OTP::createForEmail($user->email, 'verification')));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
