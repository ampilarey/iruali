<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\FulfilmentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PreorderConfirmService;
use App\Services\PreorderService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Orders, stock and checkout (docs/AUDIT_FIX_PLAN.md batch 3).
 */
class OrderFlowFixesTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected User $shop;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75, 'return_window_days' => 7]);
        $this->shop = $this->seller('Reef Goods');
        $this->customer = User::factory()->create(['loyalty_points' => 500]);
        $this->enableBml();
    }

    // ---- 3.2: an unpaid card order ---------------------------------------------------------------

    public function test_a_shop_neither_sees_nor_moves_a_card_order_until_it_is_paid(): void
    {
        $product = $this->stockedProduct($this->shop, 100, 10);
        $order = $this->order($this->customer, [[$product, 2]])['order'];
        $part = $order->sellerOrders()->sole();

        $this->assertSame([], app(FulfilmentService::class)->nextStatuses($part));
        $this->actingAs($this->shop)->get(route('seller.orders'))->assertOk()->assertDontSee($order->order_number);
        $this->get(route('seller.orders.show', $order))->assertNotFound();
        $this->post(route('seller.orders.status', $order), ['status' => 'processing'])->assertNotFound();
        $this->assertSame(['pending', 'pending'], [$part->fresh()->status, $order->fresh()->status]);

        // Never paid: the clean-up still cancels it and the stock comes back
        $this->travel(25)->hours();
        $this->artisan('orders:cancel-unpaid-card')->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_once_paid_the_shop_sees_it_and_starts_on_it(): void
    {
        $product = $this->stockedProduct($this->shop, 100, 10);
        $order = $this->paidOrder($this->customer, [[$product, 1]]);

        $this->actingAs($this->shop)->get(route('seller.orders'))->assertOk()->assertSee($order->order_number);
        $this->post(route('seller.orders.status', $order), ['status' => 'processing'])->assertSessionHas('success');
        $this->assertSame('processing', $order->sellerOrders()->sole()->status);
        $this->assertTrue((new Order(['payment_method' => 'cod', 'payment_status' => 'unpaid']))->mayBeFulfilled(), 'cash on delivery is paid at the door');
    }

    // ---- 3.3: the stock page --------------------------------------------------------------------

    public function test_saving_the_stock_page_never_writes_over_a_sale_made_while_it_was_open(): void
    {
        $mat = $this->stockedProduct($this->shop, 100, 5);
        $rope = $this->stockedProduct($this->shop, 50, 10);

        // The page opens with mat 5 and rope 10; then a customer buys 2 mats
        $this->paidOrder($this->customer, [[$mat, 2]]);
        $this->assertSame(3, $mat->fresh()->stock_quantity);

        // The shop changes only the rope
        $this->actingAs($this->shop)->put(route('seller.stock.update'), [
            'products' => [$mat->id => 5, $rope->id => 12],
            'original' => ['products' => [$mat->id => 5, $rope->id => 10]],
        ])->assertRedirect();
        $this->assertSame([3, 12], [$mat->fresh()->stock_quantity, $rope->fresh()->stock_quantity]);

        // The shop adds 3 mats to what it saw (5 → 8): the 2 sold stay sold
        $this->put(route('seller.stock.update'), ['products' => [$mat->id => 8], 'original' => ['products' => [$mat->id => 5]]]);
        $this->assertSame(6, $mat->fresh()->stock_quantity);

        // Never below zero
        $this->put(route('seller.stock.update'), ['products' => [$mat->id => 0], 'original' => ['products' => [$mat->id => 20]]]);
        $this->assertSame(0, $mat->fresh()->stock_quantity);
    }

    public function test_more_stock_on_the_stock_page_goes_to_waiting_pre_orders_first(): void
    {
        $product = $this->preorderProduct($this->shop);
        $order = $this->paidOrder($this->customer, [[$product, 2]]);
        $this->assertTrue((bool) $order->items()->sole()->is_preorder);

        $this->actingAs($this->shop)->put(route('seller.stock.update'), ['products' => [$product->id => 5], 'original' => ['products' => [$product->id => 0]]]);

        $this->assertNotNull($order->items()->sole()->preorder_allocated_at, 'the paid pre-order got its units');
        $this->assertSame(3, $product->fresh()->stock_quantity, 'the rest is on sale');
    }

    // ---- 3.4: one tap, one order ----------------------------------------------------------------

    public function test_a_double_tap_places_one_order(): void
    {
        $product = $this->stockedProduct($this->shop, 100, 10);
        $cart = $this->cartWith($this->customer, [[$product, 1]]);
        $cart->load('items.product'); // both requests loaded the cart before either placed the order
        $second = Cart::with('items.product')->find($cart->id);

        $this->assertTrue(app(OrderService::class)->createOrderFromCart($this->customer, $this->shippingData(), $cart)['success']);
        $this->assertFalse(app(OrderService::class)->createOrderFromCart($this->customer, $this->shippingData(), $second)['success']);
        $this->assertSame(1, Order::count());
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    // ---- 3.5 and 3.7: still on sale, also through the app ---------------------------------------

    public function test_the_app_cannot_order_a_product_taken_off_sale(): void
    {
        $product = $this->stockedProduct($this->shop, 100, 10);
        $this->cartWith($this->customer, [[$product, 1]]);
        $product->update(['is_active' => false]);
        Sanctum::actingAs($this->customer, ['*']);

        $this->postJson('/api/v1/orders', $this->shippingData())->assertStatus(400);
        $this->assertSame(0, Order::count());
    }

    public function test_the_app_does_not_buy_a_sold_out_item_as_a_pre_order_unless_the_customer_agreed(): void
    {
        $product = $this->preorderProduct($this->shop);
        $this->cartWith($this->customer, [[$product, 1]]);
        Sanctum::actingAs($this->customer, ['*']);

        $this->postJson('/api/v1/orders', $this->shippingData())->assertStatus(400);
        $this->assertSame(0, Order::count());

        $this->postJson('/api/v1/orders', $this->shippingData() + ['accept_preorders' => true])->assertSuccessful();
        $this->assertTrue((bool) Order::sole()->items()->sole()->is_preorder);
    }

    public function test_removing_a_sellers_access_takes_their_products_off_sale(): void
    {
        $product = $this->stockedProduct($this->shop, 100, 10);
        $this->cartWith($this->customer, [[$product, 1]]);
        $admin = $this->staffMember('admin');

        $this->actingAs($admin)->post(route('admin.sellers.reject', $this->shop->id))->assertRedirect();

        $this->assertFalse((bool) $product->fresh()->is_active);
        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shippingData());
        $this->assertFalse($result['success']);
    }

    // ---- 3.6: paid after the stock arrived ------------------------------------------------------

    public function test_a_pre_order_paid_after_its_stock_arrived_gets_that_stock(): void
    {
        $product = $this->preorderProduct($this->shop);
        $order = $this->order($this->customer, [[$product, 2]])['order']; // a card order, not paid yet

        // The stock arrives while it is unpaid: it goes on sale
        app(PreorderService::class)->receiveStock($product->fresh(), [0 => 4]);
        $this->assertSame(4, $product->fresh()->stock_quantity);

        // Then it is paid: two of the four on the shelf are its
        app(PaymentService::class)->confirm($order->fresh());
        $this->assertNotNull($order->items()->sole()->preorder_allocated_at);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    // ---- 3.9: the shop never answers ------------------------------------------------------------

    public function test_a_pre_order_the_shop_never_confirms_is_cancelled_after_a_week(): void
    {
        $product = $this->preorderProduct($this->shop);
        $order = $this->order($this->customer, [[$product, 1]])['order'];
        $order->forceFill(['confirm_first' => true, 'pay_days' => 3])->save();

        $this->travel(6)->days();
        $this->assertSame(0, app(PreorderConfirmService::class)->expireUnanswered());
        $this->travel(2)->days();
        $this->artisan('preorders:balance-reminders')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->availability_declined_at);
        $this->assertSame(0, app(PreorderService::class)->waitingUnits($product->id), 'its place on the pre-order list is free again');
    }

    // ---- 3.10 and 3.11: what the checkout page showed --------------------------------------------

    public function test_a_failed_order_keeps_the_voucher_and_points_for_the_next_try(): void
    {
        Voucher::create(['code' => 'SAVE10', 'type' => 'percent', 'amount' => 10, 'is_active' => true, 'valid_until' => now()->addMonth()]);
        $product = $this->stockedProduct($this->shop, 100, 10);
        $cart = $this->cartWith($this->customer, [[$product, 1]]);
        $this->actingAs($this->customer);
        Session::put(['voucher_code' => 'SAVE10', 'points_redeemed' => 20]);

        // A step inside the order fails after the voucher and points were worked out (here: the total is
        // more than the checkout page showed)
        Session::put(OrderService::SESSION_GOODS_SHOWN, 10.0);
        $this->assertFalse(app(OrderService::class)->createOrderFromCart($this->customer, $this->shippingData(), $cart->fresh())['success']);

        $this->assertSame('SAVE10', Session::get('voucher_code'));
        $this->assertSame(20, Session::get('points_redeemed'));
        $this->assertSame(0, (int) Voucher::where('code', 'SAVE10')->value('used_count'));

        // The next try uses them, then they are used up
        Session::put(OrderService::SESSION_GOODS_SHOWN, 70.0);
        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shippingData(), $cart->fresh());
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals(10, $result['order']->voucher_discount);
        $this->assertNull(Session::get('voucher_code'));
    }

    public function test_a_higher_total_than_the_checkout_page_showed_is_not_charged(): void
    {
        $product = $this->stockedProduct($this->shop, 100, 10);
        $cart = $this->cartWith($this->customer, [[$product, 1]]);
        $this->actingAs($this->customer);
        Session::put(OrderService::SESSION_GOODS_SHOWN, 100.0);

        // The shop raised the price after the page was shown
        $product->update(['price' => 150]);
        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shippingData(), $cart->fresh());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('changed', $result['message']);

        // Seen again on the checkout page: it goes through
        Session::put(OrderService::SESSION_GOODS_SHOWN, 150.0);
        $this->assertTrue(app(OrderService::class)->createOrderFromCart($this->customer, $this->shippingData(), $cart->fresh())['success']);
        $this->assertNull(Session::get(OrderService::SESSION_GOODS_SHOWN));
    }

    // ---- 3.12: one return request at a time -----------------------------------------------------

    public function test_two_return_requests_at_once_open_one(): void
    {
        $product = $this->stockedProduct($this->shop, 100, 10);
        $order = $this->paidOrder($this->customer, [[$product, 2]]);
        $admin = $this->staffMember('admin');
        $this->actingAs($admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }
        $part = $order->sellerOrders()->sole();
        $item = $part->items()->sole();
        $returns = app(ReturnService::class);

        $this->assertNotNull($returns->request($part->fresh(), $this->customer, [$item->id => 2], 'faulty', 'x', null));
        $this->assertNull($returns->request($part, $this->customer, [$item->id => 2], 'faulty', 'x', null), 'the second, from the same page');
        $this->assertSame(1, ReturnRequest::count());
    }
}
