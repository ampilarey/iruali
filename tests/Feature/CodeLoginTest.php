<?php

namespace Tests\Feature;

use App\Models\OTP;
use App\Models\Role;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\SignInCode;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Signing in with a 6-digit code by SMS (verified numbers) or email, without a password, and setting
 * a new password straight after (the "forgot password" route by SMS).
 */
class CodeLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'http', 'sms.http.url' => 'https://sms.test/send', 'sms.http.body_template' => '{"to":"{to}","text":"{message}"}', 'sms.http.success_regex' => '']);
        app(SmsManager::class)->using(null);
        Http::fake(['sms.test/*' => Http::response('ok', 200)]);
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['phone' => '777 1234', 'password' => Hash::make('Old-pass-123!')], $attributes));
        $user->forceFill(['phone_verified_at' => now()])->save();

        return $user;
    }

    protected function lastCode(): string
    {
        return (string) OTP::lastCodeFor(null, 'login'); // only its hash is stored
    }

    public function test_a_customer_signs_in_with_a_code_by_sms_and_sets_a_new_password(): void
    {
        $user = $this->customer();
        $this->get(route('login.email'))->assertOk()->assertSee(route('login.code'), false);

        $this->post(route('login.code.send'), ['login' => '+960 777-1234'])->assertRedirect(route('login.code'))
            ->assertSessionHas('status', 'If that number belongs to an iruali account, we have texted it a code.');
        $sms = SmsMessage::where('type', 'login_code')->sole();
        $this->assertSame('+9607771234', $sms->to);
        // Texted whole, kept in the SMS log with the code hidden
        $this->assertStringNotContainsString($this->lastCode(), $sms->message);
        $this->assertStringContainsString('••••••', $sms->message);

        $this->get(route('login.code'))->assertOk()->assertSee('•••1234')->assertSee('data-login-code-verify', false);
        $this->post(route('login.code.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('login.code.verify'), ['code' => $this->lastCode()])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        // New password without the old one, right after
        $this->get(route('account'))->assertOk()->assertSee('data-fresh-code', false);
        $this->put(route('account.password'), ['password' => 'New-pass-456!', 'password_confirmation' => 'New-pass-456!'])->assertRedirect(route('account'));
        $this->assertTrue(Hash::check('New-pass-456!', $user->fresh()->password));
        // ...once: the next change needs the current password again
        $this->put(route('account.password'), ['password' => 'Other-pass-789!', 'password_confirmation' => 'Other-pass-789!'])->assertSessionHasErrors('current_password');
    }

    public function test_by_email_too_and_the_email_counts_as_verified(): void
    {
        Notification::fake();
        // An account opened with this email (no other way in yet): proving the email signs in and confirms it
        $user = $this->customer(['email' => 'aisha@example.com', 'email_verified_at' => null]);
        $user->forceFill(['phone_verified_at' => null])->save();

        $this->post(route('login.code.send'), ['login' => 'Aisha@Example.com'])->assertSessionHas('status');
        Notification::assertSentOnDemand(SignInCode::class, fn ($n, $channels, $to) => $channels === ['mail'] && $to->routes['mail'] === 'aisha@example.com');

        $this->post(route('login.code.verify'), ['code' => $this->lastCode()]);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_it_says_the_same_whoever_asks_and_sends_nothing_to_unknown_or_unverified_numbers_or_staff(): void
    {
        $this->customer(['phone' => '9991111'])->forceFill(['phone_verified_at' => null])->save();
        $staff = $this->customer(['phone' => '7775555', 'email' => 'ops@iruali.test']);
        $staff->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        foreach (['7770000', '9991111', '7775555'] as $number) {
            $this->post(route('login.code.send'), ['login' => $number])->assertSessionHas('status', 'If that number belongs to an iruali account, we have texted it a code.');
        }
        $this->post(route('login.code.send'), ['login' => 'ops@iruali.test'])->assertSessionHas('status');
        $this->assertSame(0, OTP::where('purpose', 'login')->count());
        $this->assertSame(0, SmsMessage::count());

        $this->post(route('login.code.send'), ['login' => '3321234'])->assertSessionHasErrors('login');
    }

    public function test_limits_one_code_a_minute_and_five_wrong_tries(): void
    {
        $this->customer();
        $this->post(route('login.code.send'), ['login' => '7771234']);
        $this->post(route('login.code.send'), ['login' => '7771234'])->assertSessionHasErrors('login');
        $this->assertSame(1, SmsMessage::count());

        $code = $this->lastCode();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.code.verify'), ['code' => '111111'])->assertSessionHasErrors('code');
        }
        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('login.code'));
        $this->assertGuest();
        $this->assertNull(OTP::verify('+9607771234', $code, 'login'), 'the code was cancelled');
    }

    public function test_two_step_sign_in_still_asks_for_the_authenticator_code(): void
    {
        $user = $this->customer();
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('secret')])->save();

        $this->post(route('login.code.send'), ['login' => '7771234']);
        $this->post(route('login.code.verify'), ['code' => $this->lastCode()])->assertRedirect(route('2fa.show'));
        $this->assertGuest();
    }

    public function test_without_an_sms_gateway_it_asks_for_the_email(): void
    {
        config(['sms.driver' => 'log']);
        app(SmsManager::class)->using(null);
        $this->customer();

        $this->get(route('login.code'))->assertOk()->assertSee('Email address');
        $this->post(route('login.code.send'), ['login' => '7771234'])->assertSessionHasErrors('login');
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_an_email_typed_onto_someone_elses_account_does_not_hand_that_account_over(): void
    {
        Notification::fake();
        // Opened with one person's mobile number, with another person's email typed onto it (never confirmed)
        $theirs = $this->customer(['email' => 'victim@example.com', 'email_verified_at' => null, 'phone' => '7771234', 'phone_verified_at' => now()]);

        $this->post(route('login.code.send'), ['login' => 'victim@example.com']);
        $this->post(route('login.code.verify'), ['code' => $this->lastCode()])->assertRedirect(route('login.email-taken'));

        // The email owner is not signed in to that account: they choose
        $this->assertGuest();
        $this->get(route('login.email-taken'))->assertOk()->assertSee('victim@example.com')->assertSee('•••1234');

        // "It is not my account": the email comes off it, free for them to use; the account keeps its own way in
        $this->post(route('login.email-taken.release'))->assertRedirect(route('register'));
        $this->assertGuest();
        $this->assertNull($theirs->fresh()->email);
        $this->assertTrue($theirs->fresh()->isPhoneVerified());
        $this->assertSame($theirs->id, User::findByVerifiedPhone('7771234')?->id);

        // The choice can't be replayed later
        $this->post(route('login.email-taken.release'))->assertRedirect(route('login'));
    }

    public function test_an_account_with_no_other_way_in_is_still_claimed_by_proving_its_email(): void
    {
        Notification::fake();
        // Registered with someone's email and a password they chose, then two-step turned on
        $premade = $this->customer(['email' => 'owner@example.com', 'email_verified_at' => null]);
        $premade->forceFill(['phone' => null, 'phone_verified_at' => null, 'two_factor_enabled' => true, 'two_factor_secret' => encrypt('x')])->save();

        $this->post(route('login.code.send'), ['login' => 'owner@example.com']);
        $this->post(route('login.code.verify'), ['code' => $this->lastCode()]);

        $this->assertAuthenticatedAs($premade);
        $this->assertFalse($premade->fresh()->isTwoFactorEnabled(), 'the two-step sign-in the other person set up is gone');
        $this->assertFalse($premade->fresh()->hasPassword());
    }
}
