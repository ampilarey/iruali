<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerAdjustment;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WalletTransaction;
use App\Services\DisputeService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReturnService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Refund money (docs/AUDIT_FIX_PLAN.md batch 2): returns and disputes refund what the items were
 * actually paid after the iruali voucher and points, never more than the order was paid, once each,
 * and a cancellation after a dispute refund does not pay the same money again.
 */
class RefundMoneyTest extends TestCase
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
        Storage::fake('local');
        Setting::set(['default_commission_rate' => 10, 'return_window_days' => 7, 'late_shipment_days' => 3]);

        $this->customer = User::factory()->create(['loyalty_points' => 1000]);
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
     * Place an order through the real checkout.
     *
     * @param  array<int, array{0: User, 1: float, 2: int}>  $lines  [shop, price, quantity]
     */
    protected function order(array $lines, ?string $voucher = null, int $points = 0): Order
    {
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        foreach ($lines as [$shop, $price, $quantity]) {
            $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => $price, 'stock_quantity' => 50]);
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'price' => $price]);
        }
        if ($voucher) {
            Session::put('voucher_code', $voucher);
        }
        if ($points) {
            Session::put('points_redeemed', $points);
        }

        $result = app(OrderService::class)->createOrderFromCart($this->customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml', 'delivery_zone' => 'islands',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order']->fresh();
    }

    protected function voucher(float $percent): string
    {
        Voucher::create(['code' => 'SAVE'.(int) $percent, 'type' => 'percent', 'amount' => $percent, 'is_active' => true, 'valid_until' => now()->addMonth()]);

        return 'SAVE'.(int) $percent;
    }

    protected function paidAndDelivered(Order $order): Order
    {
        app(PaymentService::class)->confirm($order->fresh());
        $this->actingAs($this->admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }

        return $order->fresh();
    }

    protected function part(Order $order, User $shop): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $shop->id)->firstOrFail();
    }

    protected function returnAll(Order $order, User $shop, string $reason = 'changed_mind', ?int $qty = null): ReturnRequest
    {
        $part = $this->part($order, $shop);
        $item = $part->items()->firstOrFail();

        return app(ReturnService::class)->request($part, $this->customer, [$item->id => $qty ?? $item->quantity], $reason, 'Not what I wanted.', null);
    }

    // ---- 2.1: values after the voucher and points -----------------------------------------------

    public function test_a_voucher_is_shared_over_the_items_and_a_return_refunds_what_was_paid(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 500, 1], [$this->shopB, 500, 1]], $this->voucher(20)));
        $this->assertEquals(200, $order->voucher_discount);
        $this->assertEquals([400.0, 400.0], $order->items->map->paidTotal()->all());

        $return = $this->returnAll($order, $this->shopA);
        $this->assertEquals(400, $return->items_value, 'what was paid for the item, not its 500 price');
        $this->assertEquals(400, app(ReturnService::class)->suggestedRefund($return));

        // More than was paid for it can't be approved
        $this->assertFalse(app(ReturnService::class)->approve($return, 500, false, null, $this->admin));
        $this->assertTrue(app(ReturnService::class)->approve($return->fresh(), 400, false, null, $this->admin));

        // The shop gives back what it earned on the item (500 less 10%): the voucher was iruali's
        $this->assertEquals(-450, SellerAdjustment::sole()->amount);
    }

    public function test_redeemed_points_never_come_back_as_cash(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 1000, 1]], null, 200));
        $this->assertEquals(800, $order->items->first()->paidTotal());

        $return = $this->returnAll($order, $this->shopA, 'faulty');
        $this->assertEquals(800, $return->items_value);
        $this->assertLessThanOrEqual((float) $order->total_amount, app(ReturnService::class)->suggestedRefund($return));
        $this->assertLessThanOrEqual(800 + (float) $order->shipping_amount, app(ReturnService::class)->suggestedRefund($return));
    }

    public function test_a_voucher_and_points_together_and_partial_returns_add_up_to_what_was_paid(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 3], [$this->shopB, 300, 1]], $this->voucher(20), 60));
        // 600 of goods, voucher 120 (60 on each line), then 60 points over 480 (30 each): 210 and 210
        $this->assertEquals([210.0, 210.0], $order->items->map->paidTotal()->all());
        $this->assertEquals(round((float) $order->items->sum->paidTotal() + (float) $order->shipping_amount, 2), (float) $order->total_amount);

        $returns = app(ReturnService::class);
        $first = $this->returnAll($order, $this->shopA, 'changed_mind', 1);
        $this->assertEquals(70, $first->items_value);
        $returns->approve($first, 70, false, null, $this->admin);
        $returns->markRefunded($first->fresh(), 'BML-1'); // one open return per part at a time
        $second = $this->returnAll($order->fresh(), $this->shopA, 'changed_mind', 2);
        $this->assertEquals(140, $second->items_value);
        $returns->approve($second, 140, false, null, $this->admin);
        $returns->markRefunded($second->fresh(), 'BML-2');
        $third = $this->returnAll($order->fresh(), $this->shopB);
        $returns->approve($third, (float) $third->items_value, false, null, $this->admin);

        $refunded = (float) ReturnRequest::whereIn('status', ['approved', 'refunded'])->sum('refund_amount');
        $this->assertEquals(420, $refunded);
        $this->assertLessThanOrEqual((float) $order->total_amount, $refunded);
    }

    // ---- 2.2: disputes and returns on the same items --------------------------------------------

    public function test_a_return_waits_while_a_dispute_is_open_and_a_dispute_pays_only_what_returns_left(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 2], [$this->shopB, 100, 1]]));
        $part = $this->part($order, $this->shopA);
        $disputes = app(DisputeService::class);

        $dispute = $disputes->open($part, $this->customer, 'item_not_as_described', 200, 'Both arrived broken.');
        $this->assertNotNull($dispute);
        $this->assertFalse(app(ReturnService::class)->canRequest($part->fresh(), $this->customer), 'the dispute decides first');

        // A return decided anyway (an older request) takes 150 of the 200: the dispute pays only 50
        $return = ReturnRequest::forceCreate(['order_id' => $order->id, 'seller_order_id' => $part->id, 'user_id' => $this->customer->id, 'status' => 'approved', 'reason' => 'faulty', 'items_value' => 200, 'refund_amount' => 150, 'resolved_at' => now()]);
        $this->assertTrue($disputes->resolve($dispute, $this->admin, 'full', null, null));
        $this->assertEquals(50, $dispute->fresh()->amount_resolved);
        $this->assertEquals(50, $order->fresh()->refund_amount);

        // Nothing left: a new dispute on the part refunds nothing
        $this->assertSame(0.0, $disputes->maxClaim($part->fresh()));
        $this->assertNotNull($return);
    }

    public function test_after_a_dispute_refund_a_return_can_only_take_what_is_left(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 2], [$this->shopB, 100, 1]]));
        $part = $this->part($order, $this->shopA);
        $disputes = app(DisputeService::class);
        $dispute = $disputes->open($part, $this->customer, 'item_not_as_described', 150, 'One arrived broken.');
        $disputes->resolve($dispute, $this->admin, 'full', null, null);

        $return = $this->returnAll($order->fresh(), $this->shopA, 'faulty');
        $this->assertFalse(app(ReturnService::class)->approve($return, 100, false, null, $this->admin), 'only 50 of the 200 is left');
        $this->assertTrue(app(ReturnService::class)->approve($return->fresh(), 50, false, null, $this->admin));
    }

    // ---- 2.3: cancelling after a dispute refund ------------------------------------------------

    public function test_cancelling_after_a_dispute_refund_refunds_the_payment_once_and_gives_the_shop_its_deduction_back(): void
    {
        $order = $this->order([[$this->shopA, 100, 2]]);
        app(PaymentService::class)->confirm($order);
        $order->forceFill(['paid_at' => now()->subDays(10)])->save();
        $part = $this->part($order, $this->shopA);
        $disputes = app(DisputeService::class);

        $dispute = $disputes->open($part, $this->customer, 'non_delivery', 120, 'Nothing arrived.');
        $this->assertTrue($disputes->resolve($dispute, $this->admin, 'full', null, null));
        $this->assertEquals(120, $order->fresh()->refund_amount);

        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));

        $order->refresh();
        $this->assertSame('due', $order->refund_status);
        $this->assertEquals((float) $order->total_amount, (float) $order->refund_amount, 'the payment back once, not 120 more');
        $this->assertEquals(0, (float) SellerAdjustment::where('seller_id', $this->shopA->id)->sum('amount'), 'the cancelled part earns nothing and owes nothing');

        // Cancelling can't run twice
        $this->assertFalse(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));
        $this->assertSame(2, SellerAdjustment::count());
    }

    // ---- 2.4 and 2.5: once only ----------------------------------------------------------------

    public function test_refund_to_wallet_credits_once_even_from_a_stale_page(): void
    {
        $order = $this->order([[$this->shopA, 100, 1]]);
        app(PaymentService::class)->confirm($order);
        app(PaymentService::class)->flagRefund($order->fresh(), 60, 'Test');
        // Two admins (or a double click) with the order loaded before either recorded it
        [$first, $second] = [$order->fresh(), $order->fresh()];
        $wallets = app(WalletService::class);

        $this->assertTrue($wallets->refundOrderToWallet($first));
        $this->assertFalse($wallets->refundOrderToWallet($second), 'the second copy still says "due"');
        $this->assertEquals(60, (float) WalletTransaction::where('user_id', $this->customer->id)->where('type', 'refund')->sum('amount'));
        $this->assertSame('refunded', $order->fresh()->refund_status);
    }

    public function test_return_refund_to_wallet_credits_once(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 1]]));
        $return = $this->returnAll($order, $this->shopA, 'faulty');
        app(ReturnService::class)->approve($return, 100, false, null, $this->admin);
        [$first, $second] = [$return->fresh(), $return->fresh()];

        $this->assertTrue(app(WalletService::class)->refundReturnToWallet($first));
        $this->assertFalse(app(WalletService::class)->refundReturnToWallet($second), 'the second copy still says "approved"');
        $this->assertEquals(100, (float) WalletTransaction::where('user_id', $this->customer->id)->where('type', 'refund')->sum('amount'));
    }

    public function test_a_dispute_is_decided_once(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 2]]));
        $disputes = app(DisputeService::class);
        $dispute = $disputes->open($this->part($order, $this->shopA), $this->customer, 'item_not_as_described', 100, 'Broken.');
        $stale = Dispute::find($dispute->id);

        $this->assertTrue($disputes->resolve($dispute, $this->admin, 'full', null, null));
        $this->assertFalse($disputes->resolve($stale, $this->admin, 'full', null, null), 'a second decision from a stale page');
        $this->assertEquals(100, $order->fresh()->refund_amount);
        $this->assertSame(1, SellerAdjustment::count());
    }

    // ---- 2.6: a changed refund needs approving again --------------------------------------------

    public function test_a_new_refund_amount_clears_the_earlier_approval(): void
    {
        $order = $this->order([[$this->shopA, 100, 1]]);
        app(PaymentService::class)->flagRefund($order, 50, 'First');
        $order->fresh()->forceFill(['refund_approved_at' => now(), 'refund_approved_by' => $this->admin->id])->save();

        app(PaymentService::class)->flagRefund($order->fresh(), 500, 'Second');

        $order->refresh();
        $this->assertEquals(550, $order->refund_amount);
        $this->assertNull($order->refund_approved_at);
        $this->assertNull($order->refund_approved_by);
    }

    // ---- Audit 2: several refunds on one part share it ------------------------------------------

    public function test_two_disputes_on_one_part_never_charge_the_shop_more_than_it_earned(): void
    {
        // Goods 100 (shop earns 90 at 10%), iruali's delivery fee on top
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 1]]));
        $part = $this->part($order, $this->shopA);
        $disputes = app(DisputeService::class);

        $first = $disputes->open($part, $this->customer, 'item_not_as_described', 60, 'Half of it broken.');
        $disputes->resolve($first, $this->admin, 'full', null, null);
        $second = $disputes->open($part->fresh(), $this->customer, 'item_not_as_described', $disputes->maxClaim($part->fresh()), 'The rest too, and the delivery.');
        $disputes->resolve($second, $this->admin, 'full', null, null);

        $this->assertEquals((float) $order->total_amount, (float) $order->fresh()->refund_amount, 'the customer gets everything back');
        $this->assertEquals(-90, (float) SellerAdjustment::where('seller_id', $this->shopA->id)->sum('amount'), 'the shop gives back what it earned, never iruali\'s delivery fee');
    }

    public function test_a_return_then_a_dispute_on_the_delivery_fee_takes_nothing_more_from_the_shop(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 1]]));
        $part = $this->part($order, $this->shopA);
        $return = $this->returnAll($order, $this->shopA, 'changed_mind');
        $this->assertTrue(app(ReturnService::class)->approve($return, 100, true, null, $this->admin));

        $disputes = app(DisputeService::class);
        $dispute = $disputes->open($part->fresh(), $this->customer, 'item_not_as_described', $disputes->maxClaim($part->fresh()), 'And the delivery fee.');
        $disputes->resolve($dispute, $this->admin, 'full', null, null);

        $this->assertEquals(-90, (float) SellerAdjustment::where('seller_id', $this->shopA->id)->sum('amount'));
    }

    public function test_after_a_dispute_a_change_of_mind_return_suggests_only_the_items_left(): void
    {
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 100, 1]]));
        $part = $this->part($order, $this->shopA);
        $disputes = app(DisputeService::class);
        $dispute = $disputes->open($part, $this->customer, 'item_not_as_described', 50, 'Scratched.');
        $disputes->resolve($dispute, $this->admin, 'full', null, null);

        $return = $this->returnAll($order->fresh(), $this->shopA, 'changed_mind');
        $this->assertEquals(50, app(ReturnService::class)->suggestedRefund($return), 'not iruali\'s delivery fee through the order cap');
        $this->assertTrue(app(ReturnService::class)->approve($return, 50, true, null, $this->admin));
        $this->assertEquals(-90, (float) SellerAdjustment::where('seller_id', $this->shopA->id)->sum('amount'));
    }

    public function test_the_gst_report_counts_a_dispute_refund_once_when_the_order_is_then_cancelled(): void
    {
        $order = $this->order([[$this->shopA, 100, 1]]);
        app(PaymentService::class)->confirm($order);
        $order->forceFill(['paid_at' => now()->subDays(10)])->save();
        $part = $this->part($order, $this->shopA);
        $disputes = app(DisputeService::class);
        $dispute = $disputes->open($part, $this->customer, 'non_delivery', $disputes->maxClaim($part), 'Nothing arrived.');
        $disputes->resolve($dispute, $this->admin, 'full', null, null);
        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));

        $events = app(\App\Services\GstReportService::class)->refunds(now()->subDay(), now()->addDay())->where('order.id', $order->id);
        $this->assertEqualsWithDelta(100, $events->sum('goods'), 0.01, 'the goods reversed once, not twice');
        $this->assertEqualsWithDelta((float) $order->shipping_amount, $events->sum('delivery'), 0.01);
        $this->assertEqualsWithDelta(10, $events->sum('commission'), 0.01);
    }

    // ---- 2.1 follow-up: points spent on returned items come back as points ----------------------

    public function test_points_spent_on_returned_items_come_back_as_points(): void
    {
        // 2 units at 1000, 200 points redeemed: 100 points sat on each unit
        $order = $this->paidAndDelivered($this->order([[$this->shopA, 1000, 2]], null, 200));
        $returns = app(ReturnService::class);
        $before = (int) $this->customer->fresh()->loyalty_points;

        // One unit back, refunded in full: its 100 points return
        $first = $this->returnAll($order, $this->shopA, 'changed_mind', 1);
        $this->assertEquals(900, $first->items_value);
        $this->assertTrue($returns->approve($first, 900, true, null, $this->admin));
        $this->assertSame(100, (int) $first->fresh()->points_returned);
        $this->assertSame($before + 100, (int) $this->customer->fresh()->loyalty_points);
        $this->actingAs($this->admin)->get(route('admin.returns.show', $first))->assertOk()->assertSee('data-points-returned', false);

        // Approving again gives nothing more
        $this->assertFalse($returns->approve($first->fresh(), 900, true, null, $this->admin));
        $this->assertSame($before + 100, (int) $this->customer->fresh()->loyalty_points);

        // The other unit, refunded at half its value: half its points
        $returns->markRefunded($first->fresh(), 'REF-1');
        $second = $this->returnAll($order->fresh(), $this->shopA, 'changed_mind', 1);
        $this->assertTrue($returns->approve($second, 450, false, null, $this->admin));
        $this->assertSame(50, (int) $second->fresh()->points_returned);
        $this->assertSame($before + 150, (int) $this->customer->fresh()->loyalty_points);
    }

    public function test_no_points_come_back_when_none_were_spent_and_a_cancellation_returns_only_the_rest(): void
    {
        // No points on the order: nothing to give back
        $plain = $this->paidAndDelivered($this->order([[$this->shopA, 500, 1]]));
        $return = $this->returnAll($plain, $this->shopA, 'faulty');
        $this->assertTrue(app(ReturnService::class)->approve($return, 500, true, null, $this->admin));
        $this->assertSame(0, (int) $return->fresh()->points_returned);

        // Points on the order, one of two units returned, then the order is cancelled: 200 back in all
        $order = $this->order([[$this->shopB, 1000, 2]], null, 200);
        $placed = (int) $this->customer->fresh()->loyalty_points; // the 200 already spent
        app(PaymentService::class)->confirm($order);
        $before = (int) $this->customer->fresh()->loyalty_points; // plus the points earned on the payment
        $part = $this->part($order->fresh(), $this->shopB);
        $part->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();
        $back = app(ReturnService::class)->request($part->fresh(), $this->customer, [$part->items()->firstOrFail()->id => 1], 'changed_mind', 'One too many.', null);
        $this->assertTrue(app(ReturnService::class)->approve($back, 900, true, null, $this->admin));
        $this->assertSame($before + 100, (int) $this->customer->fresh()->loyalty_points);

        // An order with a part already sent can't be cancelled (returns are the way back), so this can't
        // follow a return today; should a cancellation ever run after one, it gives back only the rest
        $this->assertFalse(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));
        $part->forceFill(['status' => 'pending', 'delivered_at' => null])->save();
        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));
        // The earned points go with the cancellation; of the 200 spent, the other 100 come back
        $this->assertSame($placed + 200, (int) $this->customer->fresh()->loyalty_points, 'the rest of the 200, not 200 again');
    }

    // ---- 2.6 follow-up: one row per refund ------------------------------------------------------

    public function test_every_refund_on_an_order_keeps_its_own_record(): void
    {
        $order = $this->order([[$this->shopA, 100, 1]]);
        $payments = app(PaymentService::class);

        // Two amounts flagged while nothing was sent yet go out together, under one reference
        $payments->flagRefund($order, 30, 'Paid twice');
        $payments->flagRefund($order->fresh(), 20, 'Dispute #1');
        $this->assertEquals(50, $order->fresh()->refund_amount);
        $this->actingAs($this->admin)->post(route('admin.orders.refunded', $order), ['refund_reference' => 'BML-REF-1'])->assertRedirect();

        // A later refund no longer writes over the first
        $payments->flagRefund($order->fresh(), 40, 'Dispute #2');
        $order->refresh();
        $this->assertSame(['due', '40.00'], [$order->refund_status, $order->refund_amount]);

        $rows = \App\Models\Refund::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame(
            [['30.00', 'Paid twice', 'refunded', 'BML-REF-1'], ['20.00', 'Dispute #1', 'refunded', 'BML-REF-1'], ['40.00', 'Dispute #2', 'due', null]],
            $rows->map(fn ($r) => [$r->amount, $r->reason, $r->status, $r->reference])->all(),
        );
        $this->assertSame($this->admin->id, (int) $rows[0]->recorded_by);
        $this->assertNotNull($rows[0]->refunded_at);

        // The order page lists them all
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('data-refund-history', false)->assertSee('ref BML-REF-1')->assertSee('Dispute #2');

        // Recording the same refund twice changes nothing more
        $this->post(route('admin.orders.refunded', $order), ['refund_reference' => 'BML-REF-2']);
        $this->post(route('admin.orders.refunded', $order), ['refund_reference' => 'BML-REF-3']);
        $this->assertSame(['BML-REF-1', 'BML-REF-1', 'BML-REF-2'], \App\Models\Refund::where('order_id', $order->id)->orderBy('id')->pluck('reference')->all());
        $this->assertEquals(90, \App\Models\Refund::where('order_id', $order->id)->where('status', 'refunded')->sum('amount'));
    }

    public function test_a_refund_sent_to_the_wallet_is_recorded_as_wallet_credit(): void
    {
        $order = $this->order([[$this->shopA, 100, 1]]);
        app(PaymentService::class)->flagRefund($order, 25, 'Paid twice');

        $this->assertTrue(app(\App\Services\WalletService::class)->refundOrderToWallet($order->fresh()));

        $row = \App\Models\Refund::where('order_id', $order->id)->sole();
        $this->assertSame(['refunded', 'wallet'], [$row->status, $row->method]);
        $this->assertStringStartsWith('WALLET-', (string) $row->reference);
    }

    // ---- 2.9: a BML payment that doesn't match --------------------------------------------------

    public function test_a_bml_payment_that_does_not_match_is_flagged_for_a_refund_once(): void
    {
        config(['services.bml.api_key' => 'test-key']);
        $order = $this->order([[$this->shopA, 100, 1]]);
        $transaction = PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'bml', 'transaction_id' => 'bml-1', 'local_id' => 'L1', 'amount' => (int) round((float) $order->total_amount * 100), 'currency' => 'MVR', 'state' => 'INITIATED']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['id' => 'bml-1', 'state' => 'CONFIRMED', 'amount' => 999, 'currency' => 'MVR', 'localId' => 'L1'])]);

        app(PaymentService::class)->syncBml($transaction);
        app(PaymentService::class)->syncBml($transaction->fresh());

        $order->refresh();
        $this->assertSame('MISMATCH', $transaction->fresh()->state);
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertSame('due', $order->refund_status);
        $this->assertEquals(9.99, $order->refund_amount, 'what BML took, flagged once');
    }

    // ---- 2.10: wallet rows are not card payments -----------------------------------------------

    public function test_wallet_payments_are_not_counted_as_card_money(): void
    {
        $order = $this->order([[$this->shopA, 100, 1]]);
        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'wallet', 'transaction_id' => 'wallet-1', 'local_id' => 'W1', 'amount' => 5000, 'currency' => 'MVR', 'state' => 'CONFIRMED', 'confirmed_at' => now()]);
        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'bml', 'transaction_id' => 'bml-2', 'local_id' => 'L2', 'amount' => 7000, 'currency' => 'MVR', 'state' => 'CONFIRMED', 'confirmed_at' => now()]);
        Setting::set(['card_fee_percent' => 10]);

        $report = app(\App\Services\FinanceService::class)->profitAndLoss(now()->format('Y-m'));
        $this->assertEquals(70, $report['card_total'], 'only the card payment');
        $this->assertEquals(7, $report['card_fees']);
    }
}
