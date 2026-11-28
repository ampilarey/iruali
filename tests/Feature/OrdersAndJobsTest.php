<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\OrderPartCancelled;
use App\Notifications\OrderStatusChanged;
use App\Notifications\RefundRecorded;
use App\Notifications\ReturnUpdated;
use App\Notifications\ShopOrderCancelled;
use App\Services\FulfilmentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Audit 2, batch 3: the life of an order (one shop's part cancelled, shops told of cancellations,
 * status messages), and the jobs that send held texts and clean up unpaid orders.
 */
class OrdersAndJobsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $shopA;

    protected User $shopB;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set(['default_commission_rate' => 10]);
        $this->customer = User::factory()->create(['loyalty_points' => 500]);
        $this->shopA = $this->seller('Island Crafts');
        $this->shopB = $this->seller('Reefline Marine');
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    protected function seller(string $name): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    /**
     * A card order from both shops (MVR 100 from A, MVR 200 from B), optionally with points.
     */
    protected function order(int $points = 0, bool $pay = true): Order
    {
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        foreach ([[$this->shopA, 100], [$this->shopB, 200]] as [$shop, $price]) {
            $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => $price, 'stock_quantity' => 10]);
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $price]);
        }
        if ($points) {
            Session::put('points_redeemed', $points);
        }
        $result = app(OrderService::class)->createOrderFromCart($this->customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml', 'delivery_zone' => 'islands',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');
        if ($pay) {
            app(PaymentService::class)->confirm($result['order']->fresh());
        }

        return $result['order']->fresh();
    }

    protected function part(Order $order, User $shop): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $shop->id)->firstOrFail();
    }

    public function test_an_admin_cancels_the_part_of_a_shop_that_cannot_supply(): void
    {
        $order = $this->order(30);
        $productB = $this->part($order, $this->shopB)->items()->sole()->product;
        $stockB = (int) $productB->fresh()->stock_quantity;
        $pointsBefore = (int) $this->customer->fresh()->loyalty_points;

        // Shop A sends its part: the order can't be cancelled as a whole any more
        $this->assertTrue(app(FulfilmentService::class)->advance($this->part($order, $this->shopA), 'processing'));
        $this->assertTrue(app(FulfilmentService::class)->advance($this->part($order, $this->shopA)->fresh(), 'shipped'));
        $this->assertFalse(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));
        $partB = $this->part($order, $this->shopB);
        $paidForB = $partB->paidSubtotal();

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('data-cancel-part', false);
        $this->post(route('admin.orders.parts.cancel', [$order, $partB]))->assertSessionHas('success');

        $partB->refresh();
        $this->assertSame('cancelled', $partB->status);
        $this->assertNotNull($partB->cancelled_alone_at);
        $this->assertEqualsWithDelta($paidForB, (float) $partB->cancelled_refund, 0.01);
        $this->assertSame($stockB + 1, (int) $productB->fresh()->stock_quantity, 'back on sale');
        $this->assertSame('due', $order->fresh()->refund_status);
        $this->assertEqualsWithDelta($paidForB, (float) $order->fresh()->refund_amount, 0.01);
        $this->assertSame(20, (int) $partB->points_returned, '20 of the 30 points sat on B\'s MVR 200 of MVR 300');
        // ...and the earned points go back in proportion to what was refunded
        $this->assertSame((int) floor((int) $order->loyalty_points_earned * $paidForB / (float) $order->total_amount), (int) $order->fresh()->loyalty_points_taken_back);
        $this->assertSame($pointsBefore + 20 - (int) $order->fresh()->loyalty_points_taken_back, (int) $this->customer->fresh()->loyalty_points);
        $this->assertSame('shipped', $order->fresh()->status, 'the order follows the shop still in it');
        Notification::assertSentTo($this->shopB, ShopOrderCancelled::class, fn ($n) => $n->partOnly);
        Notification::assertNotSentTo($this->shopA, ShopOrderCancelled::class);
        Notification::assertSentTo($this->customer, OrderPartCancelled::class);

        // Not twice, nor the last shop left
        $this->post(route('admin.orders.parts.cancel', [$order, $partB]))->assertSessionHas('error');
        $this->post(route('admin.orders.parts.cancel', [$order, $this->part($order, $this->shopA)]))->assertSessionHas('error');

        // GST: B's sale is reversed, iruali's delivery fee is not (the rest of the order was delivered)
        $event = app(\App\Services\GstReportService::class)->refunds(now()->subDay(), now()->addDay())->firstWhere('part.id', $partB->id);
        $this->assertNotNull($event);
        $this->assertEquals(0, $event['delivery']);

        // Support may not: it is a refund
        $support = User::factory()->create();
        $support->roles()->attach(Role::firstOrCreate(['name' => 'support'], ['display_name' => 'Support'])->id);
        $this->actingAs($support)->post(route('admin.orders.parts.cancel', [$order, $partB]))->assertForbidden();
    }

    public function test_cancelling_the_rest_later_does_not_restock_refund_or_return_the_cancelled_part_again(): void
    {
        $order = $this->order(30);
        $partB = $this->part($order, $this->shopB);
        $productB = $partB->items()->sole()->product;
        $this->assertNull(app(FulfilmentService::class)->cancelPart($partB, $this->admin));
        $stockB = (int) $productB->fresh()->stock_quantity;
        $refundB = (float) $partB->fresh()->cancelled_refund;
        $points = (int) $this->customer->fresh()->loyalty_points;
        $earnedLeft = (int) $order->fresh()->loyalty_points_earned - (int) $order->fresh()->loyalty_points_taken_back;

        // Shop A has not sent anything: the rest of the order can still be cancelled
        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));
        $this->assertSame($stockB, (int) $productB->fresh()->stock_quantity, 'B\'s unit came back once');
        $this->assertEqualsWithDelta((float) $order->total_amount, (float) $order->fresh()->refund_amount, 0.01, 'the whole payment once, not B\'s part twice');
        $this->assertGreaterThan(0, $refundB);
        $this->assertSame($points + 10 - $earnedLeft, (int) $this->customer->fresh()->loyalty_points, 'only A\'s 10 points come back now (and the earned points not yet taken back go)');
    }

    public function test_shops_are_told_when_an_order_they_were_told_about_is_cancelled(): void
    {
        $paid = $this->order();
        $this->actingAs($this->customer)->post(route('orders.cancel', $paid))->assertRedirect();
        $this->assertSame('cancelled', $paid->fresh()->status);
        Notification::assertSentTo($this->shopA, ShopOrderCancelled::class, fn ($n) => ! $n->partOnly && $n->order->is($paid));
        Notification::assertSentTo($this->shopB, ShopOrderCancelled::class);

        // A card order never paid was never shown to them: nothing to say
        Notification::fake();
        $unpaid = $this->order(0, false);
        app(OrderService::class)->updateOrderStatus($unpaid, 'cancelled');
        Notification::assertNotSentTo($this->shopA, ShopOrderCancelled::class);
    }

    public function test_a_status_message_says_the_step_it_was_queued_for(): void
    {
        $order = $this->order();
        $order->forceFill(['status' => 'processing'])->save();
        $message = new OrderStatusChanged($order->fresh());

        // The shop moves on before the queue sends it
        $order->forceFill(['status' => 'shipped'])->save();
        $sent = unserialize(serialize($message));
        $this->assertStringContainsString('is being prepared', $sent->toMail($this->customer)->subject);
    }

    public function test_return_decisions_and_refunds_reach_a_phone_only_customer_by_sms(): void
    {
        $phoneOnly = User::factory()->create(['email' => null, 'phone' => '7771234', 'phone_verified_at' => now()]);
        $return = new ReturnRequest(['status' => 'approved']);
        $this->assertSame(['sms'], (new ReturnUpdated($return))->via($phoneOnly));
        $this->assertSame(['sms'], (new RefundRecorded(new Order))->via($phoneOnly));

        $order = $this->order();
        $order->forceFill(['refund_amount' => 300, 'refund_reference' => 'FT1'])->save();
        $this->assertStringContainsString('MVR 300.00', (string) (new RefundRecorded($order->fresh()))->toSms($phoneOnly));
    }

    public function test_a_held_text_is_sent_once_even_when_two_releases_overlap(): void
    {
        $sms = app(SmsManager::class);
        $driver = new class implements SmsDriver
        {
            public int $sent = 0;

            public ?\Closure $during = null;

            public function send(string $to, string $message, string $sender): SmsResult
            {
                $this->sent++;
                if ($during = $this->during) {
                    $this->during = null;
                    $during(); // a second run starting while this one is still sending
                }

                return SmsResult::sent('ok');
            }
        };
        $sms->using($driver);
        SmsMessage::create(['to' => '+9607771234', 'message' => 'First', 'status' => 'held', 'send_after' => now()->subMinute(), 'created_at' => now()]);
        SmsMessage::create(['to' => '+9607771235', 'message' => 'Second', 'status' => 'held', 'send_after' => now()->subMinute(), 'created_at' => now()]);

        $driver->during = fn () => $sms->releaseHeld();
        $sms->releaseHeld();

        $this->assertSame(2, $driver->sent, 'each message once');
        $this->assertSame(0, SmsMessage::where('status', 'held')->count());
        $sms->using(null);
    }

    public function test_a_stock_delivery_sent_twice_is_recorded_once(): void
    {
        $product = Product::factory()->create(['seller_id' => $this->shopA->id, 'price' => 100, 'stock_quantity' => 0]);
        $product->forceFill(['preorder_enabled' => true, 'preorder_ship_date' => now()->addWeek()->toDateString(), 'preorder_limit' => 5])->save();
        $form = ['received' => [0 => 5], 'submission' => (string) \Illuminate\Support\Str::uuid()];

        $this->actingAs($this->shopA)->post(route('seller.preorders.arrived', $product->id), $form)->assertSessionHas('success');
        $this->post(route('seller.preorders.arrived', $product->id), $form)->assertSessionHas('success', 'This delivery is already recorded.');
        $this->assertSame(5, (int) $product->fresh()->stock_quantity);
    }

    public function test_a_shop_cannot_move_on_a_part_cancelled_while_its_page_was_open(): void
    {
        $order = $this->order();
        $stale = $this->part($order, $this->shopA)->load('order'); // the page loaded it, order included
        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));

        $this->assertFalse(app(FulfilmentService::class)->advance($stale, 'processing'));
        $this->assertSame('cancelled', $stale->fresh()->status);
    }

    public function test_an_unpaid_card_order_bml_never_answers_for_is_released_after_three_days(): void
    {
        config(['services.bml.api_key' => 'test-key', 'services.bml.environment' => 'sandbox', 'services.bml.base_uri' => 'https://bml.test/public']);
        Http::fake(['bml.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('BML does not answer for this transaction')]);
        $order = $this->order(0, false);
        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'bml', 'local_id' => 'L1', 'transaction_id' => 'gone', 'amount' => 30000, 'currency' => 'MVR', 'state' => 'INITIATED']);

        $this->travel(2)->days();
        $this->artisan('orders:cancel-unpaid-card')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->status, 'not sure it is unpaid yet');

        $this->travel(2)->days();
        $this->artisan('orders:cancel-unpaid-card')->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_a_dispute_open_on_a_cancelled_part_is_closed_and_cannot_refund_it_again(): void
    {
        $order = $this->order(30);
        $part = $this->part($order, $this->shopB);
        $dispute = \App\Models\Dispute::create(['order_id' => $order->id, 'seller_order_id' => $part->id, 'customer_id' => $this->customer->id, 'seller_id' => $this->shopB->id,
            'type' => 'non_delivery', 'status' => 'open', 'details' => 'Nothing came.', 'amount_claimed' => $part->paidSubtotal(), 'opened_at' => now()]);

        $this->assertNull(app(FulfilmentService::class)->cancelPart($part, $this->admin));
        $due = (float) $order->fresh()->refund_amount;
        $points = (int) $this->customer->fresh()->loyalty_points;

        $this->assertSame('resolved_refund', $dispute->fresh()->status, 'closed by the cancellation');
        $this->assertEquals(0, (float) $dispute->fresh()->amount_resolved);
        $this->assertEquals(0, app(\App\Services\DisputeService::class)->maxClaim($part->fresh()));

        // Deciding it again (a page left open) does nothing
        $this->assertFalse(app(\App\Services\DisputeService::class)->resolve($dispute->fresh(), $this->admin, 'full', null, null));
        $dispute->forceFill(['status' => 'open'])->save();
        $this->assertFalse(app(\App\Services\DisputeService::class)->resolve($dispute->fresh(), $this->admin, 'full', null, null));
        $this->assertEquals($due, (float) $order->fresh()->refund_amount);
        $this->assertSame($points, (int) $this->customer->fresh()->loyalty_points);
    }

    public function test_pre_order_lines_of_a_cancelled_part_stop_waiting_for_stock(): void
    {
        $order = $this->order();
        $part = $this->part($order, $this->shopB);
        $item = $part->items()->sole();
        $item->forceFill(['is_preorder' => true, 'preorder_allocated_at' => null, 'preorder_allocated_quantity' => 0])->save();
        $this->assertSame(1, app(\App\Services\PreorderService::class)->waitingUnits((int) $item->product_id));

        $stock = (int) $item->product->fresh()->stock_quantity;

        $this->assertNull(app(FulfilmentService::class)->cancelPart($part, $this->admin));
        $this->assertSame(0, app(\App\Services\PreorderService::class)->waitingUnits((int) $item->product_id));
        $this->assertSame($stock, (int) $item->product->fresh()->stock_quantity, 'no unit on the shelf was handed to the cancelled line');
    }

    public function test_a_status_message_queued_before_it_kept_its_status_still_sends(): void
    {
        $order = $this->order();
        $order->forceFill(['status' => 'shipped'])->save();
        $old = new OrderStatusChanged($order->fresh());
        $old->status = null; // as unserialized from a job queued by the older code
        $this->assertStringContainsString('on its way', $old->toMail($this->customer)->subject);
    }
}
