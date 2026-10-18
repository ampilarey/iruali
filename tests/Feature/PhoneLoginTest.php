<?php

namespace Tests\Feature;

use App\Models\OTP;
use App\Models\Role;
use App\Models\User;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Continue with a mobile number (as on Bake & Grill): a new number signs up with an SMS code and
 * sets a name and password; a number with a password signs in with it; "forgot password" by code.
 */
class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'http', 'sms.http.url' => 'https://sms.test/send', 'sms.http.body_template' => '{"to":"{to}","text":"{message}"}', 'sms.http.success_regex' => '']);
        app(SmsManager::class)->using(null);
        Http::fake(['sms.test/*' => Http::response('ok', 200)]);
    }

    protected function lastCode(): string
    {
        return (string) OTP::where('purpose', 'login')->latest('id')->value('code');
    }

    public function test_a_new_number_signs_up_with_a_code_then_sets_name_and_password(): void
    {
        $this->get(route('login.email'))->assertSee('data-login-phone-link', false);
        $this->get(route('login'))->assertOk()->assertSee('data-login-phone="phone"', false)->assertSee(route('login.email'), false);

        $this->post(route('login.phone.start'), ['phone' => '9123456'])->assertRedirect(route('login'));
        $this->get(route('login'))->assertSee('data-login-phone="code"', false)->assertSee('•••3456');
        $this->post(route('login.phone.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()])->assertRedirect(route('account.setup'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertFalse($user->hasPassword());
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame('sms', $user->notificationPreference('order_updates')); // no email: updates by SMS

        $this->get(route('account.setup'))->assertOk()->assertSee('data-account-setup', false);
        $this->post(route('account.setup.store'), ['name' => 'Aisha', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->post(route('account.setup.store'), ['name' => 'Aisha', 'password' => 'Strong-pass-1!', 'password_confirmation' => 'Strong-pass-1!'])->assertRedirect(route('home'));
        $user->refresh();
        $this->assertSame('Aisha', $user->name);
        $this->assertTrue($user->hasPassword());

        // Next time: the number asks for the password
        $this->post(route('logout'));
        $this->post(route('login.phone.start'), ['phone' => '912 3456']);
        $this->get(route('login'))->assertSee('data-login-phone="password"', false);
        $this->post(route('login.phone.password'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post(route('login.phone.password'), ['password' => 'Strong-pass-1!'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_checkout_can_wait_for_the_password_and_it_is_asked_again(): void
    {
        $this->get(route('checkout'))->assertRedirect(route('login')); // remembers where to go back
        $this->post(route('login.phone.start'), ['phone' => '7771234']);
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()])->assertRedirect(route('account.setup'));
        $this->post(route('account.setup.store'), ['name' => 'Ali', 'action' => 'later'])->assertRedirect(route('checkout'));
        $this->assertFalse(User::sole()->hasPassword());

        $this->post(route('logout'));
        $this->travel(2)->minutes(); // one code a minute per number
        $this->post(route('login.phone.start'), ['phone' => '7771234']);
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()])->assertRedirect(route('account.setup'));
    }

    public function test_forgot_password_sends_a_code_and_opens_the_new_password_form(): void
    {
        $user = User::factory()->create(['phone' => '7771234', 'password' => Hash::make('Old-pass-123!')]);
        $user->forceFill(['phone_verified_at' => now()])->save();

        $this->post(route('login.phone.start'), ['phone' => '7771234']);
        $this->post(route('login.phone.resend'))->assertRedirect(route('login'));
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()])->assertRedirect(route('account').'#security');
        $this->assertAuthenticatedAs($user);
        $this->put(route('account.password'), ['password' => 'New-pass-456!', 'password_confirmation' => 'New-pass-456!'])->assertRedirect(route('account'));
        $this->assertTrue(Hash::check('New-pass-456!', $user->fresh()->password));
        $this->assertSame(1, User::count()); // no second account for the number
    }

    public function test_staff_and_unverified_numbers(): void
    {
        $staff = User::factory()->create(['phone' => '7770001']);
        $staff->forceFill(['phone_verified_at' => now()])->save();
        $staff->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->post(route('login.phone.start'), ['phone' => '7770001'])->assertSessionHasErrors('phone');

        // Someone typed this number on their account but never verified it: the code decides who it belongs to
        $typo = User::factory()->create(['phone' => '7775555', 'email' => 'other@example.com']);
        $this->post(route('login.phone.start'), ['phone' => '7775555']);
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()]);
        $this->assertNull($typo->fresh()->phone);
        $this->assertNotSame($typo->id, auth()->id());
        $this->assertSame('+9607775555', User::find(auth()->id())->phone);

        $this->post(route('logout'));
        $this->post(route('login.phone.start'), ['phone' => '12345'])->assertSessionHasErrors('phone');
    }

    public function test_a_mobile_only_customer_cannot_lose_their_number_from_the_profile_form(): void
    {
        $this->post(route('login.phone.start'), ['phone' => '7771234']);
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()]);
        $this->put(route('account.update'), ['name' => 'Ali', 'phone' => '7779999'])->assertSessionHasErrors('phone');
    }
}
