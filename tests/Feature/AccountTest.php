<?php

namespace Tests\Feature;

use App\Models\OTP;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Sign-up, email verification, password reset, profile and two-step sign-in.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected const PASSWORD = 'Str0ng!Pass#2026';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function registerWithTheFormsFields(array $extra = [])
    {
        return $this->post('/register', array_merge([
            'name' => 'Aisha', 'email' => 'aisha@example.com',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'agree_terms' => '1',
        ], $extra));
    }

    public function test_sign_up_with_exactly_the_forms_fields_creates_the_account_and_emails_a_code(): void
    {
        $this->registerWithTheFormsFields()->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'aisha@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->phone);
        $this->assertFalse($user->is_seller);
        $this->assertSame(0, $user->loyalty_points);
        $this->assertTrue($user->hasRole('customer'));
        Notification::assertSentTo($user, VerifyEmailCode::class, fn ($n) => strlen($n->otp->code) === 6);

        // Signed in but unverified: pages work and carry the reminder
        $this->get('/')->assertOk()->assertSee('Please verify your email address.');
        $this->get('/checkout')->assertStatus(302); // empty cart, not a verification wall
    }

    public function test_sign_up_accepts_a_phone_and_rejects_a_bad_one(): void
    {
        $this->registerWithTheFormsFields(['phone' => '777 1234'])->assertRedirect();
        $this->assertSame('7771234', User::where('email', 'aisha@example.com')->value('phone'));

        $this->post('/logout');
        $this->registerWithTheFormsFields(['email' => 'b@example.com', 'phone' => 'call-me'])->assertSessionHasErrors('phone');
    }

    public function test_nobody_can_make_themselves_a_seller_at_sign_up(): void
    {
        $this->registerWithTheFormsFields(['is_seller' => 1]);
        $user = User::where('email', 'aisha@example.com')->firstOrFail();
        $this->assertFalse($user->is_seller);
        $this->assertFalse($user->hasRole('seller'));
    }

    public function test_verification_code_only_works_for_the_signed_in_users_own_email(): void
    {
        $me = User::factory()->create(['email_verified_at' => null]);
        $other = User::factory()->create(['email_verified_at' => null]);
        $otherCode = OTP::createForEmail($other->email, 'verification')->code;

        // My code verifies me, and sends me on to where I was going
        $this->actingAs($me);
        session(['url.intended' => '/checkout']);
        $this->post(route('auth.send.email.otp'))->assertSessionHas('status');
        Notification::assertSentTo($me, VerifyEmailCode::class);
        $code = OTP::where('email', $me->email)->where('is_used', false)->latest('id')->value('code');
        $this->post(route('auth.verify.email.otp'), ['code' => $code])->assertRedirect('/checkout');
        $this->assertNotNull($me->fresh()->email_verified_at);

        // Someone else's code never verifies them from my session (the request's email is ignored)
        $this->post(route('auth.verify.email.otp'), ['email' => $other->email, 'code' => $otherCode])->assertSessionHasErrors('code');
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_password_reset_round_trip(): void
    {
        $user = User::factory()->create();
        $this->get(route('login'))->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk();

        // Unknown email: same reply, no mail, no account enumeration
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status');
        Notification::assertNothingSent();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'N3w!Passw0rd#', 'password_confirmation' => 'N3w!Passw0rd#'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('N3w!Passw0rd#', $user->fresh()->password));

        // The token is single use
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'An0ther!Pass#', 'password_confirmation' => 'An0ther!Pass#'])
            ->assertSessionHasErrors('email');
    }

    public function test_customer_edits_profile_and_changes_password(): void
    {
        $user = User::factory()->create(['phone' => '7770000', 'phone_verified_at' => now()]);

        $this->actingAs($user)->get(route('account'))->assertOk()->assertSee(route('account.edit'))->assertSee(route('profile.2fa.setup'))->assertDontSee('href="#"', false);
        $this->get(route('account.edit'))->assertOk();

        $this->put(route('account.update'), ['name' => 'Aisha Ali', 'phone' => '999 1234', 'address' => 'M. Blue House', 'city' => 'Hithadhoo', 'state' => 'Addu', 'preferred_language' => 'dv'])
            ->assertRedirect(route('account'));
        $user->refresh();
        $this->assertSame(['Aisha Ali', '9991234', 'Hithadhoo', 'dv'], [$user->name, $user->phone, $user->city, $user->preferred_language]);
        $this->assertNull($user->phone_verified_at, 'a changed phone is no longer verified');

        $this->put(route('account.password'), ['current_password' => 'wrong', 'password' => 'N3w!Passw0rd#', 'password_confirmation' => 'N3w!Passw0rd#'])->assertSessionHasErrors('current_password');
        $this->put(route('account.password'), ['current_password' => 'password', 'password' => 'N3w!Passw0rd#', 'password_confirmation' => 'N3w!Passw0rd#'])->assertRedirect(route('account'));
        $this->assertTrue(Hash::check('N3w!Passw0rd#', $user->fresh()->password));
    }

    public function test_two_step_sign_in_setup_login_recovery_and_disable(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // more than 5 code attempts below
        $user = User::factory()->create();
        $google2fa = new Google2FA;

        // Setup page shows a QR code and keeps the pending key in the session
        $this->actingAs($user)->get(route('profile.2fa.setup'))->assertOk()->assertSee('<svg', false)->assertSee('Turn on two-step sign-in');
        $secret = session('2fa_pending_secret');
        $this->assertNotEmpty($secret);

        $this->post(route('profile.2fa.enable'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->isTwoFactorEnabled());

        $this->post(route('profile.2fa.enable'), ['code' => $google2fa->getCurrentOtp($secret)])->assertRedirect(route('profile.2fa.setup'));
        $this->assertTrue($user->fresh()->isTwoFactorEnabled());
        $codes = session('2fa_recovery_codes');
        $this->assertCount(8, $codes);
        $this->get(route('profile.2fa.setup'))->assertOk()->assertSee($codes[0]);
        // Only hashes are stored
        $this->assertStringStartsWith('$2y$', $user->fresh()->getRecoveryCodes()[0]);

        // Sign in needs the code
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('2fa.show'));
        $this->assertGuest();
        $this->post(route('2fa.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post(route('2fa.verify'), ['code' => $google2fa->getCurrentOtp($secret)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        // A recovery code works once
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('2fa.verify'), ['code' => $codes[1]])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, $user->fresh()->getRecoveryCodes());
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('2fa.verify'), ['code' => $codes[1]])->assertSessionHasErrors('code');

        // Turning it off needs the password
        $this->actingAs($user->fresh())->post(route('profile.2fa.disable'), ['current_password' => 'nope'])->assertSessionHasErrors('current_password');
        $this->post(route('profile.2fa.disable'), ['current_password' => 'password'])->assertRedirect(route('account'));
        $this->assertFalse($user->fresh()->isTwoFactorEnabled());
    }

    public function test_register_page_links_the_real_policies(): void
    {
        $this->get(route('register'))->assertOk()->assertSee(route('policies.terms'))->assertSee(route('policies.privacy'))->assertSee('name="phone"', false);
    }
}
