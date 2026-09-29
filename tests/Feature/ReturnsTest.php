<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerAdjustment;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReturnRequested;
use App\Notifications\ReturnUpdated;
use App\Services\OrderService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $shopA;

    protected User $shopB;

    protected User $admin;

    protected Product $productA;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        Setting::set(['default_commission_rate' => 10, 'return_window_days' => 7, 'contact_email' => 'hello@iruali.mv']);

        $this->customer = User::factory()->create();
        $this->shopA = $this->seller('Island Crafts', 15);
        $this->shopB = $this->seller('Reefline Marine', null);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    protected function seller(string $name, ?float $rate): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->forceFill(['commission_rate' => $rate])->save();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    /**
     * Two of shop A's MVR 100 items and one of shop B's, delivered and paid.
     */
    protected function deliveredOrder(): Order
    {
        $this->productA = Product::factory()->create(['seller_id' => $this->shopA->id, 'price' => 100, 'stock_quantity' => 10]);
        $b = Product::factory()->create(['seller_id' => $this->shopB->id, 'price' => 100, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $this->productA->id, 'quantity' => 2, 'price' => 100]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $b->id, 'quantity' => 1, 'price' => 100]);

        $order = app(OrderService::class)->createOrderFromCart($this->customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'payment_method' => 'cod',
        ])['order'];

        $this->actingAs($this->admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }
        $this->post(route('admin.orders.payment', $order), ['action' => 'confirm']);

        return $order->fresh();
    }

    protected function partA(Order $order): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $this->shopA->id)->firstOrFail();
    }

    protected function requestReturn(Order $order, int $qty = 1, string $reason = 'faulty', array $extra = [])
    {
        $part = $this->partA($order);
        $item = $part->items()->firstOrFail();

        return $this->actingAs($this->customer)->post(route('orders.returns.store', [$order, $part]), array_merge([
            'quantities' => [$item->id => $qty], 'reason' => $reason, 'details' => 'Stopped working after a day.',
        ], $extra));
    }

    public function test_customer_requests_a_return_and_the_shop_and_admin_are_told(): void
    {
        $order = $this->deliveredOrder();

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertSee('Request a return');

        $this->requestReturn($order, 1, 'damaged', ['photo' => UploadedFile::fake()->image('broken.jpg')])->assertRedirect(route('orders.show', $order));

        $return = ReturnRequest::sole();
        $this->assertSame('requested', $return->status);
        $this->assertEquals(100, $return->items_value);
        $this->assertSame(1, $return->items()->sole()->quantity);
        Storage::disk('local')->assertExists($return->photo_path);

        Notification::assertSentTo($this->shopA, ReturnRequested::class);
        Notification::assertSentOnDemand(ReturnRequested::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'hello@iruali.mv');

        // Only one open request per shop part (shop B's items can still be returned)
        $page = $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Return requested')->getContent();
        $this->assertSame(1, substr_count($page, 'Send return request'));
        $this->requestReturn($order)->assertSessionMissing('errors');
        $this->assertSame(1, ReturnRequest::count());
    }

    public function test_damaged_items_need_a_photo_and_quantities_are_capped(): void
    {
        $order = $this->deliveredOrder();

        $this->requestReturn($order, 1, 'damaged')->assertSessionHasErrors('photo');
        $this->requestReturn($order, 0)->assertSessionHasErrors('quantities');

        $this->requestReturn($order, 5);
        $this->assertSame(2, ReturnRequest::sole()->items()->sole()->quantity);
    }

    public function test_returns_are_only_possible_on_delivered_parts_within_the_window(): void
    {
        $order = $this->deliveredOrder();
        $part = $this->partA($order);

        $part->update(['delivered_at' => now()->subDays(8)]);
        $this->requestReturn($order);
        $this->assertSame(0, ReturnRequest::count());

        $part->update(['delivered_at' => now(), 'status' => 'shipped']);
        $this->requestReturn($order);
        $this->assertSame(0, ReturnRequest::count());

        // Someone else's order
        $part->update(['status' => 'delivered']);
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('orders.returns.store', [$order, $part]), ['quantities' => [1 => 1], 'reason' => 'faulty'])->assertForbidden();
    }

    public function test_approving_restocks_and_takes_the_shops_share_from_its_next_payout(): void
    {
        $order = $this->deliveredOrder();
        $payouts = app(PayoutService::class);
        $this->requestReturn($order, 1, 'change_of_mind');
        $return = ReturnRequest::sole();

        $this->actingAs($this->admin)->get(route('admin.returns'))->assertOk()->assertSee('Island Crafts');
        // Change of mind on a two-shop order: just the item, no delivery
        $this->get(route('admin.returns.show', $return))->assertOk()->assertSee('value="100.00"', false);

        $stock = $this->productA->fresh()->stock_quantity;
        $this->post(route('admin.returns.approve', $return), ['refund_amount' => 100, 'restock' => 1, 'admin_note' => 'Courier collects on Sunday.'])->assertSessionHas('success');

        $return->refresh();
        $this->assertSame('approved', $return->status);
        $this->assertEquals(100, $return->refund_amount);
        $this->assertSame($stock + 1, $this->productA->fresh()->stock_quantity);
        Notification::assertSentTo($this->customer, ReturnUpdated::class, fn ($n) => $n->request->status === 'approved');

        // Shop A earned 170 on the order (15% commission); the returned item's share is 85.
        $adjustment = SellerAdjustment::sole();
        $this->assertEquals(-85, $adjustment->amount);
        $this->assertEquals(85, $payouts->balances($this->shopA)['available']);

        $this->post(route('admin.payouts.store', $this->shopA), ['parts' => [$this->partA($order)->id], 'reference' => 'TRF-1']);
        $payout = SellerPayout::sole();
        $this->assertEquals(85, $payout->amount);
        $this->assertSame($payout->id, $adjustment->fresh()->payout_id);
        $this->assertEquals(0, $payouts->balances($this->shopA)['available']);
        $this->assertStringContainsString('Return on order', $this->get(route('admin.payouts.show', [$payout, 'export' => 'csv']))->streamedContent());

        // Can't approve twice
        $this->post(route('admin.returns.approve', $return), ['refund_amount' => 100])->assertSessionHas('error');
        $this->assertSame(1, SellerAdjustment::count());
    }

    public function test_a_return_after_the_shop_was_paid_is_deducted_from_the_next_payout(): void
    {
        $order = $this->deliveredOrder();
        $this->actingAs($this->admin)->post(route('admin.payouts.store', $this->shopA), ['parts' => [$this->partA($order)->id], 'reference' => 'TRF-1']);

        $this->requestReturn($order, 2);
        $this->actingAs($this->admin)->post(route('admin.returns.approve', ReturnRequest::sole()), ['refund_amount' => 200, 'restock' => 0]);

        // Owes 170 back; nothing to pay until new sales cover it
        $this->assertEquals(-170, app(PayoutService::class)->balances($this->shopA)['available']);
        $this->actingAs($this->shopA)->get(route('seller.earnings'))->assertOk()->assertSee('Returns &amp; adjustments', false)->assertSee('Next payout');

        $second = $this->deliveredOrder(); // shop A earns another 170
        $this->actingAs($this->admin)->post(route('admin.payouts.store', $this->shopA), ['parts' => [$this->partA($second)->id], 'reference' => 'TRF-2'])->assertSessionHas('error');
        $this->assertSame(1, SellerPayout::count());
    }

    public function test_shop_fault_on_a_single_shop_order_suggests_the_delivery_fee_too_and_refunds_are_capped(): void
    {
        $order = $this->deliveredOrder();
        $order->sellerOrders()->where('seller_id', $this->shopB->id)->delete();
        $order->update(['shipping_amount' => 25, 'total_amount' => 325]);

        $this->requestReturn($order, 1, 'wrong_item', ['photo' => UploadedFile::fake()->image('wrong.png')]);
        $return = ReturnRequest::sole();

        $this->actingAs($this->admin)->get(route('admin.returns.show', $return))->assertSee('value="125.00"', false);
        $this->post(route('admin.returns.approve', $return), ['refund_amount' => 400])->assertSessionHas('error');
        $this->assertSame('requested', $return->fresh()->status);
    }

    public function test_reject_and_mark_refunded_email_the_customer(): void
    {
        $order = $this->deliveredOrder();
        $this->requestReturn($order);
        $return = ReturnRequest::sole();

        $this->actingAs($this->admin)->post(route('admin.returns.refunded', $return), ['refund_reference' => 'x'])->assertSessionHas('error');
        $this->post(route('admin.returns.reject', $return), ['admin_note' => 'The item was used.'])->assertSessionHas('success');
        $this->assertSame('rejected', $return->fresh()->status);
        Notification::assertSentTo($this->customer, ReturnUpdated::class, fn ($n) => $n->request->status === 'rejected');
        $this->assertSame(0, SellerAdjustment::count());

        // A rejected request frees the items for a new one
        $this->requestReturn($order);
        $second = ReturnRequest::latest('id')->first();
        $this->assertNotSame($return->id, $second->id);

        $this->actingAs($this->admin)->post(route('admin.returns.approve', $second), ['refund_amount' => 100]);
        $this->post(route('admin.returns.refunded', $second), ['refund_reference' => 'BML-RF-889'])->assertSessionHas('success');
        $this->assertSame('refunded', $second->fresh()->status);
        Notification::assertSentTo($this->customer, ReturnUpdated::class, fn ($n) => $n->request->status === 'refunded' && $n->request->refund_reference === 'BML-RF-889');

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('BML-RF-889')->assertSee('The item was used.');
    }

    public function test_access_to_returns_screens_and_photos(): void
    {
        $order = $this->deliveredOrder();
        $this->requestReturn($order, 1, 'damaged', ['photo' => UploadedFile::fake()->image('broken.jpg')]);
        $return = ReturnRequest::sole();

        $this->actingAs($this->customer)->get(route('admin.returns'))->assertForbidden();
        $this->actingAs($this->shopA)->get(route('admin.returns.show', $return))->assertForbidden();

        $this->actingAs($this->shopA)->get(route('seller.returns'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($this->shopB)->get(route('seller.returns'))->assertOk()->assertDontSee($order->order_number);

        $this->actingAs($this->customer)->get(route('returns.photo', $return))->assertOk();
        $this->actingAs($this->shopA)->get(route('returns.photo', $return))->assertOk();
        $this->actingAs($this->admin)->get(route('returns.photo', $return))->assertOk();
        $this->actingAs($this->shopB)->get(route('returns.photo', $return))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('returns.photo', $return))->assertForbidden();
    }
}
