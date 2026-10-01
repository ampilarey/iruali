<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Dispute;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerAdjustment;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DisputeOpened;
use App\Notifications\DisputeResolved;
use App\Services\DisputeService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Disputes: when a customer may open one, what iruali's decision does to the money, and who may do what.
 */
class DisputesTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $shop;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set(['default_commission_rate' => 10, 'return_window_days' => 7, 'contact_email' => 'hello@iruali.mv', 'late_shipment_days' => 3]);

        $this->customer = User::factory()->create();
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $this->shop->forceFill(['commission_rate' => 20])->save();
        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    /**
     * A paid single-shop order: two MVR 100 items from the shop, MVR 25 delivery.
     */
    protected function paidOrder(): Order
    {
        $product = Product::factory()->create(['seller_id' => $this->shop->id, 'price' => 100, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 100]);

        $order = app(OrderService::class)->createOrderFromCart($this->customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml', 'delivery_zone' => 'islands',
        ])['order'];
        app(PaymentService::class)->confirm($order);

        return $order->fresh();
    }

    protected function part(Order $order): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $this->shop->id)->firstOrFail();
    }

    protected function deliver(Order $order): void
    {
        $this->actingAs($this->admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }
    }

    protected function openDispute(Order $order, string $type = 'non_delivery', float $amount = 200, string $details = 'Nothing arrived and the shop does not answer.')
    {
        return $this->actingAs($this->customer)->post(route('orders.disputes.store', [$order, $this->part($order)]), ['type' => $type, 'amount_claimed' => $amount, 'details' => $details]);
    }

    public function test_non_delivery_can_be_raised_only_when_paid_and_late(): void
    {
        $order = $this->paidOrder();
        $service = app(DisputeService::class);
        $this->assertSame(8, $service->nonDeliveryAfterDays());

        // Paid today: too early
        $this->assertSame([], $service->availableTypes($this->part($order), $this->customer));
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertDontSee('open a dispute');
        $this->openDispute($order)->assertRedirect(route('orders.show', $order));
        $this->assertSame(0, Dispute::count());

        // Unpaid and late: still no
        $order->forceFill(['paid_at' => now()->subDays(9), 'payment_status' => 'unpaid'])->save();
        $this->assertSame([], $service->availableTypes($this->part($order->fresh()), $this->customer));

        // Paid and 9 days without delivery: yes
        $order->forceFill(['payment_status' => 'paid'])->save();
        $this->assertSame(['non_delivery', 'other'], $service->availableTypes($this->part($order->fresh()), $this->customer));
        $max = 200 + (float) $order->shipping_amount; // items plus delivery on a single-shop order
        $this->assertEquals($max, $service->maxClaim($this->part($order->fresh())));
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('open a dispute')->assertSee('value="'.number_format($max, 2, '.', '').'"', false);

        // A claim above the cap is refused
        $this->openDispute($order, 'non_delivery', 999)->assertSessionHasErrors('amount_claimed');
        $this->openDispute($order, 'non_delivery', 225)->assertRedirect();
        $dispute = Dispute::sole();
        $this->assertSame(['open', 'non_delivery', $this->customer->id, $this->shop->id], [$dispute->status, $dispute->type, $dispute->customer_id, $dispute->seller_id]);
        $this->assertEquals(225, $dispute->amount_claimed);
        $this->assertNotNull($dispute->conversation_id);
        $this->assertStringContainsString('Nothing arrived', Message::sole()->body);

        Notification::assertSentTo($this->shop, DisputeOpened::class);
        Notification::assertSentTo(new AnonymousNotifiable, DisputeOpened::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'hello@iruali.mv');

        // One open dispute per part
        $this->assertSame([], $service->availableTypes($this->part($order->fresh()), $this->customer));
        $this->openDispute($order);
        $this->assertSame(1, Dispute::count());
    }

    public function test_not_as_described_within_seven_days_and_rejected_returns(): void
    {
        $order = $this->paidOrder();
        $this->deliver($order);
        $service = app(DisputeService::class);
        $part = $this->part($order);

        $this->assertSame(['item_not_as_described', 'other'], $service->availableTypes($part, $this->customer));

        $part->update(['delivered_at' => now()->subDays(8)]);
        $this->assertSame([], $service->availableTypes($part->fresh(), $this->customer));
        $this->openDispute($order, 'item_not_as_described');
        $this->assertSame(0, Dispute::count());

        // A rejected return reopens the door, linked to that return
        $return = ReturnRequest::create(['order_id' => $order->id, 'seller_order_id' => $part->id, 'user_id' => $this->customer->id, 'status' => 'rejected', 'reason' => 'faulty', 'items_value' => 100]);
        $this->assertSame(['return_rejected', 'other'], $service->availableTypes($part->fresh(), $this->customer));
        $this->openDispute($order, 'item_not_as_described')->assertSessionHasErrors('type');
        $this->openDispute($order, 'return_rejected', 100, 'The item really was faulty, see my photos.')->assertSessionMissing('errors');
        $this->assertSame($return->id, Dispute::sole()->return_request_id);
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Return was not accepted')->assertSee('iruali is reviewing it');
    }

    public function test_full_refund_flags_the_money_and_debits_the_shop(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['paid_at' => now()->subDays(10)])->save();
        $this->openDispute($order, 'non_delivery', 225);
        $dispute = Dispute::sole();

        $this->actingAs($this->admin)->get(route('admin.disputes'))->assertOk()->assertSee('Island Crafts')->assertSee('Not delivered');
        $this->get(route('admin.disputes.show', $dispute))->assertOk()->assertSee('Nothing arrived')->assertSee('Full refund');

        $this->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'full', 'note' => 'The shop could not show proof of delivery.'])->assertSessionHas('success');

        $dispute->refresh();
        $this->assertSame('resolved_refund', $dispute->status);
        $this->assertEquals(225, $dispute->amount_resolved);
        $this->assertSame($this->admin->id, $dispute->admin_id);

        $order->refresh();
        $this->assertSame('due', $order->refund_status);
        $this->assertEquals(225, $order->refund_amount);
        $this->assertStringContainsString('Dispute #'.$dispute->id, $order->refund_reason);

        // The shop gives back its share of the items (200 less 20% commission); delivery is iruali's
        $adjustment = SellerAdjustment::sole();
        $this->assertEquals(-160, $adjustment->amount);
        $this->assertSame($this->shop->id, $adjustment->seller_id);
        $this->assertEquals(-160, app(PayoutService::class)->balances($this->shop)['available']);

        Notification::assertSentTo($this->customer, DisputeResolved::class, fn ($n) => $n->dispute->status === 'resolved_refund');
        Notification::assertSentTo($this->shop, DisputeResolved::class);
        $this->assertStringContainsString('225', (new DisputeResolved($dispute))->toSms($this->customer));

        // The decision is in the thread and on the customer's page; it can't be decided twice
        $this->assertStringContainsString('full refund', Message::latest('id')->first()->body);
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Decided in your favour')->assertSee('could not show proof');
        $this->actingAs($this->admin)->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'reject', 'note' => 'x'])->assertSessionHas('error');
        $this->assertSame(1, SellerAdjustment::count());
    }

    public function test_partial_refund_and_rejection_maths(): void
    {
        $order = $this->paidOrder();
        $this->deliver($order);
        $this->openDispute($order, 'item_not_as_described', 200, 'The colour is completely different from the photos.');
        $dispute = Dispute::sole();

        $this->actingAs($this->admin);
        $this->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'partial'])->assertSessionHasErrors('amount');
        $this->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'partial', 'amount' => 200])->assertSessionHas('error');
        $this->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'partial', 'amount' => 50, 'note' => 'Half the difference in value.'])->assertSessionHas('success');

        $dispute->refresh();
        $this->assertSame('resolved_partial', $dispute->status);
        $this->assertEquals(50, $dispute->amount_resolved);
        $this->assertEquals(50, $order->fresh()->refund_amount);
        $this->assertEquals(-40, SellerAdjustment::sole()->amount); // 50 less 20% commission

        // A second dispute on the same part can claim only what is left, and a rejection moves no money
        $part = $this->part($order);
        $this->assertEquals(200 + (float) $order->shipping_amount - 50, app(DisputeService::class)->maxClaim($part));
        $this->openDispute($order, 'other', 150, 'I am still not happy with this item at all.');
        $second = Dispute::latest('id')->first();
        $this->actingAs($this->admin)->post(route('admin.disputes.resolve', $second), ['outcome' => 'reject'])->assertSessionHasErrors('note');
        $this->post(route('admin.disputes.resolve', $second), ['outcome' => 'reject', 'note' => 'Already compensated.'])->assertSessionHas('success');
        $this->assertSame('resolved_rejected', $second->fresh()->status);
        $this->assertEquals(0, $second->fresh()->amount_resolved);
        $this->assertEquals(50, $order->fresh()->refund_amount);
        $this->assertSame(1, SellerAdjustment::count());
        Notification::assertSentTo($this->customer, DisputeResolved::class, fn ($n) => $n->dispute->status === 'resolved_rejected');
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('not upheld')->assertSee('Already compensated.');
    }

    public function test_admin_can_ask_a_side_for_more_and_the_shop_replies_in_the_thread(): void
    {
        $order = $this->paidOrder();
        $this->deliver($order);
        $this->openDispute($order, 'item_not_as_described', 100, 'Looks nothing like the listing photos.');
        $dispute = Dispute::sole();

        $this->actingAs($this->admin)->post(route('admin.disputes.request-info', $dispute), ['from' => 'seller', 'note' => 'Please send photos of what you shipped.'])->assertSessionHas('success');
        $this->assertSame('awaiting_seller', $dispute->fresh()->status);

        $this->actingAs($this->shop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Disputes')->assertSee('Please send photos')->assertSee('iruali support');
        $this->post(route('seller.orders.messages.store', [$order, $this->part($order)]), ['body' => 'Attached: it matches the listing.'])->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.disputes.show', $dispute))->assertSee('it matches the listing');
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('asked the shop for more information');

        // Messages stay open thanks to the dispute even on an old order
        $order->forceFill(['created_at' => now()->subDays(120)])->save();
        $this->assertTrue(app(\App\Services\MessagingService::class)->isOpenFor($order->fresh()));
    }

    public function test_authorisation(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['paid_at' => now()->subDays(10)])->save();
        $part = $this->part($order);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('orders.disputes.store', [$order, $part]), ['type' => 'non_delivery', 'amount_claimed' => 10, 'details' => 'Not my order but trying anyway.'])->assertForbidden();
        $this->actingAs($this->shop)->post(route('orders.disputes.store', [$order, $part]), ['type' => 'non_delivery', 'amount_claimed' => 10, 'details' => 'Not my order but trying anyway.'])->assertForbidden();
        $this->assertSame(0, Dispute::count());

        $this->openDispute($order);
        $dispute = Dispute::sole();
        $this->actingAs($this->customer)->get(route('admin.disputes'))->assertForbidden();
        $this->actingAs($this->shop)->get(route('admin.disputes.show', $dispute))->assertForbidden();
        $this->actingAs($this->shop)->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'full'])->assertForbidden();
        $this->actingAs($stranger)->post(route('admin.disputes.request-info', $dispute), ['from' => 'seller', 'note' => 'x'])->assertForbidden();
        $this->assertSame('open', $dispute->fresh()->status);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('1 open');
    }
}
