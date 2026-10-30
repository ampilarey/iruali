<?php

namespace Tests\Feature;

use App\Models\Island;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SellerOrderShipped;
use App\Services\PaymentService;
use App\Support\BoatDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * "Send with a boat" (App\Support\BoatDelivery): the shop takes the parcel to a Malé jetty and hands it to
 * the boat the customer named; it is charged as a Greater Malé delivery.
 */
class BoatDeliveryTest extends TestCase
{
    use DeliveryFixtures;
    use RefreshDatabase;

    protected Island $male;

    protected User $customer;

    protected User $maleShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Indian/Maldives')); // a Monday morning
        Setting::set(['delivery_fee_greater_male' => 25, 'delivery_fee_islands' => 75, 'free_delivery_over' => 1000]);
        $this->male = $this->island('Malé', 'Kaafu');
        $this->customer = User::factory()->create(['phone' => '7770000']);
        $this->maleShop = $this->shop('Coral Corner');
        $this->maleShop->forceFill(['home_island_id' => $this->male->id])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function boat(array $overrides = []): array
    {
        return array_merge([
            'address_id' => 'boat', 'boat_name' => 'Express 3', 'boat_phone' => '799 1234', 'boat_jetty' => 'male_5',
            'boat_departs_at' => '2026-11-02T15:00', 'boat_island' => 'Hithadhoo, Seenu', 'boat_notes' => 'Give it to Ahmed',
            // The address fields are not sent for a boat (they are disabled on the page)
            'shipping_address' => '', 'shipping_city' => '', 'shipping_state' => '',
        ], $overrides);
    }

    public function test_a_boat_order_is_a_greater_male_delivery_with_the_boat_on_it(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->maleShop, 200), 1]]);
        $this->actingAs($this->customer)->get(route('checkout'))->assertOk()
            ->assertSee('Send with a boat (from Malé)')->assertSee('data-boat-panel', false);

        $this->placeOrder($this->customer, $this->boat())->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::latest('id')->firstOrFail();

        $this->assertTrue($order->isByBoat());
        $this->assertSame('greater_male', $order->delivery_zone);
        $this->assertEquals(25, $order->shipping_amount); // the Greater Malé fee, not the islands one
        $this->assertSame('Malé', $order->shipping_city);
        $this->assertStringContainsString('Express 3', $order->shipping_address);
        $this->assertSame(['name' => 'Express 3', 'phone' => '7991234', 'jetty' => 'male_5', 'jetty_other' => null,
            'departs_at' => '2026-11-02 15:00', 'island' => 'Hithadhoo, Seenu', 'notes' => 'Give it to Ahmed'], $order->boat);

        // The customer, the shop and admins see the boat
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('data-order-boat', false)->assertSee('Malé Jetty No. 5');
        $this->actingAs($this->maleShop)->get(route('seller.orders.show', $order))->assertSee('Express 3')->assertSee('7991234');

        // When the shop sends it, the text says which boat
        app(PaymentService::class)->confirm($order);
        $part = SellerOrder::where('order_id', $order->id)->firstOrFail();
        $sms = (string) (new SellerOrderShipped($part))->toSms($this->customer);
        $this->assertStringContainsString('Express 3', $sms);
        $this->assertStringContainsString('7991234', $sms);

        // Next time the form is filled in from this boat
        $this->assertSame('Express 3', BoatDelivery::lastFor($this->customer)['name']);
    }

    public function test_the_boat_must_leave_late_enough_and_have_its_details(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->maleShop, 200), 1]]);

        $this->placeOrder($this->customer, $this->boat(['boat_departs_at' => '2026-11-02T10:00']))->assertSessionHasErrors('boat_departs_at'); // in an hour
        $this->placeOrder($this->customer, $this->boat(['boat_departs_at' => '2026-12-30T10:00']))->assertSessionHasErrors('boat_departs_at'); // too far ahead
        $this->placeOrder($this->customer, $this->boat(['boat_name' => '', 'boat_phone' => 'abc']))->assertSessionHasErrors(['boat_name', 'boat_phone']);
        $this->placeOrder($this->customer, $this->boat(['boat_jetty' => 'other', 'boat_jetty_other' => '']))->assertSessionHasErrors('boat_jetty_other');

        // The shop is closed until Wednesday: Monday's boat is too early for it
        \App\Models\SellerDeliverySetting::where('seller_id', $this->maleShop->id)->update(['opening_hours' => json_encode(['3' => [['09:00', '17:00']]])]);
        $this->placeOrder($this->customer, $this->boat())->assertSessionHasErrors('boat_departs_at');
        $this->placeOrder($this->customer, $this->boat(['boat_departs_at' => '2026-11-04T14:00']))->assertSessionHasNoErrors();

    }

    public function test_only_when_every_shop_is_in_greater_male_and_it_is_switched_on(): void
    {
        $addu = $this->island('Hithadhoo', 'Seenu');
        $islandShop = $this->shop('Addu Goods');
        $islandShop->forceFill(['home_island_id' => $addu->id])->save();
        $this->cartFor($this->customer, [[$this->product($this->maleShop, 200), 1], [$this->product($islandShop, 100), 1]]);

        $this->actingAs($this->customer)->get(route('checkout'))->assertOk()->assertDontSee('data-boat-panel', false);
        $this->placeOrder($this->customer, $this->boat())->assertSessionHasErrors('address_id');

        // Admin → Delivery switches it off
        $this->cartFor($this->customer, [[$this->product($this->maleShop, 200), 1]]);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.delivery'))->assertOk()->assertSee('data-boat-delivery', false);
        Setting::set(['boat_delivery_enabled' => 0]);
        $this->assertFalse(BoatDelivery::enabled());
        $this->actingAs($this->customer)->get(route('checkout'))->assertOk()->assertDontSee('data-boat-panel', false);
    }
}
