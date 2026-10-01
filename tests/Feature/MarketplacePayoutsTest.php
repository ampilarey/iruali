<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Notifications\SellerOrderShipped;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarketplacePayoutsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $shopA;

    protected User $shopB;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->customer = User::factory()->create();
        $this->shopA = $this->seller('Island Crafts', 15);
        $this->shopB = $this->seller('Reefline Marine', null); // default rate
        Setting::set(['default_commission_rate' => 10]);
    }

    protected function seller(string $name, ?float $rate): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->forceFill(['commission_rate' => $rate])->save();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    /**
     * Place a real order through checkout: MVR 200 from shop A and MVR 100 from shop B.
     */
    protected function placeOrder(): Order
    {
        $a = Product::factory()->create(['seller_id' => $this->shopA->id, 'price' => 100, 'stock_quantity' => 10]);
        $b = Product::factory()->create(['seller_id' => $this->shopB->id, 'price' => 100, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $a->id, 'quantity' => 2, 'price' => 100]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $b->id, 'quantity' => 1, 'price' => 100]);

        $result = app(OrderService::class)->createOrderFromCart($this->customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'payment_method' => 'bml',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order'];
    }

    protected function part(Order $order, User $shop): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $shop->id)->firstOrFail();
    }

    public function test_checkout_splits_the_order_by_shop_with_commission(): void
    {
        $order = $this->placeOrder();

        $a = $this->part($order, $this->shopA);
        $this->assertEquals(200, $a->subtotal);
        $this->assertEquals(15, $a->commission_rate);
        $this->assertEquals(30, $a->commission_amount);
        $this->assertEquals(170, $a->seller_earnings);

        $b = $this->part($order, $this->shopB);
        $this->assertEquals(10, $b->commission_rate); // default
        $this->assertEquals(90, $b->seller_earnings);

        $this->assertSame(2, $order->sellerOrders()->count());
    }

    public function test_order_status_follows_the_slowest_shop(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->shopA);
        $this->post(route('seller.orders.status', $order), ['status' => 'processing']);
        $this->assertSame('processing', $order->fresh()->status);

        $this->post(route('seller.orders.status', $order), ['status' => 'shipped', 'tracking_note' => 'Hithadhoo ferry, ref 4411']);
        $this->assertSame('processing', $order->fresh()->status);
        Notification::assertSentTo($this->customer, SellerOrderShipped::class, fn ($n) => $n->part->tracking_note === 'Hithadhoo ferry, ref 4411');

        $this->actingAs($this->shopB);
        $this->post(route('seller.orders.status', $order), ['status' => 'processing']);
        $this->post(route('seller.orders.status', $order), ['status' => 'shipped']);
        $this->assertSame('shipped', $order->fresh()->status);
        Notification::assertSentTo($this->customer, OrderStatusChanged::class, fn ($n) => $n->order->status === 'shipped');

        $this->post(route('seller.orders.status', $order), ['status' => 'delivered']);
        $this->assertSame('shipped', $order->fresh()->status);
        $this->actingAs($this->shopA)->post(route('seller.orders.status', $order), ['status' => 'delivered']);
        $this->assertSame('delivered', $order->fresh()->status);

        $this->actingAs($this->customer)->get(route('orders.show', $order))
            ->assertSee('From Island Crafts')->assertSee('From Reefline Marine')->assertSee('Hithadhoo ferry, ref 4411');
    }

    public function test_admin_status_changes_carry_to_every_part_and_cancel_is_blocked_once_a_part_is_sent(): void
    {
        $order = $this->placeOrder();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.orders.status', $order), ['status' => 'processing']);
        $this->assertSame(['processing', 'processing'], $order->sellerOrders()->orderBy('id')->pluck('status')->all());

        $this->actingAs($this->shopA)->post(route('seller.orders.status', $order), ['status' => 'shipped']);

        // A shop has sent its part: the order can't be cancelled as a whole any more.
        $this->actingAs($admin)->post(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHas('error');
        $this->assertSame('processing', $order->fresh()->status);

        $this->actingAs($admin)->post(route('admin.orders.parts.status', [$order, $this->part($order, $this->shopB)]), ['status' => 'shipped']);
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_cancelling_a_pending_order_cancels_every_part_and_no_earnings_are_due(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->customer)->post(route('orders.cancel', $order));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(['cancelled'], $order->sellerOrders()->pluck('status')->unique()->values()->all());
        $this->assertSame('cancelled', $this->part($order, $this->shopA)->earningsState());
        $this->assertEquals(0, app(PayoutService::class)->balances($this->shopA)['available']);
    }

    public function test_earnings_become_payable_when_delivered_and_paid_then_are_paid_once(): void
    {
        $order = $this->placeOrder();
        $admin = $this->admin();
        $payouts = app(PayoutService::class);

        $this->actingAs($admin)->post(route('admin.orders.status', $order), ['status' => 'processing']);
        $this->post(route('admin.orders.status', $order), ['status' => 'shipped']);
        $this->post(route('admin.orders.status', $order), ['status' => 'delivered']);
        $this->assertEquals(['pending' => 170.0, 'available' => 0.0], array_intersect_key($payouts->balances($this->shopA), ['pending' => 1, 'available' => 1]));

        // BML confirms the card payment (what the webhook does on success)
        app(PaymentService::class)->confirm($order->fresh());
        $this->assertEquals(170, $payouts->balances($this->shopA)['available']);

        $this->get(route('admin.payouts'))->assertOk()->assertSee('Island Crafts')->assertSee('MVR 170.00');
        $this->get(route('admin.payouts.create', $this->shopA))->assertOk()->assertSee($order->order_number);

        $part = $this->part($order, $this->shopA);
        $this->post(route('admin.payouts.store', $this->shopA), ['parts' => [$part->id], 'reference' => 'BML-TRF-123'])->assertRedirect();

        $payout = SellerPayout::sole();
        $this->assertEquals(170, $payout->amount);
        $this->assertSame($payout->id, $part->fresh()->payout_id);
        $this->assertEquals(['available' => 0.0, 'paid' => 170.0], array_intersect_key($payouts->balances($this->shopA), ['available' => 1, 'paid' => 1]));

        // Paying the same part again does nothing
        $this->post(route('admin.payouts.store', $this->shopA), ['parts' => [$part->id], 'reference' => 'again'])->assertSessionHas('error');
        $this->assertSame(1, SellerPayout::count());

        // A shop's part can't be put in another shop's payout
        $this->post(route('admin.payouts.store', $this->shopB), ['parts' => [$part->id], 'reference' => 'x'])->assertSessionHas('error');

        $csv = $this->get(route('admin.payouts.show', [$payout, 'export' => 'csv']));
        $this->assertStringContainsString($order->order_number, $csv->streamedContent());

        $this->actingAs($this->shopA)->get(route('seller.earnings'))->assertOk()->assertSee('MVR 170.00')->assertSee('BML-TRF-123');
    }

    public function test_commission_rate_changes_apply_to_new_orders_only(): void
    {
        $first = $this->placeOrder();

        $this->actingAs($this->admin())->post(route('admin.sellers.commission', $this->shopA), ['commission_rate' => 5])->assertRedirect();
        $this->assertEquals(15, $this->part($first, $this->shopA)->commission_rate);

        $second = $this->placeOrder();
        $this->assertEquals(5, $this->part($second, $this->shopA)->commission_rate);
        $this->assertEquals(190, $this->part($second, $this->shopA)->seller_earnings);

        // Clearing the rate falls back to the default
        $this->post(route('admin.sellers.commission', $this->shopA), ['commission_rate' => '']);
        $this->assertNull($this->shopA->fresh()->commission_rate);
    }

    public function test_only_admins_see_payouts_and_sellers_only_their_own_earnings(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->shopA)->get(route('admin.payouts'))->assertForbidden();
        $this->actingAs($this->shopA)->get(route('seller.earnings'))->assertOk()->assertSee($order->order_number)->assertSee('15%');
        $this->actingAs($this->shopA)->get(route('seller.orders'))->assertOk()->assertSee('MVR 170.00')->assertDontSee('Reefline');
    }

    public function test_seller_saves_payout_bank_details(): void
    {
        $this->actingAs($this->shopA)->put('/seller/profile', [
            'name' => 'Aisha', 'business_name' => 'Island Crafts',
            'payout_bank_name' => 'Bank of Maldives', 'payout_account_name' => 'Island Crafts', 'payout_account_number' => '7730000123456',
        ])->assertRedirect();

        $this->assertSame('7730000123456', $this->shopA->fresh()->payout_account_number);

        $this->put('/seller/profile', ['name' => 'Aisha', 'business_name' => 'Island Crafts', 'payout_account_number' => 'abc'])
            ->assertSessionHasErrors('payout_account_number');
    }
}
