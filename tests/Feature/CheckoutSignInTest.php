<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\OTP;
use App\Models\Product;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Signing in inside checkout (as on Bake & Grill): the steps sit beside the order and come back to
 * it; a registered customer may use a code by email or SMS instead of the password there, and a new
 * number buys straight away (the name is asked at checkout, the password next time).
 */
class CheckoutSignInTest extends TestCase
{
    use RefreshDatabase;

    protected array $session;

    /** What the fake SMS gateway answers (500: it is down) */
    protected int $smsStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'http', 'sms.http.url' => 'https://sms.test/send', 'sms.http.body_template' => '{"to":"{to}","text":"{message}"}', 'sms.http.success_regex' => '']);
        app(SmsManager::class)->using(null);
        Http::fake(['sms.test/*' => fn () => Http::response($this->smsStatus === 200 ? 'ok' : 'gateway down', $this->smsStatus)]);

        $product = Product::factory()->create(['price' => 150, 'stock_quantity' => 5, 'name' => ['en' => 'Coconut oil']]);
        $token = Str::random(40);
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => $token, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 150]);
        $this->session = ['cart_token' => $token];
    }

    protected function lastCode(): string
    {
        return (string) OTP::where('purpose', 'login')->latest('id')->value('code');
    }

    protected function customer(): User
    {
        $user = User::factory()->create(['name' => 'Aishath', 'phone' => '7774321', 'email' => 'aishath@example.com', 'password' => Hash::make('Good-pass-123!')]);
        $user->forceFill(['phone_verified_at' => now(), 'has_password' => true])->save();

        return $user;
    }

    public function test_checkout_shows_the_sign_in_beside_the_order(): void
    {
        $this->withSession($this->session)->get(route('checkout'))->assertOk()
            ->assertSee('data-checkout-sign-in', false)->assertSee('Coconut oil')->assertSee('MVR 300.00')
            ->assertSee('name="checkout" value="1"', false)->assertDontSee('id="checkout-form"', false);

        // Nothing in the cart: back to the cart
        $this->app['session']->driver()->flush();
        $this->get(route('checkout'))->assertRedirect(route('cart'));
    }

    public function test_a_new_number_buys_straight_away_and_gives_a_name_at_checkout(): void
    {
        $this->withSession($this->session)->post(route('login.phone.start'), ['login' => '9123456', 'checkout' => '1'])->assertRedirect(route('checkout'));
        $this->get(route('checkout'))->assertSee('data-login-phone="code"', false)->assertSee('•••3456');

        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()])->assertRedirect(route('checkout'));
        $user = User::where('phone', '+9609123456')->sole();
        $this->assertAuthenticatedAs($user);

        // The checkout itself now, with the cart that came along and a name to fill in
        $this->get(route('checkout'))->assertOk()->assertSee('id="checkout-form"', false)->assertSee('data-checkout-name', false)->assertSee('Coconut oil');

        $this->post(route('orders.store'), ['shipping_phone' => '9123456', 'payment_method' => 'bml', 'agree_terms' => '1'])->assertSessionHasErrors('customer_name');

        // Next time, outside checkout, the name and password are asked for
        $this->post(route('logout'));
        $this->travel(2)->minutes();
        $this->post(route('login.phone.start'), ['login' => '9123456']);
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()])->assertRedirect(route('account.setup'));
    }

    public function test_a_registered_customer_can_use_a_code_by_sms_or_email_instead_of_the_password_at_checkout(): void
    {
        $user = $this->customer();

        $this->withSession($this->session)->post(route('login.phone.start'), ['login' => '7774321', 'checkout' => '1'])->assertRedirect(route('checkout'));
        $this->get(route('checkout'))->assertSee('data-login-phone="password"', false)->assertSee('data-sms-code', false)->assertSee('data-email-code', false);

        $this->post(route('login.phone.sms-code'))->assertRedirect(route('checkout'))->assertSessionHas('status', 'We have texted a 6-digit code to •••4321.');
        $this->assertSame('+9607774321', SmsMessage::sole()->to);
        $this->post(route('login.phone.verify'), ['code' => $this->lastCode()])->assertRedirect(route('checkout'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('checkout'))->assertOk()->assertSee('id="checkout-form"', false)->assertDontSee('data-checkout-name', false);
    }

    public function test_the_password_works_at_checkout_too(): void
    {
        $user = $this->customer();

        $this->withSession($this->session)->post(route('login.phone.start'), ['login' => 'aishath@example.com', 'checkout' => '1']);
        $this->post(route('login.phone.password'), ['password' => 'Good-pass-123!'])->assertRedirect(route('checkout'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_outside_checkout_there_is_no_sms_code_instead_of_the_password(): void
    {
        Notification::fake();
        $this->customer();

        $this->post(route('login.phone.start'), ['login' => '7774321']);
        $this->get(route('login'))->assertSee('data-login-phone="password"', false)->assertDontSee('data-sms-code', false);
        $this->post(route('login.phone.sms-code'))->assertRedirect(route('login'));
        Notification::assertNothingSent();
    }

    public function test_no_sms_code_button_for_an_account_without_a_verified_number(): void
    {
        $user = $this->customer();
        $user->forceFill(['phone_verified_at' => null])->save();

        $this->withSession($this->session)->post(route('login.phone.start'), ['login' => 'aishath@example.com', 'checkout' => '1']);
        $this->get(route('checkout'))->assertSee('data-login-phone="password"', false)->assertDontSee('data-sms-code', false);
        $this->post(route('login.phone.sms-code'))->assertRedirect(route('checkout'))->assertSessionHasErrors('login');
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_a_failed_text_message_is_reported_not_claimed_as_sent(): void
    {
        $this->smsStatus = 500;

        $this->withSession($this->session)->post(route('login.phone.start'), ['login' => '9123456', 'checkout' => '1'])
            ->assertRedirect(route('checkout'))->assertSessionHasErrors('login')->assertSessionMissing('status');
        $this->assertSame('failed', SmsMessage::sole()->status);
        $this->get(route('checkout'))->assertSee('data-login-phone="phone"', false);

        // The gateway is back: a retry straight away is allowed
        $this->smsStatus = 200;
        $this->post(route('login.phone.start'), ['login' => '9123456', 'checkout' => '1'])->assertSessionHas('status');
    }
}
