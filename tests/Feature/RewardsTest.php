<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Loyalty points and referral rewards are given when an order is paid, once, and taken back on cancel.
 */
class RewardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set(['referral_referrer_points' => 100, 'referral_referee_points' => 50]);
    }

    protected function placeOrder(User $user, float $price = 1000): Order
    {
        $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $price]);

        $result = app(OrderService::class)->createOrderFromCart($user, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order'];
    }

    public function test_points_are_earned_only_when_the_order_is_paid_and_taken_back_on_cancel(): void
    {
        $user = User::factory()->create(['loyalty_points' => 0]);
        $order = $this->placeOrder($user, 1000);

        $this->assertGreaterThan(0, $order->loyalty_points_earned);
        $this->assertSame(0, $user->fresh()->loyalty_points, 'nothing earned on an unpaid order');

        app(PaymentService::class)->confirm($order);
        $this->assertSame($order->loyalty_points_earned, $user->fresh()->loyalty_points);
        $this->assertNotNull($order->fresh()->loyalty_points_awarded_at);

        // Confirming again (webhook + return URL) does not double the points
        app(PaymentService::class)->confirm($order->fresh());
        $this->assertSame($order->loyalty_points_earned, $user->fresh()->loyalty_points);

        // Cancelling a paid order takes them back, even below zero if they were spent
        $user->forceFill(['loyalty_points' => 2])->save();
        app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled');
        $this->assertSame(2 - $order->loyalty_points_earned, $user->fresh()->loyalty_points);
    }

    public function test_cancelling_an_unpaid_order_takes_nothing_away(): void
    {
        $user = User::factory()->create(['loyalty_points' => 30]);
        $order = $this->placeOrder($user, 1000);

        app(OrderService::class)->updateOrderStatus($order, 'cancelled');
        $this->assertSame(30, $user->fresh()->loyalty_points);
    }

    public function test_referral_reward_is_given_once_on_the_first_paid_order_and_never_at_sign_up(): void
    {
        $referrer = User::factory()->create(['loyalty_points' => 0, 'referral_code' => 'REF12345']);

        $this->post('/register', [
            'name' => 'Referee', 'email' => 'referee@example.com', 'password' => 'Str0ng!Pass#2024', 'password_confirmation' => 'Str0ng!Pass#2024',
            'agree_terms' => '1', 'referral_code' => 'REF12345',
        ])->assertRedirect();
        $referee = User::where('email', 'referee@example.com')->firstOrFail();
        $this->assertSame([0, 0], [$referrer->fresh()->loyalty_points, $referee->loyalty_points], 'no reward for signing up');

        $first = $this->placeOrder($referee, 100);
        $this->assertSame(0, $referrer->fresh()->loyalty_points, 'no reward until paid');

        app(PaymentService::class)->confirm($first);
        $this->assertSame(100, $referrer->fresh()->loyalty_points);
        $this->assertSame(50 + $first->loyalty_points_earned, $referee->fresh()->loyalty_points);
        $this->assertNotNull($referee->fresh()->referral_rewarded_at);

        $second = $this->placeOrder($referee, 100);
        app(PaymentService::class)->confirm($second);
        $this->assertSame(100, $referrer->fresh()->loyalty_points, 'rewarded once');
    }
}
