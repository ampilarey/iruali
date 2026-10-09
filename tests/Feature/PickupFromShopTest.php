<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Island;
use App\Models\Order;
use App\Models\SellerDeliverySetting;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\PickupReady;
use App\Services\PaymentService;
use App\Services\ReturnService;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Pick up from the shop: the shop's settings, choosing it at checkout (fees cover only delivered
 * parts), "Ready for pickup" with a 6-digit code, confirming the collection, and who may do what.
 */
class PickupFromShopTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected Island $male;

    protected User $customer;

    protected User $pickupShop;

    protected User $deliveryShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();
        Setting::set(['delivery_fee_greater_male' => 25, 'delivery_fee_islands' => 75, 'free_delivery_over' => 1000]);
        $this->male = $this->island('Malé', 'Kaafu');
        $this->customer = User::factory()->create(['phone' => '7770000']);
        $this->pickupShop = $this->shop('Coral Corner', $this->male);
        $this->deliveryShop = $this->shop('Island Post Shop');
    }

    /**
     * A paid order with one pickup part from the pickup shop.
     */
    protected function paidPickupOrder(float $price = 300): Order
    {
        $this->cartFor($this->customer, [[$this->product($this->pickupShop, $price), 1]]);
        $this->placeOrder($this->customer, ['fulfilment' => [$this->pickupShop->id => 'pickup']])->assertRedirect();
        $order = Order::latest('id')->first();
        app(PaymentService::class)->confirm($order);

        return $order->fresh();
    }

    protected function part(Order $order, User $shop): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $shop->id)->sole();
    }

    public function test_shops_set_up_pickup_in_their_settings(): void
    {
        $this->actingAs($this->deliveryShop)->get('/seller/settings/delivery')->assertOk()
            ->assertSee('Usually ships within (days)')
            ->assertSee('name="pickup_enabled"', false)
            ->assertSee('Delivery &amp; pickup', false);
        $this->actingAs($this->deliveryShop)->get('/seller/settings/bank')->assertOk()->assertSee(route('seller.settings.delivery'), false);

        // Pickup needs an address, an island and opening hours
        $this->actingAs($this->deliveryShop)->put('/seller/settings/delivery', ['ships_within_days' => 2, 'pickup_enabled' => '1'])
            ->assertSessionHasErrors(['pickup_address', 'pickup_island_id', 'pickup_hours']);
        $this->actingAs($this->deliveryShop)->put('/seller/settings/delivery', ['ships_within_days' => 40])->assertSessionHasErrors('ships_within_days');

        $this->actingAs($this->deliveryShop)->put('/seller/settings/delivery', [
            'ships_within_days' => 2, 'pickup_enabled' => '1', 'pickup_address' => 'H. Sea Breeze', 'pickup_island_id' => $this->male->id, 'pickup_hours' => 'Daily 10:00-22:00',
        ])->assertRedirect(route('seller.settings.delivery'));

        $setting = SellerDeliverySetting::where('seller_id', $this->deliveryShop->id)->sole();
        $this->assertSame(2, $setting->shipsWithinDays());
        $this->assertTrue($setting->offersPickup());
        $this->assertSame('Malé, Kaafu', $setting->pickupIslandName());

        // Turning it off keeps the address for next time
        $this->actingAs($this->deliveryShop)->put('/seller/settings/delivery', ['ships_within_days' => 1, 'pickup_enabled' => '0', 'pickup_address' => 'H. Sea Breeze', 'pickup_island_id' => $this->male->id])->assertRedirect();
        $this->assertFalse($setting->fresh()->offersPickup());

        // Shops only: customers can't open it
        $this->actingAs($this->customer)->get('/seller/settings/delivery')->assertForbidden();
    }

    public function test_checkout_offers_pickup_only_for_shops_that_have_it(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->pickupShop, 100), 1], [$this->product($this->deliveryShop, 100), 1]]);

        $this->actingAs($this->customer)->get('/checkout')->assertOk()
            ->assertSee('Delivery or pickup')
            ->assertSee('Pick up from Coral Corner')
            ->assertSee('name="fulfilment['.$this->pickupShop->id.']"', false)
            ->assertDontSee('name="fulfilment['.$this->deliveryShop->id.']"', false)
            ->assertSee('Sat to Thu, 9:00 to 21:00');

        // Choosing pickup from a shop that does not offer it is refused
        $this->placeOrder($this->customer, ['fulfilment' => [$this->deliveryShop->id => 'pickup']])
            ->assertSessionHasErrors('fulfilment.'.$this->deliveryShop->id);
        $this->assertSame(0, Order::count());
    }

    public function test_the_fee_covers_only_the_delivered_parts(): void
    {
        $bulkyPickup = $this->product($this->pickupShop, 200, 40);
        $bulkyDelivered = $this->product($this->deliveryShop, 300, 30);
        $this->cartFor($this->customer, [[$bulkyPickup, 2], [$bulkyDelivered, 1]]);

        $this->placeOrder($this->customer, ['fulfilment' => [$this->pickupShop->id => 'pickup', $this->deliveryShop->id => 'deliver']])->assertRedirect();

        $order = Order::sole();
        // Greater Malé fee once + the delivered item's charge; the picked-up items' charges are not taken
        $this->assertEquals(25 + 30, $order->shipping_amount);
        $this->assertEquals(30, $order->delivery_surcharge);
        $this->assertEquals(700 + 55, $order->total_amount);
        $this->assertSame('greater_male', $order->delivery_area);

        $pickup = $this->part($order, $this->pickupShop);
        $this->assertTrue($pickup->isPickup());
        $this->assertEquals(0, $pickup->delivery_surcharge);
        $this->assertSame('M. Coral Corner Store, Majeedhee Magu', $pickup->pickup_address);
        $this->assertSame('Malé, Kaafu', $pickup->pickup_island);
        $this->assertSame('Sat to Thu, 9:00 to 21:00', $pickup->pickup_hours);
        $this->assertNull($pickup->pickup_code, 'no code until the shop has it ready');
        $delivered = $this->part($order, $this->deliveryShop);
        $this->assertSame('deliver', $delivered->delivery_method);
        $this->assertEquals(30, $delivered->delivery_surcharge);
    }

    public function test_each_shops_new_order_email_says_how_its_part_reaches_the_customer(): void
    {
        Notification::fake();
        $pickupItem = $this->product($this->pickupShop, 100);
        $deliveredItem = $this->product($this->deliveryShop, 100);
        $this->cartFor($this->customer, [[$pickupItem, 1], [$deliveredItem, 1]]);

        $this->placeOrder($this->customer, [
            'fulfilment' => [$this->pickupShop->id => 'pickup'],
            'is_gift' => '1', 'gift_receiver_name' => 'Mariyam Ali', 'gift_receiver_phone' => '9998877',
        ])->assertRedirect();

        $lines = function (User $shop) {
            $sent = Notification::sent($shop, \App\Notifications\NewSellerOrder::class)->first();

            return implode("\n", $sent->toMail($shop)->introLines);
        };
        $this->assertStringContainsString('picks these items up from your shop', $lines($this->pickupShop));
        $this->assertStringNotContainsString('picks these items up', $lines($this->deliveryShop));
        $this->assertStringContainsString('This is a gift for Mariyam Ali', $lines($this->deliveryShop));
    }

    public function test_free_delivery_with_a_mixed_cart_still_charges_the_delivered_bulky_items(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->pickupShop, 900, 40), 1], [$this->product($this->deliveryShop, 200, 30), 1]]);

        $this->placeOrder($this->customer, ['fulfilment' => [$this->pickupShop->id => 'pickup']])->assertRedirect();

        // 1100 of goods is over the threshold: no area fee, only the delivered part's bulky charge
        $order = Order::sole();
        $this->assertEquals(30, $order->shipping_amount);
        $this->assertEquals(1130, $order->total_amount);
    }

    public function test_everything_picked_up_needs_no_address_and_no_fee_but_a_phone(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->pickupShop, 150, 40), 2]]);
        $pickupOnly = ['fulfilment' => [$this->pickupShop->id => 'pickup'], 'shipping_address' => '', 'shipping_city' => '', 'shipping_state' => '', 'shipping_country' => '', 'delivery_zone' => ''];

        $this->placeOrder($this->customer, $pickupOnly + ['shipping_phone' => ''])->assertSessionHasErrors('shipping_phone');
        $this->assertSame(0, Order::count());

        $this->actingAs($this->customer)->post('/orders', [
            'fulfilment' => [$this->pickupShop->id => 'pickup'], 'shipping_phone' => '777 5555', 'payment_method' => 'bml', 'agree_terms' => '1',
            'save_address' => '1',
        ])->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertEquals(0, $order->shipping_amount);
        $this->assertEquals(0, $order->delivery_surcharge);
        $this->assertEquals(300, $order->total_amount);
        $this->assertNull($order->delivery_area);
        $this->assertNull($order->delivery_zone);
        $this->assertSame('7775555', $order->shipping_phone);
        $this->assertSame('Pickup from the shop', $order->shipping_address);
        $this->assertSame(0, $this->customer->addresses()->count(), 'nothing to save as an address');

        // A delivery still needs the address
        $this->cartFor($this->customer, [[$this->product($this->deliveryShop, 100), 1]]);
        $this->actingAs($this->customer)->post('/orders', ['shipping_phone' => '7775555', 'payment_method' => 'bml', 'agree_terms' => '1'])
            ->assertSessionHasErrors(['shipping_address', 'shipping_city']);
    }

    public function test_the_shop_marks_it_ready_and_the_customer_gets_a_code_by_email(): void
    {
        Notification::fake();
        $order = $this->paidPickupOrder();
        $part = $this->part($order, $this->pickupShop);

        $this->actingAs($this->pickupShop)->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('Pickup from your shop')
            ->assertSee('Ready for pickup')
            ->assertDontSee('Mark as sent');

        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order))->assertRedirect()->assertSessionHas('success');

        $part->refresh();
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $part->pickup_code);
        $this->assertNotNull($part->pickup_ready_at);
        $this->assertNotNull($part->shipped_at, 'ready counts as sent for late-shipment figures');
        $this->assertSame('processing', $part->status);
        $this->assertSame('processing', $order->fresh()->status);

        Notification::assertSentTo($this->customer, PickupReady::class, function (PickupReady $notification, array $channels) use ($part) {
            $mail = $notification->toMail($this->customer);
            $text = implode("\n", $mail->introLines);

            return $channels === ['mail'] && str_contains($text, $part->pickup_code) && str_contains($text, 'M. Coral Corner Store') && str_contains($text, 'Sat to Thu');
        });
        // No SMS gateway is set up, so no text
        Notification::assertNothingSentTo(new AnonymousNotifiable);
        $this->assertSame(0, SmsMessage::count());

        // The shop's order list flags it
        $this->actingAs($this->pickupShop)->get(route('seller.orders'))->assertOk()->assertSee('Ready for pickup');

        // Marking it again does nothing
        $code = $part->pickup_code;
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order))->assertSessionHas('error');
        $this->assertSame($code, $part->fresh()->pickup_code);
    }

    public function test_the_code_is_texted_when_sms_is_set_up(): void
    {
        config(['sms.driver' => 'http', 'sms.http.url' => 'https://sms.test/send', 'sms.http.success_regex' => 'OK']);
        app(SmsManager::class)->using(null);
        Http::fake(['sms.test/*' => Http::response('OK', 200), 'bml.test/*' => Http::response(['id' => 'txn_test', 'url' => 'https://pay.bml.test/txn_test', 'state' => 'INITIATED'], 201)]);

        $order = $this->paidPickupOrder();
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order))->assertSessionHas('success');

        $code = $this->part($order, $this->pickupShop)->pickup_code;
        $sms = SmsMessage::where('to', '+9607771234')->sole();
        $this->assertStringContainsString($code, $sms->message);
        $this->assertStringContainsString('Coral Corner', $sms->message);
    }

    public function test_no_code_goes_out_before_the_customer_has_paid(): void
    {
        Notification::fake();
        $this->cartFor($this->customer, [[$this->product($this->pickupShop, 100), 1]]);
        $this->placeOrder($this->customer, ['fulfilment' => [$this->pickupShop->id => 'pickup']])->assertRedirect();
        $order = Order::sole();

        $this->actingAs($this->pickupShop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Wait until the customer has paid');
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order))->assertSessionHas('error');
        $this->assertNull($this->part($order, $this->pickupShop)->pickup_code);
        Notification::assertNotSentTo($this->customer, PickupReady::class);
    }

    public function test_the_right_code_completes_the_pickup_like_a_delivery(): void
    {
        Notification::fake();
        $order = $this->paidPickupOrder();
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order));
        $part = $this->part($order, $this->pickupShop);
        $wrong = $part->pickup_code === '000000' ? '111111' : '000000';

        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.collected', $order), ['pickup_code' => $wrong])
            ->assertSessionHasErrors('pickup_code');
        $this->assertSame(1, $part->fresh()->pickup_code_attempts);
        $this->assertSame('processing', $part->fresh()->status);

        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.collected', $order), ['pickup_code' => substr($part->pickup_code, 0, 3).' '.substr($part->pickup_code, 3)])
            ->assertSessionHas('success');

        $part->refresh();
        $this->assertSame('delivered', $part->status);
        $this->assertNotNull($part->delivered_at);
        $this->assertNotNull($part->pickup_collected_at);
        // Same effects as a delivery: the order follows, the earnings are payable, returns open
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('available', $part->earningsState());
        $this->assertTrue(SellerOrder::payable()->whereKey($part->id)->exists());
        $this->assertTrue(app(ReturnService::class)->canRequest($part, $this->customer));

        $this->actingAs($this->pickupShop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Collected on');
    }

    public function test_too_many_wrong_codes_lock_it_until_iruali_confirms(): void
    {
        $order = $this->paidPickupOrder();
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order));
        $part = $this->part($order, $this->pickupShop);
        $wrong = $part->pickup_code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < SellerOrder::MAX_PICKUP_ATTEMPTS; $i++) {
            $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.collected', $order), ['pickup_code' => $wrong])->assertSessionHasErrors('pickup_code');
        }
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.collected', $order), ['pickup_code' => $part->pickup_code])->assertSessionHasErrors('pickup_code');
        $this->assertSame('processing', $part->fresh()->status);
        $this->actingAs($this->pickupShop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Too many wrong codes');

        // Support confirms it from the admin order page
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Pickup from the shop')
            ->assertSee($part->pickup_code)
            ->assertSee('Mark as collected');
        $this->actingAs($admin)->post(route('admin.orders.parts.collected', [$order, $part]))->assertSessionHas('success');
        $this->assertSame('delivered', $part->fresh()->status);
        $this->assertSame('pickup.confirmed', AuditLog::latest('id')->first()->action);
    }

    public function test_only_the_shop_itself_can_handle_its_pickup(): void
    {
        $order = $this->paidPickupOrder();
        $part = $this->part($order, $this->pickupShop);
        $otherShop = $this->shop('Other Shop', $this->male);

        $this->actingAs($otherShop)->post(route('seller.orders.pickup.ready', $order))->assertNotFound();
        $this->actingAs($otherShop)->post(route('seller.orders.pickup.collected', $order), ['pickup_code' => '123456'])->assertNotFound();
        $this->actingAs($otherShop)->get(route('seller.orders.packing-slip', $order))->assertNotFound();
        $this->actingAs($this->customer)->post(route('seller.orders.pickup.ready', $order))->assertForbidden();
        $this->actingAs($this->customer)->post(route('admin.orders.parts.collected', [$order, $part]))->assertForbidden();
        $this->actingAs($this->withRole('finance'))->post(route('admin.orders.parts.collected', [$order, $part]))->assertForbidden();
        auth()->logout();
        $this->post(route('seller.orders.pickup.ready', $order))->assertRedirect('/login');

        $this->assertNull($part->fresh()->pickup_ready_at);

        // A part of another order can't be confirmed through this order's URL
        $other = $this->paidPickupOrder();
        $this->actingAs($this->admin())->post(route('admin.orders.parts.collected', [$order, $this->part($other, $this->pickupShop)]))->assertNotFound();
    }

    public function test_the_customer_sees_where_when_and_the_code_but_others_do_not(): void
    {
        $order = $this->paidPickupOrder();
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order));
        $code = $this->part($order, $this->pickupShop)->pickup_code;

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('Pick up from Coral Corner')
            ->assertSee('M. Coral Corner Store, Majeedhee Magu')
            ->assertSee('Sat to Thu, 9:00 to 21:00')
            ->assertSee('Your pickup code')
            ->assertSee($code);

        // The shop has to ask for it: not on its order page, nor on the customer's page it may open
        $this->actingAs($this->pickupShop)->get(route('seller.orders.show', $order))->assertOk()->assertDontSee($code);
        $this->actingAs($this->pickupShop)->get(route('orders.show', $order))->assertOk()->assertDontSee($code);

        // The public tracking page (anyone with the order number) shows where, not the code
        $this->get(\Illuminate\Support\Facades\URL::temporarySignedRoute('order.track.show', now()->addMinutes(30), [$order]))->assertOk()
            ->assertSee('M. Coral Corner Store')
            ->assertDontSee($code);
    }

    public function test_guests_can_choose_pickup_and_see_their_code(): void
    {
        Notification::fake();
        Setting::set(['guest_checkout_enabled' => 1]);
        $product = $this->product($this->pickupShop, 120);
        $token = Str::random(40);
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => $token, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 120]);

        $this->withSession(['cart_token' => $token])->get('/checkout/guest')->assertOk()->assertSee('Pick up from Coral Corner');
        $this->withSession(['cart_token' => $token])->post('/checkout/guest', [
            'guest_email' => 'visitor@example.com', 'guest_name' => 'Mariyam', 'shipping_phone' => '7771234',
            'fulfilment' => [$this->pickupShop->id => 'pickup'], 'payment_method' => 'bml', 'agree_terms' => '1',
        ])->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertEquals(120, $order->total_amount);
        app(PaymentService::class)->confirm($order);
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order))->assertSessionHas('success');
        Notification::assertSentOnDemand(PickupReady::class, fn ($n, $channels, AnonymousNotifiable $to) => ($to->routes['mail'] ?? null) === 'visitor@example.com');

        auth()->logout();
        $this->get($order->fresh()->guestUrl())->assertOk()->assertSee($this->part($order, $this->pickupShop)->pickup_code);
    }

    public function test_the_api_always_delivers(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->pickupShop, 100, 20), 1]]);
        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/orders', [
            'shipping_address' => 'H. Blue Villa', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu', 'shipping_country' => 'Maldives',
            'shipping_phone' => '7771234', 'payment_method' => 'bml', 'fulfilment' => [$this->pickupShop->id => 'pickup'],
        ])->assertCreated();

        $order = Order::sole();
        $this->assertEquals(25 + 20, $order->shipping_amount);
        $this->assertSame('greater_male', $order->delivery_zone);
        $this->assertSame('deliver', $this->part($order, $this->pickupShop)->delivery_method);
    }

    public function test_cancelling_a_ready_pickup_order_still_works(): void
    {
        $order = $this->paidPickupOrder();
        $this->actingAs($this->pickupShop)->post(route('seller.orders.pickup.ready', $order));
        $part = $this->part($order, $this->pickupShop);

        // Nothing has left the shop, so the order can still be cancelled (and refunded) as a whole
        $this->actingAs($this->admin())->post(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('due', $order->fresh()->refund_status);
        $this->assertSame('not_ready', app(\App\Services\FulfilmentService::class)->confirmPickup($part->fresh(), $part->pickup_code));
        $this->assertSame('cancelled', $part->fresh()->status);
    }
}
