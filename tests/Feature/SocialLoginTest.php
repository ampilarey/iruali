<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\SocialLogin\Jwt;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Sign in with Google, Facebook and Apple: the OAuth / OpenID Connect flows against faked
 * provider endpoints (state, PKCE, nonce, Apple's ES256 client secret and form post), how accounts
 * are found, linked or made, who is refused, two-step sign-in, the guest cart, and the connected
 * accounts on My Account.
 */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected static ?string $applePrivateKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function configure(array $providers = ['google', 'facebook', 'apple']): void
    {
        if (in_array('google', $providers, true)) {
            config(['services.google.client_id' => 'google-client', 'services.google.client_secret' => 'google-secret']);
        }
        if (in_array('facebook', $providers, true)) {
            config(['services.facebook.client_id' => 'fb-app', 'services.facebook.client_secret' => 'fb-secret', 'services.facebook.graph_version' => 'v25.0', 'services.facebook.pkce' => true]);
        }
        if (in_array('apple', $providers, true)) {
            config(['services.apple.client_id' => 'mv.iruali.signin', 'services.apple.team_id' => 'TEAM123456', 'services.apple.key_id' => 'KEY1234567', 'services.apple.private_key' => $this->applePrivateKey()]);
        }
    }

    protected function applePrivateKey(): string
    {
        if (self::$applePrivateKey === null) {
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            openssl_pkey_export($key, $pem);
            self::$applePrivateKey = $pem;
        }

        return self::$applePrivateKey;
    }

    /** An ID token as a token endpoint returns it (its signature is not what is checked). */
    protected function idToken(array $claims): string
    {
        return Jwt::base64UrlEncode('{"alg":"RS256","typ":"JWT"}').'.'.Jwt::base64UrlEncode((string) json_encode($claims)).'.'.Jwt::base64UrlEncode('signature');
    }

    /**
     * Press the provider's button: returns the query of the provider page it sends the browser to.
     *
     * @return array<string, string>
     */
    protected function start(string $provider): array
    {
        $location = $this->get(route('social.redirect', ['provider' => $provider]))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    /** What the faked Google token endpoint puts in the next ID token (read when it is called). */
    protected array $googleClaims = [];

    protected function fakeGoogle(array $query, array $claims = []): void
    {
        $first = $this->googleClaims === [];
        $this->googleClaims = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'google-client',
            'sub' => '1098765432',
            'email' => 'aisha@example.com',
            'email_verified' => true,
            'name' => 'Aisha Ibrahim',
            'nonce' => $query['nonce'],
            'exp' => now()->addHour()->getTimestamp(),
            'iat' => now()->getTimestamp(),
        ], $claims);

        if ($first) {
            Http::fake(['oauth2.googleapis.com/token' => fn () => Http::response(['access_token' => 'google-access', 'token_type' => 'Bearer', 'id_token' => $this->idToken($this->googleClaims)])]);
        }
    }

    /** The whole Google round trip; returns the response of the callback. */
    protected function signInWithGoogle(array $claims = []): TestResponse
    {
        $query = $this->start('google');
        $this->fakeGoogle($query, $claims);

        return $this->get(route('social.callback', ['provider' => 'google', 'state' => $query['state'], 'code' => 'google-code']));
    }

    protected function staff(string $email, string $role = 'admin'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    // ---- Buttons --------------------------------------------------------------------------------

    public function test_buttons_show_only_for_providers_with_keys(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('data-social-login', false);
        $this->get(route('social.redirect', ['provider' => 'google']))->assertNotFound();

        $this->configure(['google']);
        $this->get(route('login'))->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('href="'.route('social.redirect', ['provider' => 'google']).'"', false)
            ->assertDontSee('Continue with Facebook')
            ->assertDontSee('Continue with Apple');
        $this->get(route('social.redirect', ['provider' => 'apple']))->assertNotFound();

        $this->configure();
        $this->get(route('register'))->assertOk()->assertSeeInOrder(['Continue with Google', 'Continue with Facebook', 'Continue with Apple']);
        $this->withSession(['locale' => 'dv'])->get(route('login'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('Google އިން ކުރިއަށް');

        // Apple without its key stays hidden
        config(['services.apple.private_key' => null, 'services.apple.private_key_path' => null]);
        $this->get(route('login'))->assertDontSee('Continue with Apple');
    }

    // ---- Google: a new account, state and PKCE ---------------------------------------------------

    public function test_google_makes_a_customer_account_using_state_pkce_and_nonce(): void
    {
        $this->configure();
        $query = $this->start('google');

        $this->assertSame('google-client', $query['client_id']);
        $this->assertSame(url('/auth/google/callback'), $query['redirect_uri']);
        $this->assertSame(['code', 'openid email profile', 'S256'], [$query['response_type'], $query['scope'], $query['code_challenge_method']]);
        $this->assertSame(40, strlen($query['state']));
        $this->assertNotEmpty($query['nonce']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $query['code_challenge']);

        $this->fakeGoogle($query);
        $this->get(route('social.callback', ['provider' => 'google', 'state' => $query['state'], 'code' => 'google-code']))->assertRedirect(route('home'));

        $user = User::where('email', 'aisha@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Aisha Ibrahim', $user->name);
        $this->assertNotNull($user->email_verified_at, 'Google vouched for the email');
        $this->assertFalse($user->hasPassword());
        $this->assertTrue($user->hasRole('customer'));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'subject' => '1098765432']);

        // The token request proved the PKCE verifier and used the code
        Http::assertSent(function (HttpRequest $request) use ($query) {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'authorization_code' && $request['code'] === 'google-code'
                && $request['client_secret'] === 'google-secret' && $request['redirect_uri'] === url('/auth/google/callback')
                && Jwt::base64UrlEncode(hash('sha256', (string) $request['code_verifier'], true)) === $query['code_challenge'];
        });

        // Next time the same Google account signs in to the same iruali account
        $this->post('/logout');
        $this->signInWithGoogle(['email' => 'aisha.new@example.com'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame('aisha.new@example.com', SocialAccount::sole()->email);
        $this->assertNotNull(SocialAccount::sole()->last_used_at);
    }

    public function test_a_wrong_or_reused_state_is_refused(): void
    {
        $this->configure();
        $query = $this->start('google');
        $this->fakeGoogle($query);

        $this->get(route('social.callback', ['provider' => 'google', 'state' => 'forged-state', 'code' => 'google-code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('social');
        $this->assertGuest();
        Http::assertNothingSent();

        // The attempt is used up by the failure: the real state no longer works either
        $this->get(route('social.callback', ['provider' => 'google', 'state' => $query['state'], 'code' => 'google-code']))->assertSessionHasErrors('social');
        // Nor does a callback that was never started, or one for another provider's attempt
        $this->get(route('social.callback', ['provider' => 'google', 'code' => 'google-code']))->assertSessionHasErrors('social');
        $facebook = $this->start('facebook');
        $this->get(route('social.callback', ['provider' => 'google', 'state' => $facebook['state'], 'code' => 'x']))->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertSame(0, User::count());
        Http::assertNothingSent();

        // A successful sign-in cannot be replayed
        $query = $this->start('google');
        $this->fakeGoogle($query);
        $this->get(route('social.callback', ['provider' => 'google', 'state' => $query['state'], 'code' => 'google-code']));
        $this->post('/logout');
        $this->get(route('social.callback', ['provider' => 'google', 'state' => $query['state'], 'code' => 'google-code']))->assertSessionHasErrors('social');
        $this->assertGuest();
    }

    public function test_an_id_token_for_another_app_or_attempt_is_refused(): void
    {
        $this->configure();
        foreach ([['aud' => 'someone-else'], ['nonce' => 'another-attempt'], ['iss' => 'https://evil.example'], ['exp' => now()->subHour()->getTimestamp()]] as $bad) {
            $this->signInWithGoogle($bad)->assertRedirect(route('login'))->assertSessionHasErrors(['social' => 'We could not sign you in with Google. Please try again.']);
        }
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_a_provider_that_fails_or_times_out_gives_a_message_not_an_error_page(): void
    {
        $this->configure();
        $calls = 0;
        Http::fake(['oauth2.googleapis.com/token' => function () use (&$calls) {
            if (++$calls === 1) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response(['error' => 'invalid_grant'], 400);
        }]);

        foreach ([1, 2] as $try) {
            $query = $this->start('google');
            $this->get(route('social.callback', ['provider' => 'google', 'state' => $query['state'], 'code' => 'google-code']))
                ->assertRedirect(route('login'))->assertSessionHasErrors(['social' => 'We could not sign you in with Google. Please try again.']);
        }
        $this->assertSame(2, $calls);
        $this->assertGuest();
    }

    public function test_cancelling_at_the_provider_says_so(): void
    {
        $this->configure();
        $query = $this->start('google');

        $this->get(route('social.callback', ['provider' => 'google', 'state' => $query['state'], 'error' => 'access_denied']))
            ->assertRedirect(route('login'))->assertSessionHasErrors(['social' => 'Sign-in with Google was cancelled.']);
        $this->get(route('login'))->assertSee('Sign-in with Google was cancelled.');
    }

    // ---- Existing accounts ---------------------------------------------------------------------

    public function test_an_existing_account_is_joined_only_when_the_provider_verified_the_email(): void
    {
        $this->configure();
        $existing = User::factory()->unverified()->create(['email' => 'aisha@example.com', 'name' => 'Aisha']);

        // Google says the email is not verified: no takeover, no second account
        $this->signInWithGoogle(['email_verified' => false])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['social' => 'An iruali account already uses aisha@example.com. Sign in with your email and password instead.']);
        $this->assertGuest();
        $this->assertSame(0, SocialAccount::count());

        // Verified (and in other letter case): joined, and the email counts as verified now
        $this->signInWithGoogle(['email' => 'Aisha@Example.com'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $existing->id, 'provider' => 'google']);
        $existing->refresh();
        $this->assertNotNull($existing->email_verified_at);
        $this->assertTrue($existing->hasPassword(), 'the password stays');
        $this->assertSame('Aisha', $existing->name);
    }

    public function test_staff_accounts_must_use_their_password(): void
    {
        $this->configure();
        $admin = $this->staff('aisha@example.com');

        $this->signInWithGoogle()->assertRedirect(route('login'))->assertSessionHasErrors(['social' => 'Staff accounts cannot sign in with Google. Use your password.']);
        $this->assertGuest();
        $this->assertSame(0, SocialAccount::count(), 'never linked to a staff account');

        // A customer who became staff after linking is refused too
        $customer = User::factory()->create(['email' => 'hassan@example.com']);
        SocialAccount::create(['user_id' => $customer->id, 'provider' => 'google', 'subject' => 'hassan-google']);
        $customer->roles()->attach(Role::firstOrCreate(['name' => 'support'], ['display_name' => 'Support'])->id);
        $this->signInWithGoogle(['sub' => 'hassan-google', 'email' => 'hassan@example.com'])->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertNotNull($admin->fresh());
    }

    public function test_banned_and_inactive_accounts_are_refused(): void
    {
        $this->configure();
        $banned = User::factory()->create(['email' => 'aisha@example.com']);
        $banned->ban('Fake reviews', now()->addMonth());
        SocialAccount::create(['user_id' => $banned->id, 'provider' => 'google', 'subject' => '1098765432']);

        $this->signInWithGoogle()->assertRedirect(route('login'))->assertSessionHasErrors(['social' => 'Your account has been banned. Reason: Fake reviews']);
        $this->assertGuest();

        $banned->unban();
        $banned->update(['is_active' => false]);
        $this->signInWithGoogle()->assertSessionHasErrors(['social' => 'Your account is currently inactive.']);
        $this->assertGuest();

        $banned->delete(); // closed (in the bin): no new account under the same email either
        SocialAccount::query()->delete();
        $this->signInWithGoogle()->assertSessionHasErrors(['social' => 'Your account is currently inactive.']);
        $this->assertSame(1, User::withTrashed()->count());
    }

    public function test_two_step_sign_in_still_asks_for_the_code(): void
    {
        $this->configure();
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->create(['email' => 'aisha@example.com']);
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt($secret)])->save();
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'subject' => '1098765432']);

        $this->signInWithGoogle()->assertRedirect(route('2fa.show'));
        $this->assertGuest();
        $this->assertSame($user->id, session('2fa_user_id'));

        $this->post(route('2fa.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post(route('2fa.verify'), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_guest_cart_comes_along_and_the_customer_returns_where_they_were(): void
    {
        $this->configure();
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 5, 'price' => 100]);
        $user = User::factory()->create(['email' => 'aisha@example.com']);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'subject' => '1098765432']);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        session(['url.intended' => url('/checkout')]);

        $this->signInWithGoogle()->assertRedirect(url('/checkout'));
        $this->assertAuthenticatedAs($user);
        $cart = Cart::where('user_id', $user->id)->where('status', 'active')->sole();
        $this->assertSame(2, (int) $cart->items()->where('product_id', $product->id)->value('quantity'));
    }

    // ---- Facebook ---------------------------------------------------------------------------------

    public function test_facebook_signs_in_with_pkce_and_never_joins_an_account_by_email(): void
    {
        Notification::fake();
        $this->configure();
        $query = $this->start('facebook');
        $this->assertStringStartsWith('fb-app', $query['client_id']);
        $this->assertSame(['openid email public_profile', 'S256', url('/auth/facebook/callback')], [$query['scope'], $query['code_challenge_method'], $query['redirect_uri']]);

        $token = ['access_token' => 'fb-access', 'token_type' => 'bearer', 'id_token' => $this->idToken(['aud' => 'fb-app', 'sub' => '7700112233', 'nonce' => $query['nonce'], 'exp' => now()->addHour()->getTimestamp()])];
        $me = ['id' => '7700112233', 'name' => 'Mohamed Shareef', 'email' => 'shareef@example.com'];
        Http::fake([
            'graph.facebook.com/v25.0/oauth/access_token*' => function () use (&$token) {
                return Http::response($token);
            },
            'graph.facebook.com/v25.0/me*' => function () use (&$me) {
                return Http::response($me);
            },
        ]);
        $this->get(route('social.callback', ['provider' => 'facebook', 'state' => $query['state'], 'code' => 'fb-code']))->assertRedirect(route('verification.notice'));

        // Facebook does not vouch for the email: the account is made but must verify it, like a sign-up
        $user = User::where('email', 'shareef@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasPassword());
        Notification::assertSentTo($user, VerifyEmailCode::class);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://graph.facebook.com/v25.0/oauth/access_token')
            && $request['code'] === 'fb-code' && $request['client_secret'] === 'fb-secret'
            && Jwt::base64UrlEncode(hash('sha256', (string) $request['code_verifier'], true)) === $query['code_challenge']);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://graph.facebook.com/v25.0/me')
            && $request['appsecret_proof'] === hash_hmac('sha256', 'fb-access', 'fb-secret') && $request['fields'] === 'id,name,email');

        // Another Facebook account with an email that already has an account: refused, not joined
        $this->post('/logout');
        User::factory()->create(['email' => 'aisha@example.com']);
        $query = $this->start('facebook');
        $token = ['access_token' => 'fb-access-2', 'id_token' => $this->idToken(['aud' => 'fb-app', 'sub' => '7700999999', 'nonce' => $query['nonce'], 'exp' => now()->addHour()->getTimestamp()])];
        $me = ['id' => '7700999999', 'name' => 'Not Aisha', 'email' => 'aisha@example.com'];
        $this->get(route('social.callback', ['provider' => 'facebook', 'state' => $query['state'], 'code' => 'fb-code']))
            ->assertSessionHasErrors(['social' => 'An iruali account already uses aisha@example.com. Sign in with your email and password instead.']);
        $this->assertGuest();
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_facebook_without_an_email_or_without_pkce(): void
    {
        $this->configure();
        config(['services.facebook.pkce' => false]);
        $query = $this->start('facebook');
        $this->assertSame('email,public_profile', $query['scope']);
        $this->assertArrayNotHasKey('code_challenge', $query);

        Http::fake([
            'graph.facebook.com/v25.0/oauth/access_token*' => Http::response(['access_token' => 'fb-access']),
            'graph.facebook.com/v25.0/me*' => Http::response(['id' => '7700112233', 'name' => 'Phone Only']),
        ]);
        $this->get(route('social.callback', ['provider' => 'facebook', 'state' => $query['state'], 'code' => 'fb-code']))
            ->assertSessionHasErrors(['social' => 'Facebook did not share your email address. Allow it and try again, or sign up with your email.']);
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'oauth/access_token') && ! isset($request['code_verifier']));
        $this->assertSame(0, User::count());
    }

    // ---- Apple ------------------------------------------------------------------------------------

    public function test_apple_posts_back_without_the_session_and_sends_the_name_only_once(): void
    {
        $this->configure();
        $query = $this->start('apple');
        $this->assertSame(['code', 'form_post', 'name email', 'mv.iruali.signin', url('/auth/apple/callback')], [$query['response_type'], $query['response_mode'], $query['scope'], $query['client_id'], $query['redirect_uri']]);
        $this->assertArrayNotHasKey('code_challenge', $query);

        $secrets = [];
        Http::fake(['appleid.apple.com/auth/token' => function (HttpRequest $request) use (&$secrets, &$query) {
            $secrets[] = $request['client_secret'];

            return Http::response(['access_token' => 'apple-access', 'id_token' => $this->idToken([
                'iss' => 'https://appleid.apple.com', 'aud' => 'mv.iruali.signin', 'sub' => '001234.abcdef.1234',
                'email' => 'x7k2@privaterelay.appleid.com', 'email_verified' => 'true', 'is_private_email' => 'true',
                'nonce' => $query['nonce'], 'exp' => now()->addHour()->getTimestamp(),
            ])]);
        }]);

        // Apple's form post: no session, no CSRF token; it hands over to the callback with a 303
        $post = $this->post('/auth/apple/callback', ['code' => 'apple-code', 'state' => $query['state'], 'user' => json_encode(['name' => ['firstName' => 'Aishath', 'lastName' => 'Ali'], 'email' => 'x7k2@privaterelay.appleid.com'])]);
        $post->assertStatus(303)->assertCookieMissing(config('session.cookie'));
        $this->assertStringStartsWith(url('/auth/apple/callback').'?handoff=', $post->headers->get('Location'));

        $this->get($post->headers->get('Location'))->assertRedirect(route('home'));
        $user = User::where('email', 'x7k2@privaterelay.appleid.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Aishath Ali', $user->name);
        $this->assertNotNull($user->email_verified_at);

        // The handover works once
        $this->post('/logout');
        $this->get($post->headers->get('Location'))->assertSessionHasErrors('social');

        // Next time Apple sends no name: the account keeps the one it has
        $query = $this->start('apple');
        $again = $this->post('/auth/apple/callback', ['code' => 'apple-code-2', 'state' => $query['state']]);
        $this->get($again->headers->get('Location'))->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Aishath Ali', $user->fresh()->name);

        // The client secret: an ES256 JWT from the .p8 key, R||S signature that verifies
        $this->assertCount(2, $secrets);
        [$header, $payload, $signature] = explode('.', $secrets[0]);
        $this->assertSame(['kid' => 'KEY1234567', 'alg' => 'ES256', 'typ' => 'JWT'], json_decode(Jwt::base64UrlDecode($header), true));
        $claims = json_decode(Jwt::base64UrlDecode($payload), true);
        $this->assertSame(['TEAM123456', 'https://appleid.apple.com', 'mv.iruali.signin', 300], [$claims['iss'], $claims['aud'], $claims['sub'], $claims['exp'] - $claims['iat']]);
        $raw = Jwt::base64UrlDecode($signature);
        $this->assertSame(64, strlen($raw));
        $this->assertSame(1, openssl_verify($header.'.'.$payload, $this->rawToDer($raw), openssl_pkey_get_details(openssl_pkey_get_private($this->applePrivateKey()))['key'], OPENSSL_ALGO_SHA256));
    }

    public function test_apple_cancelled_and_the_post_route_is_the_only_one_without_session_and_csrf(): void
    {
        $this->configure();
        $query = $this->start('apple');
        $post = $this->post('/auth/apple/callback', ['error' => 'user_cancelled_authorize', 'state' => $query['state']]);
        $this->get($post->headers->get('Location'))->assertSessionHasErrors(['social' => 'Sign-in with Apple was cancelled.']);

        $middleware = fn (string $name) => app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName($name));
        $this->assertNotContains(StartSession::class, $middleware('social.apple.post'));
        $this->assertNotContains(ValidateCsrfToken::class, $middleware('social.apple.post'));
        foreach (['social.callback', 'social.redirect', 'login', 'account.social.unlink'] as $name) {
            $this->assertContains(ValidateCsrfToken::class, $middleware($name), $name);
            $this->assertContains(StartSession::class, $middleware($name), $name);
        }
    }

    public function test_apple_keys_can_be_given_as_a_path_or_with_escaped_line_breaks(): void
    {
        $this->configure(['apple']);
        $path = storage_path('framework/testing-apple-key.p8');
        file_put_contents($path, $this->applePrivateKey());

        try {
            config(['services.apple.private_key' => null, 'services.apple.private_key_path' => $path]);
            $this->assertTrue(app(\App\Services\SocialLogin\AppleProvider::class)->configured());
            $this->assertCount(3, explode('.', app(\App\Services\SocialLogin\AppleProvider::class)->clientSecret()));

            config(['services.apple.private_key' => str_replace("\n", '\n', $this->applePrivateKey()), 'services.apple.private_key_path' => null]);
            $this->assertCount(3, explode('.', app(\App\Services\SocialLogin\AppleProvider::class)->clientSecret()));
        } finally {
            @unlink($path);
        }

        // DER signatures of every length (leading zero bytes) become 64 bytes and verify again
        $key = openssl_pkey_get_private($this->applePrivateKey());
        $public = openssl_pkey_get_details($key)['key'];
        for ($i = 0; $i < 40; $i++) {
            openssl_sign('message '.$i, $der, $key, OPENSSL_ALGO_SHA256);
            $raw = Jwt::derToRaw($der);
            $this->assertSame(64, strlen($raw));
            $this->assertSame(1, openssl_verify('message '.$i, $this->rawToDer($raw), $public, OPENSSL_ALGO_SHA256));
        }
    }

    /** R||S back to DER, as openssl_verify wants it. */
    protected function rawToDer(string $raw): string
    {
        $integer = function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || ord($bytes[0]) & 0x80) {
                $bytes = "\x00".$bytes;
            }

            return "\x02".chr(strlen($bytes)).$bytes;
        };
        $sequence = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));

        return "\x30".chr(strlen($sequence)).$sequence;
    }

    // ---- My Account: connected accounts ---------------------------------------------------------

    public function test_customers_see_and_unlink_connected_accounts_while_another_way_in_remains(): void
    {
        $user = User::factory()->create(['email' => 'aisha@example.com']);
        $google = SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'subject' => 'g-1', 'email' => 'aisha@gmail.com']);

        $this->actingAs($user)->get(route('account'))->assertOk()
            ->assertSee('Connected accounts')
            ->assertSee('data-social-account="google"', false)
            ->assertSee('aisha@gmail.com')
            ->assertSee('action="'.route('account.social.unlink', ['account' => $google->id]).'"', false)
            ->assertSee('Change password');

        $this->delete(route('account.social.unlink', ['account' => $google->id]))->assertRedirect(route('account').'#security');
        $this->assertModelMissing($google);

        // Someone else's connection is not found
        $other = SocialAccount::create(['user_id' => User::factory()->create()->id, 'provider' => 'google', 'subject' => 'g-2']);
        $this->delete(route('account.social.unlink', ['account' => $other->id]))->assertNotFound();
        $this->assertModelExists($other);
    }

    public function test_an_account_without_a_password_keeps_its_last_connection_until_it_sets_one(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'aisha@example.com']);
        $user->forceFill(['has_password' => false])->save();
        $apple = SocialAccount::create(['user_id' => $user->id, 'provider' => 'apple', 'subject' => 'a-1']);

        $this->actingAs($user)->get(route('account'))->assertOk()
            ->assertSee('Your only way to sign in')
            ->assertSee('Email me a link to set a password')
            ->assertDontSee('action="'.route('account.social.unlink', ['account' => $apple->id]).'"', false)
            ->assertDontSee('action="'.route('account.password').'"', false);

        $this->delete(route('account.social.unlink', ['account' => $apple->id]))->assertSessionHasErrors('social');
        $this->assertModelExists($apple);

        // With a second connection, one of them can go
        $google = SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'subject' => 'g-1']);
        $this->delete(route('account.social.unlink', ['account' => $google->id]))->assertSessionHasNoErrors();
        $this->assertModelMissing($google);

        // The emailed link sets a password while signed in; then the last connection can go
        $this->post(route('account.password.link'))->assertRedirect(route('account').'#security')
            ->assertSessionHas('notification', fn (array $flash) => $flash['type'] === 'success');
        $this->post(route('account.password.link'))->assertSessionHas('notification', fn (array $flash) => $flash['message'] === 'Please wait a minute before asking for another link.');
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'N3w!Passw0rd#', 'password_confirmation' => 'N3w!Passw0rd#'])
            ->assertRedirect(route('account').'#security');
        $user->refresh();
        $this->assertTrue($user->hasPassword());
        $this->assertTrue(Hash::check('N3w!Passw0rd#', $user->password));
        $this->assertAuthenticatedAs($user);

        $this->delete(route('account.social.unlink', ['account' => $apple->id]))->assertSessionHasNoErrors();
        $this->assertModelMissing($apple);
        $this->get(route('account'))->assertOk()->assertDontSee('Connected accounts')->assertSee('Change password');
    }
}
