<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialLogin\AppleProvider;
use App\Services\SocialLogin\FacebookProvider;
use App\Services\SocialLogin\GoogleProvider;
use App\Services\SocialLogin\Provider;
use App\Services\SocialLogin\SocialLoginException;
use App\Services\SocialLogin\SocialProfile;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sign in with Google, Facebook or Apple (docs/SOCIAL_LOGIN.md), with Laravel's Http client and
 * no extra packages.
 *
 * start() remembers a random state (and nonce and PKCE verifier) in the session and sends the
 * customer to the provider; finish() checks the state that comes back, exchanges the code and
 * reads who signed in; resolve() finds their account by the provider's id, or joins an account
 * with the same email only when the provider vouches that the email is verified, or makes a new
 * customer account. allowed() refuses staff (they use their password), inactive and banned
 * accounts. The controller then asks for the two-step code when the account has it on.
 */
class SocialLoginService
{
    /** Buttons, in this order. */
    public const PROVIDERS = [
        'google' => GoogleProvider::class,
        'facebook' => FacebookProvider::class,
        'apple' => AppleProvider::class,
    ];

    public const LABELS = ['google' => 'Google', 'facebook' => 'Facebook', 'apple' => 'Apple'];

    /** Where start() keeps the attempt in the session. */
    public const SESSION_KEY = 'social_login';

    /** An attempt older than this is refused. */
    public const ATTEMPT_MINUTES = 10;

    public function provider(string $name): ?Provider
    {
        $class = self::PROVIDERS[$name] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * The providers whose keys are set: the buttons on the sign-in and sign-up pages.
     *
     * @return list<Provider>
     */
    public function enabled(): array
    {
        return array_values(array_filter(array_map(fn (string $name) => $this->provider($name), array_keys(self::PROVIDERS)), fn (?Provider $p) => $p?->configured() === true));
    }

    /**
     * Start a sign-in: remember this attempt in the session and return the provider's page.
     */
    public function start(Provider $provider, Session $session): string
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = $provider->usesPkce() ? Str::random(64) : null;

        $session->put(self::SESSION_KEY, [
            'provider' => $provider->name(),
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'at' => now()->getTimestamp(),
        ]);

        $challenge = $verifier === null ? null : rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return $provider->authorizeUrl($state, $nonce, $challenge);
    }

    /**
     * Finish a sign-in from what the provider sent back. The attempt is used up either way.
     *
     * @param  array<string, mixed>  $callback
     *
     * @throws SocialLoginException
     */
    public function finish(Provider $provider, array $callback, Session $session): SocialProfile
    {
        $attempt = $session->pull(self::SESSION_KEY);
        $state = $callback['state'] ?? null;

        if (! is_array($attempt) || ($attempt['provider'] ?? null) !== $provider->name() || ! is_string($state)
            || ! hash_equals((string) ($attempt['state'] ?? ''), $state)
            || (int) ($attempt['at'] ?? 0) < now()->subMinutes(self::ATTEMPT_MINUTES)->getTimestamp()) {
            throw SocialLoginException::expired();
        }

        if (isset($callback['error'])) {
            throw SocialLoginException::cancelled($provider->label());
        }

        $code = $callback['code'] ?? null;
        if (! is_string($code) || $code === '' || strlen($code) > 2048) {
            throw SocialLoginException::failed($provider->label());
        }

        try {
            return $provider->profile($code, $attempt['verifier'] ?? null, (string) $attempt['nonce'], $callback);
        } catch (ConnectionException $e) {
            report($e); // the provider could not be reached (or timed out)

            throw SocialLoginException::failed($provider->label());
        }
    }

