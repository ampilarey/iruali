<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Send as a gift: the receiver's name and phone, a gift message, and a packing slip that can
 * leave the prices out. The customer's receipt is unchanged.
 */
class GiftOrdersTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected User $customer;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();
        Setting::set(['delivery_fee_greater_male' => 25, 'free_delivery_over' => 1000]);
        $this->customer = User::factory()->create(['name' => 'Aisha Ahmed']);
        $this->seller = $this->shop('Reef Crafts');
    }

    protected function giftOrder(array $gift = []): Order
    {
        $this->cartFor($this->customer, [[$this->product($this->seller, 349.5, 0, ['name' => ['en' => 'Coral lamp']]), 2]]);
        $this->placeOrder($this->customer, array_merge([
            'is_gift' => '1', 'gift_receiver_name' => 'Mariyam Ali', 'gift_receiver_phone' => '999 8877',
            'gift_message' => "Happy birthday!\nLove, Aisha", 'gift_hide_prices' => '1',
        ], $gift))->assertRedirect();

        return Order::latest('id')->first();
    }

    public function test_checkout_has_a_gift_section(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->seller, 100), 1]]);

        $this->actingAs($this->customer)->get('/checkout')->assertOk()
            ->assertSee('This is a gift')
            ->assertSee('name="is_gift"', false)
            ->assertSee('name="gift_receiver_name"', false)
            ->assertSee('name="gift_receiver_phone"', false)
            ->assertSee('maxlength="200"', false)
            ->assertSee('Hide prices on the packing slip');
    }

    public function test_gift_details_are_validated(): void
    {
        $this->cartFor($this->customer, [[$this->product($this->seller, 100), 1]]);

        $this->placeOrder($this->customer, ['is_gift' => '1'])->assertSessionHasErrors(['gift_receiver_name', 'gift_receiver_phone']);
        $this->placeOrder($this->customer, ['is_gift' => '1', 'gift_receiver_name' => 'Mariyam', 'gift_receiver_phone' => 'call me'])->assertSessionHasErrors('gift_receiver_phone');
        $this->placeOrder($this->customer, ['is_gift' => '1', 'gift_receiver_name' => 'Mariyam', 'gift_receiver_phone' => '9998877', 'gift_message' => str_repeat('a', 201)])
            ->assertSessionHasErrors('gift_message');
        $this->assertSame(0, Order::count());

        // Gift fields left over without the box ticked are ignored
        $this->placeOrder($this->customer, ['gift_receiver_name' => 'Someone', 'gift_message' => 'Hi'])->assertRedirect();
        $order = Order::sole();
        $this->assertFalse($order->isGift());
        $this->assertNull($order->gift_receiver_name);
        $this->assertNull($order->gift_message);
    }

    public function test_a_gift_order_keeps_the_receiver_and_message(): void
    {
        $order = $this->giftOrder();

        $this->assertTrue($order->isGift());
        $this->assertSame('Mariyam Ali', $order->gift_receiver_name);
        $this->assertSame('9998877', $order->gift_receiver_phone);
        $this->assertSame("Happy birthday!\nLove, Aisha", $order->gift_message);
        $this->assertTrue($order->gift_hide_prices);
        $this->assertSame('H. Blue Villa', $order->shipping_address, 'delivered to the address as entered');
        $this->assertEquals(699 + 25, $order->total_amount);

        // "Hide prices" is on unless the box was unticked
        $this->assertTrue($this->giftOrder(['gift_hide_prices' => null])->gift_hide_prices);
        $this->assertFalse($this->giftOrder(['gift_hide_prices' => '0'])->gift_hide_prices);
    }

    public function test_the_shop_and_admin_see_the_gift_details(): void
    {
        $order = $this->giftOrder();

        $this->actingAs($this->seller)->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('This is a gift')
            ->assertSee('For Mariyam Ali')
            ->assertSee('9998877')
            ->assertSee('Love, Aisha')
            ->assertSee('Pack it without prices')
            ->assertSee(route('seller.orders.packing-slip', $order), false);

        $this->actingAs($this->seller)->get(route('seller.orders'))->assertOk()->assertSee('Gift');

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('This is a gift')
            ->assertSee('For Mariyam Ali')
            ->assertSee('Prices hidden on the packing slip.');

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertSee('For Mariyam Ali');
    }

    public function test_the_gift_packing_slip_hides_prices_and_carries_the_message(): void
    {
        $order = $this->giftOrder();

        $slip = $this->actingAs($this->seller)->get(route('seller.orders.packing-slip', $order))->assertOk();
        $slip->assertSee('Packing slip')
            ->assertSee($order->order_number)
            ->assertSee('Coral lamp')
            ->assertSee('Mariyam Ali')
            ->assertSee('9998877')
            ->assertSee('H. Blue Villa')
            ->assertSee('A gift for you')
            ->assertSee('Love, Aisha')
            ->assertDontSee('349.50')
            ->assertDontSee('699.00')
            ->assertDontSee('MVR');

        // The customer's receipt is unchanged: prices and all
        $this->actingAs($this->customer)->get(route('orders.receipt', $order))->assertOk()
            ->assertSee('349.50')
            ->assertSee('724.00')
            ->assertDontSee('A gift for you');
    }

    public function test_slips_show_prices_unless_a_gift_hides_them(): void
    {
        $shown = $this->giftOrder(['gift_hide_prices' => '0']);
        $this->actingAs($this->seller)->get(route('seller.orders.packing-slip', $shown))->assertOk()
            ->assertSee('A gift for you')
            ->assertSee('MVR 349.50')
            ->assertSee('MVR 699.00');

        $this->cartFor($this->customer, [[$this->product($this->seller, 80, 0, ['name' => ['en' => 'Tote bag']]), 1]]);
        $this->placeOrder($this->customer)->assertRedirect();
        $plain = Order::latest('id')->first();
        $this->actingAs($this->seller)->get(route('seller.orders.packing-slip', $plain))->assertOk()
            ->assertSee('Tote bag')
            ->assertSee('Aisha Ahmed')
            ->assertSee('MVR 80.00')
            ->assertDontSee('A gift for you');

        // Admins can print any part's slip; other shops can't
        $part = $plain->sellerOrders()->sole();
        $this->actingAs($this->admin())->get(route('admin.orders.parts.packing-slip', [$plain, $part]))->assertOk()->assertSee('Tote bag');
        $this->actingAs($this->shop('Other Shop'))->get(route('seller.orders.packing-slip', $plain))->assertNotFound();
        $this->actingAs($this->customer)->get(route('seller.orders.packing-slip', $plain))->assertForbidden();
    }
}
