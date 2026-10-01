<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PaymentUpdated;
use App\Services\OrderNotifier;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guest checkout, behind the guest_checkout_enabled setting.
 */
class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected const PASSWORD = 'Str0ng!Pass#2026';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();
    }

    protected function seller(): User
    {
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Crafts']);
        $seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $seller;
    }

    /**
     * Put a product in a guest cart (keyed by the session's cart token) and return the session to use.
     */
    protected function guestCartWith(Product $product): array
    {
        $token = Str::random(40);
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => $token, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $product->price]);

        return ['cart_token' => $token];
    }

    protected function guestOrderFields(array $overrides = []): array
    {
        return array_merge([
            'guest_email' => 'Visitor@Example.com', 'guest_name' => 'Mariyam',
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu',
            'shipping_zip' => '', 'shipping_country' => 'Maldives', 'shipping_phone' => '777 1234',
            'delivery_zone' => 'islands', 'payment_method' => 'bml', 'agree_terms' => '1',
        ], $overrides);
    }

    public function test_with_the_setting_off_nothing_changes(): void
    {
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5]);
        $session = $this->guestCartWith($product);

        $this->withSession($session)->get('/cart')->assertOk()
            ->assertSee('Sign in to check out')->assertDontSee(route('checkout.guest'));
        $this->withSession($session)->get('/checkout')->assertRedirect('/login');
        $this->withSession($session)->get('/checkout/guest')->assertRedirect('/login');
        $this->withSession($session)->post('/checkout/guest', $this->guestOrderFields())->assertForbidden();
        $this->assertSame(0, Order::count());

        // The setting is editable in Admin → Settings
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('name="guest_checkout_enabled"', false);
        $this->actingAs($admin)->put('/admin/settings', [
            'loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50, 'guest_checkout_enabled' => '1',
        ])->assertRedirect(route('admin.settings'));
        $this->assertTrue(\App\Support\GuestCheckout::enabled());
    }

    public function test_a_guest_can_place_an_order_and_pay_through_bml(): void
    {
        Setting::set(['guest_checkout_enabled' => 1]);
        $seller = $this->seller();
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5, 'seller_id' => $seller->id]);
        $session = $this->guestCartWith($product);

        $this->withSession($session)->get('/cart')->assertOk()->assertSee(route('checkout.guest'))->assertSee('Proceed to checkout');
        $this->withSession($session)->get('/checkout/guest')->assertOk()
            ->assertSee('name="guest_email"', false)
            ->assertSee('name="guest_name"', false)
            ->assertSee(route('checkout.guest.store'), false)
            ->assertDontSee('Loyalty Points')
            ->assertDontSee('name="save_address"', false);

        $this->withSession($session)->post('/checkout/guest', $this->guestOrderFields(['guest_email' => '']))->assertSessionHasErrors('guest_email');
        $this->withSession($session)->post('/checkout/guest', $this->guestOrderFields(['guest_name' => '']))->assertSessionHasErrors('guest_name');

        $this->withSession($session)->post('/checkout/guest', $this->guestOrderFields())->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertNull($order->user_id);
        $this->assertTrue($order->isGuest());
        $this->assertSame('visitor@example.com', $order->guest_email);
        $this->assertSame('Mariyam', $order->guest_name);
        $this->assertSame(40, strlen($order->guest_token));
        $this->assertSame('7771234', $order->shipping_phone);
        $this->assertSame('islands', $order->delivery_zone);
        $this->assertEquals(275, $order->total_amount);
        $this->assertSame(0, $order->loyalty_points_earned, 'guests earn no points');
        $this->assertSame(0, $order->points_redeemed);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertSame(0, Cart::where('session_id', $session['cart_token'])->where('status', 'active')->count(), 'the guest cart is used up');

        // The confirmation email goes to the guest's address, the shop gets its new-order email
        Notification::assertSentOnDemand(OrderPlaced::class, fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'visitor@example.com' && $n->order->is($order));
        Notification::assertSentTo($seller, NewSellerOrder::class);

        // The email's link is the signed guest page
        $mail = (new OrderPlaced($order))->toMail(new AnonymousNotifiable);
        $this->assertSame($order->guestUrl(), $mail->actionUrl);
        $this->assertStringContainsString('Mariyam', $mail->greeting);

        // Paying marks the order paid; BML sends the guest back to their signed page, not to /orders
        Notification::fake();
        $this->withSession($session)->get(route('payments.bml.return', $order))->assertRedirect($order->guestUrl());
        app(PaymentService::class)->confirm($order->fresh());
        $this->assertSame('paid', $order->fresh()->payment_status);
        Notification::assertSentOnDemand(PaymentUpdated::class);
    }

    public function test_guest_order_pages_need_the_signed_link_with_the_right_token(): void
    {
        Setting::set(['guest_checkout_enabled' => 1]);
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5]);
        $session = $this->guestCartWith($product);
        $this->withSession($session)->post('/checkout/guest', $this->guestOrderFields());
        $order = Order::sole();

        $this->get($order->guestUrl())->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Create an account to track your orders')
            ->assertSee(route('register', ['email' => $order->guest_email]), false)
            ->assertSee('Pay '.\App\Support\Money::format($order->total_amount).' now')
            ->assertSee('M. Blue House');
        $this->get($order->guestUrl('receipt'))->assertOk()->assertSee($order->order_number)->assertSee('Mariyam');

        // No signature, a wrong token, or a tampered signature: no access
        $this->get(route('guest.orders.show', ['order' => $order, 'token' => $order->guest_token]))->assertForbidden();
        $this->get(URL::signedRoute('guest.orders.show', ['order' => $order, 'token' => 'wrong-token']))->assertForbidden();
        $this->get($order->guestUrl().'x')->assertForbidden();

        // Pay now from the guest page starts a BML payment (the fake BML always answers with the same
        // transaction id, so the first attempt is cleared to keep transaction ids unique)
        $order->paymentTransactions()->delete();
        $this->post($order->guestUrl('pay'))->assertRedirect('https://pay.bml.test/txn_test');
        $this->assertSame(1, $order->paymentTransactions()->count());

        // The public tracking page (order number) still works for guest orders
        $this->post('/track', ['order_code' => $order->order_number])->assertRedirect();
        $this->get(URL::temporarySignedRoute('order.track.show', now()->addMinutes(5), $order))->assertOk()->assertSee($order->order_number);

        // Admin and shop pages show the guest with their email
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get('/admin/orders')->assertOk()->assertSee('Mariyam')->assertSee('Guest')->assertSee('visitor@example.com');
        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Guest')->assertSee('visitor@example.com');
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $seller = $product->seller;
        $seller->update(['is_seller' => true, 'seller_approved' => true]);
        $seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->actingAs($seller)->get('/seller/orders')->assertOk()->assertSee('Mariyam')->assertSee('Guest');
        $this->actingAs($seller)->get('/seller/orders/'.$order->id)->assertOk()->assertSee('visitor@example.com');
        $this->actingAs($seller)->get('/seller/dashboard')->assertOk();
    }

    public function test_registering_with_the_guest_email_attaches_the_orders(): void
    {
        Setting::set(['guest_checkout_enabled' => 1]);
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5]);
        $session = $this->guestCartWith($product);
        $this->withSession($session)->post('/checkout/guest', $this->guestOrderFields());
        $order = Order::sole();
        $other = Order::factory()->create(['user_id' => null, 'guest_email' => 'someone@else.com', 'guest_name' => 'Other', 'guest_token' => Str::random(40)]);

        $this->get(route('register', ['email' => 'visitor@example.com']))->assertOk()->assertSee('value="visitor@example.com"', false);

        $this->post('/register', [
            'name' => 'Mariyam', 'email' => 'Visitor@example.com',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'agree_terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'Visitor@example.com')->sole();
        $this->assertSame($user->id, $order->fresh()->user_id);
        $this->assertNull($other->fresh()->user_id);

        // Now it is in My Orders, and the signed guest link still opens it
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee($order->order_number);
        $this->actingAs($user)->get('/orders/'.$order->id)->assertOk();
        $this->get($order->guestUrl())->assertOk();
        $this->assertSame('Mariyam', $order->fresh()->customerName());
    }

    public function test_notifications_handle_an_order_without_a_user(): void
    {
        $guest = Order::factory()->create(['user_id' => null, 'guest_email' => 'g@example.com', 'guest_name' => 'Guest G', 'guest_token' => Str::random(40), 'status' => 'pending', 'payment_status' => 'unpaid']);
        $guest->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'price' => 10]);
        $noEmail = Order::factory()->create(['user_id' => null, 'guest_email' => null, 'guest_token' => null, 'status' => 'pending']);

        app(OrderNotifier::class)->statusChanged($guest);
        Notification::assertSentOnDemand(OrderStatusChanged::class, fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'g@example.com');
        $mail = (new OrderStatusChanged($guest))->toMail(new AnonymousNotifiable);
        $this->assertStringContainsString('Guest G', $mail->greeting);
        $this->assertSame($guest->guestUrl(), $mail->actionUrl);

        app(OrderNotifier::class)->orderPlaced($noEmail);
        app(OrderNotifier::class)->paymentUpdated($noEmail);
        Notification::assertNotSentTo(new AnonymousNotifiable, OrderPlaced::class);

        // Status changes through OrderService notify the guest too, and awarding rewards is a no-op
        app(OrderService::class)->updateOrderStatus($guest, 'processing');
        $this->assertSame('processing', $guest->fresh()->status);
        app(OrderService::class)->awardRewards($guest->fresh());
        $this->assertSame(1, Order::whereNull('user_id')->where('id', $guest->id)->count());

        // An account order still goes to the account holder's inbox
        $user = User::factory()->create();
        $mine = Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        app(OrderNotifier::class)->statusChanged($mine);
        Notification::assertSentTo($user, OrderStatusChanged::class);
        $this->assertSame(route('orders.show', $mine), $mine->customerUrl());
    }

    public function test_signed_in_customers_are_sent_to_the_normal_checkout(): void
    {
        Setting::set(['guest_checkout_enabled' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/checkout/guest')->assertRedirect(route('checkout'));
        $this->actingAs($user)->post('/checkout/guest', $this->guestOrderFields())->assertForbidden();
    }
}