    /**
     * The account for this provider sign-in: the one linked to it before; else the account with
     * the same email when the provider vouches the email is verified (and it is not staff), which
     * is then linked; else a new customer account. Returns the account and whether it is new.
     *
     * @return array{0: User, 1: bool}
     *
     * @throws SocialLoginException
     */
    public function resolve(SocialProfile $profile, ?string $referralCode = null): array
    {
        $label = self::LABELS[$profile->provider] ?? $profile->provider;

        $linked = SocialAccount::query()->where('provider', $profile->provider)->where('subject', $profile->subject)->first();
        if ($linked) {
            $user = User::withTrashed()->find($linked->user_id);
            if (! $user || $user->trashed()) {
                throw SocialLoginException::refused(__('auth.account_inactive'));
            }
            $linked->forceFill(['email' => $profile->email ?? $linked->email, 'last_used_at' => now()])->save();

            return [$user, false];
        }

        $email = mb_strtolower(trim((string) $profile->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw SocialLoginException::noEmail($label);
        }

        $existing = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            if ($existing->trashed()) {
                throw SocialLoginException::refused(__('auth.account_inactive'));
            }
            if (! $profile->emailVerified) {
                throw SocialLoginException::emailInUse($email);
            }
            $this->allowed($existing, $label); // staff are never linked

            $this->link($existing, $profile);
            if (! $existing->isEmailVerified()) {
                $existing->forceFill(['email_verified_at' => now()])->save(); // the provider vouched for it
            }

            return [$existing, false];
        }

        return [$this->createCustomer($profile, $email, $referralCode), true];
    }

    /**
     * Refuse accounts that may not sign in this way: staff (admin, support, finance) use their
     * password and two-step code; banned and inactive accounts cannot sign in at all.
     *
     * @throws SocialLoginException
     */
    public function allowed(User $user, string $label): void
    {
        if ($user->isStaff()) {
            throw SocialLoginException::staff($label);
        }
        if ($user->isBanned()) {
            throw SocialLoginException::refused(__('auth.account_banned', ['reason' => $user->banned_reason]));
        }
        if (! $user->isActive()) {
            throw SocialLoginException::refused(__('auth.account_inactive'));
        }
    }

    protected function link(User $user, SocialProfile $profile): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $profile->provider,
            'subject' => $profile->subject,
            'email' => $profile->email,
            'last_used_at' => now(),
        ]);
    }

    /**
     * A new customer account, as the sign-up form makes one, but without a password of its own
     * (has_password false) until the customer sets one by the emailed link.
     */
    protected function createCustomer(SocialProfile $profile, string $email, ?string $referralCode): User
    {
        $referralCode = strtoupper(trim((string) $referralCode));
        $referrer = $referralCode !== '' ? User::where('referral_code', $referralCode)->first() : null;

        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return DB::transaction(function () use ($profile, $email, $referrer, $code) {
            $user = new User([
                'name' => $profile->name ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'is_active' => true,
                'referral_code' => $code,
                'referred_by' => $referrer?->id,
                'preferred_language' => app()->getLocale(),
                'email_verified_at' => $profile->emailVerified ? now() : null,
            ]);
            $user->forceFill(['has_password' => false])->save();
            $user->roles()->attach(Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer'])->id);
            $this->link($user, $profile);

            // Orders placed as a guest with this email show under My Orders, once the email is proven
            if ($user->isEmailVerified()) {
                Order::whereNull('user_id')->where('guest_email', $email)->update(['user_id' => $user->id]);
            }

            return $user;
        });
    }

    // ---- Apple's form post ------------------------------------------------------------------

    /**
     * Keep what Apple posted for a few minutes and return a one-time key for it: the post arrives
     * without the session, so the callback reads it back with the session (and its state).
     *
     * @param  array<string, mixed>  $fields
     */
    public function handoff(array $fields): string
    {
        $kept = [];
        foreach (['code', 'state', 'user', 'error'] as $name) {
            if (is_string($fields[$name] ?? null)) {
                $kept[$name] = mb_substr($fields[$name], 0, 4096);
            }
        }

        $key = Str::random(40);
        Cache::put('social-login:apple:'.$key, $kept, now()->addMinutes(5));

        return $key;
    }

    /**
     * @return array<string, string>|null
     */
    public function takeHandoff(mixed $key): ?array
    {
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9]{40}$/', $key)) {
            return null;
        }

        $fields = Cache::pull('social-login:apple:'.$key);

        return is_array($fields) ? $fields : null;
    }
}
