<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OTP;
use App\Models\Product;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Notifications\VerifyEmailCode;
use App\Notifications\VerifyPhoneCode;
use App\Services\OrderService;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phone verification by SMS at sign-up, and the customer's email / SMS notification preferences.
 */
class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected const PASSWORD = 'Str0ng!Pass#2026';

    protected function liveSms(): void
    {
        config(['sms.driver' => 'http', 'sms.http.url' => 'https://sms.test/send', 'sms.http.body_template' => '', 'sms.http.success_regex' => '']);
        app(SmsManager::class)->using(null);
        // Sign-up also checks the password against pwnedpasswords; keep that off the network
        Http::fake(['sms.test/*' => Http::response('OK', 200), 'api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }

    protected function smsRequests(): \Illuminate\Support\Collection
    {
        return collect(Http::recorded(fn ($request) => str_contains($request->url(), 'sms.test')))->map(fn ($pair) => $pair[0]);
    }

    protected function register(array $extra = [])
    {
        return $this->post('/register', array_merge([
            'name' => 'Aisha', 'email' => 'aisha@example.com', 'phone' => '777 1234',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'agree_terms' => '1',
        ], $extra));
    }

    public function test_with_the_log_driver_sign_up_stays_email_only(): void
    {
        config(['sms.driver' => 'log']);
        Notification::fake();

        $this->register()->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'aisha@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailCode::class);
        Notification::assertNotSentTo($user, VerifyPhoneCode::class);
        $this->assertSame(0, OTP::whereNotNull('phone')->count());

        $this->get(route('verification.notice'))->assertOk()->assertDontSee('name="phone_code"', false)->assertSee('Verify your email');
        $this->post(route('auth.send.phone.otp'))->assertSessionHasErrors('phone_code');
    }

    public function test_with_a_gateway_the_code_is_texted_and_verifies_the_phone(): void
    {
        $this->liveSms();

        $this->register()->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'aisha@example.com')->firstOrFail();

        $otp = OTP::where('phone', '7771234')->where('purpose', 'verification')->where('is_used', false)->firstOrFail();
        $sent = $this->smsRequests()->sole();
        $this->assertSame('+9607771234', $sent['to']);
        $this->assertStringContainsString($otp->code, $sent['message']);
        $this->assertDatabaseHas('sms_messages', ['to' => '+9607771234', 'status' => 'sent']);

        $this->get(route('verification.notice'))->assertOk()->assertSee('Verify your email and phone')->assertSee('name="phone_code"', false);

        $this->post(route('auth.verify.phone.otp'), ['phone_code' => '000000'])->assertSessionHasErrors('phone_code');
        $this->assertNull($user->fresh()->phone_verified_at);

        $this->post(route('auth.verify.phone.otp'), ['phone_code' => $otp->code])->assertSessionMissing('errors');
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertTrue($otp->fresh()->is_used);

        // Verified: the box is gone and resending does nothing
        $this->get(route('verification.notice'))->assertDontSee('name="phone_code"', false);
        $this->post(route('auth.send.phone.otp'))->assertRedirect(route('account'));
    }

    public function test_resending_the_phone_code_is_throttled(): void
    {
        $this->liveSms();
        $this->register();
        $user = User::where('email', 'aisha@example.com')->firstOrFail();

        // The sign-up text counts: a second one within the minute is refused
        $this->post(route('auth.send.phone.otp'))->assertSessionHasErrors('phone_code');
        $this->assertSame(1, SmsMessage::count());

        $this->travel(61)->seconds();
        $this->post(route('auth.send.phone.otp'))->assertSessionHas('status');
        $this->assertSame(2, SmsMessage::count());

        // Five an hour at most
        for ($i = 0; $i < 3; $i++) {
            $this->travel(61)->seconds();
            $this->post(route('auth.send.phone.otp'));
        }
        $this->assertSame(5, SmsMessage::count());
        $this->travel(61)->seconds();
        $this->post(route('auth.send.phone.otp'))->assertSessionHasErrors('phone_code');
        $this->assertSame(5, SmsMessage::count());

        // Only the latest code works
        $latest = OTP::where('phone', $user->phone)->where('is_used', false)->latest('id')->firstOrFail();
        $this->assertSame(1, OTP::where('phone', $user->phone)->where('is_used', false)->count());
        $this->post(route('auth.verify.phone.otp'), ['phone_code' => $latest->code]);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_notification_preferences_page_and_sms_needs_a_verified_phone(): void
    {
        $user = User::factory()->create(['phone' => '7770001', 'phone_verified_at' => null]);

        $this->get(route('account.notifications'))->assertRedirect(route('login'));

        $this->actingAs($user)->get(route('account'))->assertSee(route('account.notifications'));
        $this->get(route('account.notifications'))->assertOk()->assertSee('Order updates')->assertSee('verified mobile number');

        // Without a verified phone, SMS is refused and email stays
        $this->put(route('account.notifications.update'), ['order_updates' => 'sms', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email'])
            ->assertSessionHasErrors('order_updates');
        $this->assertSame('email', $user->fresh()->notificationPreference('order_updates'));

        $user->forceFill(['phone_verified_at' => now()])->save();
        $this->put(route('account.notifications.update'), ['order_updates' => 'sms', 'delivery_updates' => 'both', 'marketing' => 'email', 'security' => 'both'])
            ->assertRedirect(route('account.notifications'));

        $user->refresh();
        $this->assertSame(['order_updates' => 'sms', 'delivery_updates' => 'both', 'marketing' => 'email', 'security' => 'both'], $user->notification_preferences['customer']);
        $this->assertSame(['sms'], $user->notificationChannels('order_updates'));
        $this->assertSame(['mail', 'sms'], $user->notificationChannels('delivery_updates'));
        $this->get(route('account.notifications'))->assertOk()->assertSee('value="sms"', false);

        // A changed phone is unverified again, so SMS silently falls back to email
        $user->forceFill(['phone_verified_at' => null])->save();
        $this->assertSame(['mail'], $user->notificationChannels('order_updates'));
    }

    protected function smsCustomer(array $preferences): User
    {
        $customer = User::factory()->create(['phone' => '7770002', 'phone_verified_at' => now(), 'preferred_language' => 'dv']);
        $customer->forceFill(['notification_preferences' => ['customer' => $preferences]])->save();

        return $customer;
    }

    protected function shippedOrder(User $customer): Order
    {
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'processing', 'order_number' => 'ORD-SMS1']);
        $order->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'price' => 10]);
        app(OrderService::class)->updateOrderStatus($order, 'shipped');

        return $order->fresh();
    }

    public function test_order_notifications_follow_the_preferences(): void
    {
        Notification::fake();
        $customer = $this->smsCustomer(['order_updates' => 'email', 'delivery_updates' => 'both']);

        $order = $this->shippedOrder($customer);
        Notification::assertSentTo($customer, OrderStatusChanged::class, fn ($n, $channels) => $channels === ['mail', 'sms']);

        app(OrderService::class)->updateOrderStatus($order, 'delivered');
        $customer->forceFill(['notification_preferences' => ['customer' => ['delivery_updates' => 'sms']]])->save();
        $this->assertSame(['sms'], (new OrderStatusChanged($order->fresh()))->via($customer->fresh()));
        $this->assertSame(['mail'], (new OrderStatusChanged($order->fresh()))->via(User::factory()->create()));

        // The text itself is short and in the customer's language
        $text = (new OrderStatusChanged($order->fresh()))->toSms($customer);
        $this->assertStringContainsString('ORD-SMS1', $text);
        $this->assertLessThan(160, mb_strlen($text));
        app()->setLocale('dv');
        $dhivehi = (new OrderStatusChanged($order->fresh()))->toSms($customer);
        app()->setLocale('en');
        $this->assertStringContainsString('ORD-SMS1', $dhivehi);
        $this->assertNotSame($text, $dhivehi, 'translated to Dhivehi');
    }

    public function test_a_shipped_order_really_texts_the_customer_in_their_language(): void
    {
        $this->liveSms();
        $customer = $this->smsCustomer(['delivery_updates' => 'sms']);

        $this->shippedOrder($customer);

        $sent = $this->smsRequests()->sole();
        $this->assertSame('+9607770002', $sent['to']);
        $this->assertStringContainsString('ORD-SMS1', $sent['message']);
        $this->assertMatchesRegularExpression('/\p{Thaana}/u', $sent['message']);
        $this->assertDatabaseHas('sms_messages', ['to' => '+9607770002', 'status' => 'sent']);
    }
}
