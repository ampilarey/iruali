<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerDeliverySetting;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\BackInStock;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PreorderArrived;
use App\Notifications\PreorderDateChanged;
use App\Notifications\RefundDue;
use App\Services\DisputeService;
use App\Services\FulfilmentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PreorderService;
use App\Services\SellerPerformanceService;
use App\Support\AdminInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Pre-orders after checkout: "Awaiting stock" parts that cannot be sent, the shop's Pre-orders page
 * and "Stock arrived" (oldest paid orders first, partial arrivals), moving the date (emails with a
 * link to cancel), cancelling with a refund through the normal cancellation, and late pre-orders in
 * the admin inbox.
 */
class PreorderFulfilmentTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected User $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75, 'contact_email' => 'ops@iruali.test']);
        $this->shop = $this->seller('Reef Goods');
    }

    /** Units of a product on the shelf now. */
    protected function stockOf(Product $product): int
    {
        return (int) $product->fresh()->stock_quantity;
    }

    public function test_a_part_with_pre_order_items_shows_awaiting_stock_to_the_shop_the_customer_and_admins(): void
    {
        $product = $this->preorderProduct($this->shop, ['name' => 'Coral lamp']);
        $mug = $this->stockedProduct($this->shop, 40, 5);
        $customer = User::factory()->create();
        $order = $this->paidOrder($customer, [[$product, 1], [$mug, 1]]);

        $this->actingAs($this->shop)->get(route('seller.orders'))->assertOk()->assertSee('data-awaiting-stock', false)->assertSee('Awaiting stock');
        $this->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('data-preorder-part="waiting"', false)
            ->assertSee('Coral lamp × 1: ships around 3 Nov')
            ->assertSee('record your delivery under Pre-orders')
            ->assertSee('Start preparing')
            ->assertDontSee('Mark as sent');

        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('data-preorder-part="waiting"', false)
            ->assertSee('The shop sends these items when their stock arrives')
            ->assertSee(route('orders.preorder.cancel', $order), false);

        $admin = $this->staffMember('admin');
        $html = $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('data-preorder-part="waiting"', false)
            ->assertSee('can\'t be marked sent until the shop records the stock', false)
            ->getContent();
        $this->assertStringNotContainsString('value="shipped"', $html, 'neither the part nor the whole order offers "shipped"');
    }

    public function test_the_part_cannot_be_marked_shipped_until_its_stock_has_arrived(): void
    {
        $product = $this->preorderProduct($this->shop);
        $order = $this->paidOrder(User::factory()->create(), [[$product, 2]]);
        $part = $order->sellerOrders()->sole();
        $fulfilment = app(FulfilmentService::class);

        $this->actingAs($this->shop)->post(route('seller.orders.status', $order), ['status' => 'processing'])->assertSessionHas('success');
        $this->assertSame([], $fulfilment->nextStatuses($part->fresh()));
        $this->post(route('seller.orders.status', $order), ['status' => 'shipped'])->assertSessionHas('error');
        $this->assertSame('processing', $part->fresh()->status);

        $admin = $this->staffMember('admin');
        $this->actingAs($admin)->post(route('admin.orders.parts.status', [$order, $part]), ['status' => 'shipped'])->assertSessionHas('error');
        $this->assertNotContains('shipped', app(OrderService::class)->nextStatuses($order->fresh()));
        $this->post(route('admin.orders.status', $order), ['status' => 'shipped'])->assertSessionHas('error');
        $this->assertSame('processing', $order->fresh()->status);

        // The stock comes in: the part follows the normal flow again
        app(PreorderService::class)->receiveStock($product, [0 => 2]);
        $this->assertFalse($part->fresh()->awaiting_stock);
        $this->assertNotNull($part->fresh()->stock_arrived_at);
        $this->actingAs($this->shop)->post(route('seller.orders.status', $order), ['status' => 'shipped'])->assertSessionHas('success');
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_a_pickup_part_cannot_be_marked_ready_while_it_awaits_stock(): void
    {
        $island = \App\Models\Island::create(['name' => ['en' => 'Malé', 'dv' => 'މާލެ'], 'atoll' => 'Kaafu', 'is_active' => true]);
        SellerDeliverySetting::create(['seller_id' => $this->shop->id, 'ships_within_days' => 1, 'pickup_enabled' => true, 'pickup_address' => 'M. Reef Store', 'pickup_island_id' => $island->id, 'pickup_hours' => '9 to 9']);
        $product = $this->preorderProduct($this->shop);
        $cart = $this->cartWith($customer = User::factory()->create(), [[$product, 1]]);
        $result = app(OrderService::class)->createOrderFromCart($customer, $this->shippingData() + ['fulfilment' => [$this->shop->id => 'pickup']], $cart);
        $this->assertTrue($result['success'], $result['message']);
        $order = $result['order'];
        app(PaymentService::class)->confirm($order->fresh());
        $part = $order->sellerOrders()->sole();
        $this->assertTrue($part->isPickup());

        $this->assertSame('awaiting_stock', app(FulfilmentService::class)->pickupReadiness($part->fresh()));
        $this->actingAs($this->shop)->post(route('seller.orders.pickup.ready', $order))
            ->assertSessionHas('error', 'These pre-order items are still waiting for their stock. Record it under Pre-orders when it arrives.');
        $this->assertNull($part->fresh()->pickup_ready_at);

        app(PreorderService::class)->receiveStock($product, [0 => 1]);
        $this->assertSame('ok', app(FulfilmentService::class)->pickupReadiness($part->fresh()));
    }

    public function test_stock_arrived_goes_to_the_oldest_paid_pre_orders_first_including_partial_arrivals(): void
    {
        $product = $this->preorderProduct($this->shop, ['preorder_limit' => 10]);
        $alice = User::factory()->create();
        $first = $this->paidOrder($alice, [[$product, 2]]);
        $unpaid = $this->order(User::factory()->create(), [[$product, 1]])['order'];
        $bob = User::factory()->create();
        $second = $this->paidOrder($bob, [[$product, 3]]);
        $carol = User::factory()->create();
        $third = $this->paidOrder($carol, [[$product, 1]]);
        StockAlert::create(['product_id' => $product->id, 'email' => 'waiting@example.com', 'locale' => 'en']);

        $this->actingAs($this->shop)->get(route('seller.preorders'))->assertOk()
            ->assertSee('data-preorder-product="'.$product->id.'"', false)
            ->assertSee('7 units waiting')
            ->assertSee('#'.$first->order_number)->assertSee('#'.$unpaid->order_number);

        // 4 arrive: the oldest paid order gets its 2, the unpaid one is passed over, the next gets 2 of its 3
        $this->post(route('seller.preorders.arrived', $product->id), ['received' => [0 => 4]])->assertSessionHas('success');

        $line = fn (Order $order) => $order->items()->sole();
        $this->assertNotNull($line($first)->preorder_allocated_at);
        $this->assertSame(0, $line($unpaid)->preorder_allocated_quantity);
        $this->assertSame(2, $line($second)->preorder_allocated_quantity);
        $this->assertTrue($line($second)->isPreorderWaiting());
        $this->assertSame(0, $line($third)->preorder_allocated_quantity);
        $this->assertSame(0, $this->stockOf($product), 'everything went to the waiting customers');
        $this->assertFalse($first->sellerOrders()->sole()->awaiting_stock);
        $this->assertTrue($second->sellerOrders()->sole()->awaiting_stock);
        Notification::assertSentTo($alice, PreorderArrived::class, fn ($n) => $n->order->is($first));
        Notification::assertNotSentTo($bob, PreorderArrived::class);
        Notification::assertSentOnDemandTimes(BackInStock::class, 0); // nothing reached the shelf
        $this->assertSame(['received' => 4, 'allocated' => 4, 'to_stock' => 0, 'orders' => 1], array_intersect_key(AuditLog::where('action', 'preorder.stock_arrived')->sole()->changes, array_flip(['received', 'allocated', 'to_stock', 'orders'])));

        // 3 more: the rest of the second order, the third order, and one left over goes on sale
        $this->post(route('seller.preorders.arrived', $product->id), ['received' => [0 => 3]])->assertSessionHas('success');
        $this->assertNotNull($line($second)->preorder_allocated_at);
        $this->assertNotNull($line($third)->preorder_allocated_at);
        $this->assertTrue($line($unpaid)->isPreorderWaiting(), 'units go to paid orders only');
        $this->assertSame(1, $this->stockOf($product));
        Notification::assertSentTo($bob, PreorderArrived::class);
        Notification::assertSentTo($carol, PreorderArrived::class);
        // Real stock is on sale again: the back-in-stock alert goes out
        Notification::assertSentOnDemand(BackInStock::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'waiting@example.com');

        // Nothing came and nothing is on the shelf: nothing to do
        $other = $this->preorderProduct($this->shop);
        $this->paidOrder(User::factory()->create(), [[$other, 1]]);
        $this->post(route('seller.preorders.arrived', $other->id), ['received' => [0 => 0]])->assertSessionHasErrors('received');
    }

    public function test_stock_on_the_shelf_goes_to_waiting_pre_orders_and_options_are_counted_separately(): void
    {
        [$product, $small, $medium, $large] = $this->preorderVariantProduct($this->shop, 6);
        $medium->update(['stock_quantity' => 0]);
        $first = $this->paidOrder($ann = User::factory()->create(), [[$product, 2, $medium]]);
        $second = $this->paidOrder($ben = User::factory()->create(), [[$product, 1, $large]]);
        StockAlert::create(['product_id' => $product->id, 'product_variant_id' => $large->id, 'email' => 'large@example.com', 'locale' => 'en']);

        // One M arrives, three L: M gets 1 of 2, L is covered and 2 go on sale
        $this->actingAs($this->shop)->post(route('seller.preorders.arrived', $product->id), ['received' => [$medium->id => 1, $large->id => 3]])->assertSessionHas('success');
        $this->assertSame(1, $first->items()->sole()->preorder_allocated_quantity);
        $this->assertNotNull($second->items()->sole()->preorder_allocated_at);
        $this->assertSame(0, $medium->fresh()->stock_quantity);
        $this->assertSame(2, $large->fresh()->stock_quantity);
        $this->assertSame(5, $small->fresh()->stock_quantity, 'other options are left alone');
        $this->assertSame(7, $this->stockOf($product), 'the product total follows its options');
        Notification::assertSentTo($ben, PreorderArrived::class);
        Notification::assertNotSentTo($ann, PreorderArrived::class);
        Notification::assertSentOnDemand(BackInStock::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'large@example.com');

        // The shop typed an M into the stock page instead: "Stock arrived" with nothing new gives it to the waiting order
        $medium->update(['stock_quantity' => 1]);
        $this->post(route('seller.preorders.arrived', $product->id), ['received' => [$medium->id => '']])->assertSessionHas('success');
        $this->assertNotNull($first->items()->sole()->preorder_allocated_at);
        $this->assertSame(0, $medium->fresh()->stock_quantity);
        Notification::assertSentTo($ann, PreorderArrived::class);

        // Another product's option is not this product's
        $this->post(route('seller.preorders.arrived', $product->id), ['received' => [$this->preorderVariantProduct($this->shop)[1]->id => 1]])->assertNotFound();
    }

    public function test_only_the_shop_sees_and_handles_its_own_pre_orders(): void
    {
        $product = $this->preorderProduct($this->shop, ['name' => 'Coral lamp']);
        $this->paidOrder(User::factory()->create(), [[$product, 1]]);
        $other = $this->seller('Island Crafts');
        $theirs = $this->preorderProduct($other, ['name' => 'Woven basket']);

        $this->actingAs($this->shop)->get(route('seller.preorders'))->assertOk()
            ->assertSee('Coral lamp')->assertDontSee('Woven basket')
            ->assertSee(route('seller.preorders'), false);

        $this->actingAs($other)->get(route('seller.preorders'))->assertOk()
            ->assertSee('Taking pre-orders, none waiting yet')->assertSee('Woven basket')->assertDontSee('Coral lamp');
        $this->post(route('seller.preorders.arrived', $product->id), ['received' => [0 => 5]])->assertForbidden();
        $this->put(route('seller.preorders.date', $product->id), ['preorder_ship_date' => '2026-12-01'])->assertForbidden();
        $this->assertSame(0, $this->stockOf($product));
        $this->assertSame('2026-11-03', $product->fresh()->preorder_ship_date->toDateString());
        $this->assertSame(5, app(PreorderService::class)->unitsLeft($theirs));

        $this->actingAs(User::factory()->create())->get(route('seller.preorders'))->assertForbidden();
    }

    public function test_the_product_form_switches_pre_orders_on_with_a_date_a_limit_and_a_note(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $fields = ['name_en' => 'Coral lamp', 'sku' => 'LAMP-1', 'category_id' => $category->id, 'price' => 300, 'stock_quantity' => 0, 'reorder_point' => 2, 'preorder_form' => 1];

        $this->actingAs($this->shop)->get(route('seller.products.create'))->assertOk()
            ->assertSee('data-preorder-fieldset', false)
            ->assertSee('Take pre-orders when it is out of stock');

        $on = $fields + ['preorder_enabled' => 1];
        $this->post(route('seller.products.store'), $on)->assertSessionHasErrors(['preorder_ship_date', 'preorder_limit']);
        $this->post(route('seller.products.store'), $on + ['preorder_ship_date' => '2026-10-20', 'preorder_limit' => 5])
            ->assertSessionHasErrors(['preorder_ship_date' => 'The expected ship date must be after today.']);
        $this->post(route('seller.products.store'), $on + ['preorder_ship_date' => '2026-11-03', 'preorder_limit' => 0])->assertSessionHasErrors('preorder_limit');
        $this->assertSame(0, Product::count());

        $this->post(route('seller.products.store'), $on + ['preorder_ship_date' => '2026-11-03', 'preorder_limit' => 8, 'preorder_note' => '  Arrives on the next ship from Colombo  '])
            ->assertRedirect(route('seller.products.index'));
        $product = Product::where('sku', 'LAMP-1')->sole();
        $this->assertTrue($product->preorder_enabled);
        $this->assertSame('2026-11-03', $product->preorder_ship_date->toDateString());
        $this->assertSame(8, $product->preorder_limit);
        $this->assertSame('Arrives on the next ship from Colombo', $product->preorder_note);

        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('value="2026-11-03"', false)->assertSee('Arrives on the next ship from Colombo');

        // Off: the other fields are not checked and stay for next time
        $this->put(route('seller.products.update', $product), $fields + ['preorder_enabled' => 0, 'preorder_ship_date' => 'soon'])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertFalse($product->preorder_enabled);
        $this->assertSame(8, $product->preorder_limit);

        // A form without the fieldset leaves pre-orders alone
        $product->forceFill(['preorder_enabled' => true])->save();
        $this->put(route('seller.products.update', $product), array_diff_key($fields, ['preorder_form' => 1]))->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->preorder_enabled);
    }

    public function test_moving_the_date_emails_the_waiting_customers_with_a_link_to_cancel(): void
    {
        $product = $this->preorderProduct($this->shop, ['preorder_limit' => 10]);
        $covered = User::factory()->create();
        $done = $this->paidOrder($covered, [[$product, 1]]);
        $waiting = User::factory()->create();
        $mine = $this->paidOrder($waiting, [[$product, 1]]);
        // A guest pre-order too
        $guestResult = app(OrderService::class)->createOrderFromCart(null, $this->shippingData(), $this->cartWith(User::factory()->create(), [[$product, 2]]), ['email' => 'guest@example.com', 'name' => 'Guest']);
        $this->assertTrue($guestResult['success'], $guestResult['message']);
        $guestOrder = $guestResult['order'];

        // One unit arrives: it goes to the oldest order, which no longer waits
        app(PreorderService::class)->receiveStock($product, [0 => 1]);
        $this->assertNotNull($done->items()->sole()->preorder_allocated_at);
        OrderItem::where('order_id', $mine->id)->update(['preorder_late_at' => now()]);

        $this->actingAs($this->shop)->put(route('seller.preorders.date', $product->id), ['preorder_ship_date' => '2026-11-20'])
            ->assertSessionHas('success', 'The expected date is now 20 Nov 2026. Customers waiting for this product have been emailed.');

        $this->assertSame('2026-11-20', $mine->items()->sole()->preorder_ship_date->toDateString());
        $this->assertNull($mine->items()->sole()->preorder_late_at, 'no longer late');
        $this->assertSame('2026-11-20', $mine->sellerOrders()->sole()->preorder_ship_date->toDateString());
        $this->assertSame('2026-11-03', $done->items()->sole()->preorder_ship_date->toDateString(), 'its stock is in: unchanged');

        Notification::assertSentTo($waiting, PreorderDateChanged::class, function (PreorderDateChanged $notification) use ($waiting, $mine) {
            $mail = $notification->toMail($waiting);
            $text = implode("\n", $mail->introLines);

            return $notification->order->is($mine) && str_contains($text, 'It is now expected to ship around 20 Nov 2026 (it was 3 Nov 2026).')
                && str_contains($text, route('orders.preorder.cancel', $mine));
        });
        Notification::assertNotSentTo($covered, PreorderDateChanged::class);
        Notification::assertSentOnDemand(PreorderDateChanged::class, function (PreorderDateChanged $notification, $channels, $notifiable) use ($guestOrder) {
            $text = implode("\n", $notification->toMail($notifiable)->introLines);

            return $notifiable->routes['mail'] === 'guest@example.com' && $notification->order->is($guestOrder)
                && str_contains($text, '/orders/guest/'.$guestOrder->id.'/') && str_contains($text, 'signature=');
        });
        $this->assertSame(['from' => '2026-11-03', 'to' => '2026-11-20'], array_intersect_key(AuditLog::where('action', 'preorder.date_moved')->sole()->changes, ['from' => 1, 'to' => 1]));

        // The same date again changes nothing and emails nobody
        Notification::fake();
        $this->put(route('seller.preorders.date', $product->id), ['preorder_ship_date' => '2026-11-20'])->assertSessionHas('success', 'That is already the expected date.');
        $this->put(route('seller.preorders.date', $product->id), ['preorder_ship_date' => '2026-10-19'])->assertSessionHasErrors('preorder_ship_date');
        Notification::assertNothingSent();
    }

    public function test_the_product_form_moving_the_date_emails_the_waiting_customers_too(): void
    {
        $product = $this->preorderProduct($this->shop);
        $customer = User::factory()->create();
        $this->paidOrder($customer, [[$product, 1]]);

        $this->actingAs($this->shop)->put(route('seller.products.update', $product), [
            'name_en' => $product->getTranslation('name', 'en'), 'sku' => $product->sku, 'category_id' => $product->category_id,
            'price' => $product->price, 'stock_quantity' => 0, 'reorder_point' => 5,
            'preorder_form' => 1, 'preorder_enabled' => 1, 'preorder_ship_date' => '2026-11-10', 'preorder_limit' => 5,
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($customer, PreorderDateChanged::class, fn ($n) => $n->newDate === '2026-11-10' && $n->oldDate === '2026-11-03');
    }

    public function test_cancelling_a_pre_order_refunds_it_through_the_normal_cancellation(): void
    {
        $this->enableBml();
        $product = $this->preorderProduct($this->shop, ['price' => 300]);
        $mug = $this->stockedProduct($this->shop, 50, 5);
        $customer = User::factory()->create();
        $order = $this->paidOrder($customer, [[$product, 1], [$mug, 2]]);
        $this->assertSame(3, $this->stockOf($mug));

        $this->actingAs(User::factory()->create())->get(route('orders.preorder.cancel', $order))->assertForbidden();

        $this->actingAs($customer)->get(route('orders.preorder.cancel', $order))->assertOk()
            ->assertSee('Cancel order '.$order->order_number.'?')
            ->assertSee('Pre-order: ships around 3 Nov')
            ->assertSee('We refund MVR 475.00 to the card you paid with.');

        $this->post(route('orders.preorder.cancel.store', $order))->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('notification', fn ($flash) => str_contains($flash['message'], 'We will refund MVR 475.00 to your card'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('due', $order->refund_status, 'flagged like any paid order cancelled');
        $this->assertEquals(475, $order->refund_amount);
        $this->assertSame('Order cancelled after payment', $order->refund_reason);
        $this->assertSame('cancelled', $order->sellerOrders()->sole()->status);
        $this->assertSame(5, $this->stockOf($mug), 'the in-stock items go back');
        $this->assertSame(0, $this->stockOf($product), 'the pre-order units never existed: nothing goes back');
        $this->assertSame(0, app(PreorderService::class)->waitingUnits($product->id));
        $this->assertTrue(AuditLog::where('action', 'order.cancelled')->exists());
        $this->assertTrue(AuditLog::where('action', 'preorder.cancelled')->exists());
        Notification::assertSentOnDemand(RefundDue::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'ops@iruali.test');
        Notification::assertSentTo($customer, OrderStatusChanged::class);

        // Cancelled already: nothing more to do
        $this->get(route('orders.preorder.cancel', $order))->assertOk()->assertSee('This order has already been cancelled.');
        $this->post(route('orders.preorder.cancel.store', $order))->assertSessionHas('notification', fn ($flash) => $flash['type'] === 'error');
    }

    public function test_a_pre_order_cannot_be_cancelled_once_part_of_the_order_has_been_sent(): void
    {
        $product = $this->preorderProduct($this->shop);
        $other = $this->seller('Island Crafts');
        $basket = $this->stockedProduct($other, 80, 3);
        $customer = User::factory()->create();
        $order = $this->paidOrder($customer, [[$product, 1], [$basket, 1]]);
        $fulfilment = app(FulfilmentService::class);
        $islandPart = $order->sellerOrders()->where('seller_id', $other->id)->sole();
        $fulfilment->advance($islandPart, 'processing');
        $fulfilment->advance($islandPart->fresh(), 'shipped');

        $this->assertFalse(app(PreorderService::class)->canCancel($order->fresh()));
        $this->actingAs($customer)->get(route('orders.preorder.cancel', $order))->assertOk()
            ->assertSee('part of it has already been sent');
        $this->post(route('orders.preorder.cancel.store', $order))->assertRedirect(route('orders.show', $order));
        $this->assertNotSame('cancelled', $order->fresh()->status);
        $this->get(route('orders.show', $order))->assertOk()->assertDontSee('data-preorder-cancel', false);
    }

    public function test_a_guest_cancels_through_the_signed_link(): void
    {
        $product = $this->preorderProduct($this->shop);
        $result = app(OrderService::class)->createOrderFromCart(null, $this->shippingData(), $this->cartWith(User::factory()->create(), [[$product, 1]]), ['email' => 'guest@example.com', 'name' => 'Guest']);
        $order = $result['order'];
        app(PaymentService::class)->confirm($order->fresh());

        $url = app(PreorderService::class)->cancelUrl($order->fresh());
        $this->get($url)->assertOk()->assertSee('Yes, cancel my order');
        $this->get(route('guest.orders.preorder.cancel', ['order' => $order->id, 'token' => $order->guest_token]))->assertForbidden();
        $this->get(URL::signedRoute('guest.orders.preorder.cancel', ['order' => $order->id, 'token' => 'not-the-token']))->assertForbidden();

        $action = URL::signedRoute('guest.orders.preorder.cancel.store', ['order' => $order->id, 'token' => $order->guest_token]);
        $this->post($action)->assertRedirect($order->fresh()->guestUrl());
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('due', $order->fresh()->refund_status);
    }

    public function test_units_given_back_by_a_cancelled_order_go_to_the_next_waiting_pre_order(): void
    {
        $product = $this->preorderProduct($this->shop, ['preorder_limit' => 10]);
        $first = $this->paidOrder(User::factory()->create(), [[$product, 2]]);
        $nextCustomer = User::factory()->create();
        $next = $this->paidOrder($nextCustomer, [[$product, 2]]);
        app(PreorderService::class)->receiveStock($product, [0 => 2]);
        $this->assertNotNull($first->items()->sole()->preorder_allocated_at);

        $this->assertTrue(app(PreorderService::class)->cancel($first->fresh()));

        $this->assertNotNull($next->items()->sole()->preorder_allocated_at, 'the next customer in line gets the units');
        $this->assertSame(0, $this->stockOf($product));
        Notification::assertSentTo($nextCustomer, PreorderArrived::class);
    }

    public function test_late_pre_orders_go_to_the_admin_inbox_once_a_day(): void
    {
        $product = $this->preorderProduct($this->shop, ['preorder_limit' => 10]);
        $late = $this->paidOrder(User::factory()->create(), [[$product, 1]]);
        $cancelled = $this->paidOrder(User::factory()->create(), [[$product, 1]]);
        app(OrderService::class)->updateOrderStatus($cancelled, 'cancelled');

        // Seven days past 3 Nov is not yet "more than seven"
        $this->travelTo(Carbon::parse('2026-11-10 07:00'));
        $this->artisan('preorders:flag-late')->assertExitCode(0);
        $this->assertNull($late->items()->sole()->preorder_late_at);

        $this->travelTo(Carbon::parse('2026-11-11 07:00'));
        $this->artisan('preorders:flag-late')->expectsOutputToContain('Flagged 1 late pre-order line(s)')->assertExitCode(0);
        $this->assertNotNull($late->items()->sole()->preorder_late_at);
        $this->assertNull($cancelled->items()->sole()->preorder_late_at);

        AdminInbox::forget('__refresh__');
        $row = collect(AdminInbox::all())->firstWhere('key', 'late_preorders');
        $this->assertSame(['Late pre-orders', 1, route('admin.preorders')], [$row['label'], $row['count'], $row['url']]);

        $support = $this->staffMember('support');
        $this->actingAs($support)->get(route('admin.inbox'))->assertOk()->assertSee('data-inbox="late_preorders" data-count="1"', false);
        $this->get(route('admin.preorders', ['late' => 1]))->assertOk()
            ->assertSee('#'.$late->order_number)->assertSee('Late: 8 days')->assertDontSee('#'.$cancelled->order_number);

        // The stock arrives: no longer waiting, no longer in the inbox
        app(PreorderService::class)->receiveStock($product, [0 => 1]);
        AdminInbox::forget('__refresh__');
        $this->assertSame(0, collect(AdminInbox::all())->firstWhere('key', 'late_preorders')['count']);

        $this->artisan('schedule:list')->expectsOutputToContain('preorders:flag-late');
    }

    public function test_a_waiting_part_is_not_late_and_not_reported_undelivered_before_its_date(): void
    {
        Setting::set(['late_shipment_days' => 3]);
        $product = $this->preorderProduct($this->shop);
        $customer = User::factory()->create();
        $order = $this->paidOrder($customer, [[$product, 1]]);
        $part = $order->sellerOrders()->sole();
        $performance = app(SellerPerformanceService::class);
        $disputes = app(DisputeService::class);

        // 10 days after payment, still before 3 Nov: on time, and no "not delivered" claim yet
        $this->travelTo(Carbon::parse('2026-10-30 10:00'));
        $this->assertFalse($performance->isLate($part->fresh(), Carbon::parse($order->paid_at)));
        $this->assertNotContains('non_delivery', $disputes->availableTypes($part->fresh(), $customer));

        // Its date and the shop's 3 days have passed and it still waits: late, and it can be reported
        $this->travelTo(Carbon::parse('2026-11-12 10:00'));
        $this->assertTrue($performance->isLate($part->fresh(), Carbon::parse($order->paid_at)));
        $this->assertContains('non_delivery', $disputes->availableTypes($part->fresh(), $customer));

        // Once the stock is in, the shop's days to ship count from then
        app(PreorderService::class)->receiveStock($product, [0 => 1]);
        $this->assertFalse($performance->isLate($part->fresh(), Carbon::parse($order->paid_at)));
    }
}
