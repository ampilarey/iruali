<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Bulky items: a per-item extra delivery charge set by the shop, added for delivered items and
 * never waived by free delivery.
 */
class DeliverySurchargeTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();
        Setting::set(['delivery_fee_greater_male' => 25, 'delivery_fee_islands' => 75, 'free_delivery_over' => 1000]);
    }

    public function test_shops_set_the_charge_on_the_product_form(): void
    {
        $seller = $this->shop('Big Things');
        $category = Category::factory()->create(['status' => 'active']);

        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()
            ->assertSee('Extra delivery charge per item (MVR)')
            ->assertSee('name="delivery_surcharge"', false);

        $fields = ['name_en' => 'Sofa', 'sku' => 'SOFA-1', 'category_id' => $category->id, 'price' => 4500, 'stock_quantity' => 3];
        $this->actingAs($seller)->post(route('seller.products.store'), $fields + ['delivery_surcharge' => '-1'])->assertSessionHasErrors('delivery_surcharge');
        $this->actingAs($seller)->post(route('seller.products.store'), $fields + ['delivery_surcharge' => '150.50'])->assertRedirect(route('seller.products.index'));

        $product = Product::where('sku', 'SOFA-1')->sole();
        $this->assertEquals(150.5, $product->delivery_surcharge);

        $this->actingAs($seller)->get(route('seller.products.edit', $product))->assertOk()->assertSee('value="150.50"', false);
        $this->actingAs($seller)->put(route('seller.products.update', $product), $fields + ['delivery_surcharge' => '0'])->assertRedirect();
        $this->assertEquals(0, $product->fresh()->delivery_surcharge);

        // Products default to no charge
        $this->assertEquals(0, $this->product($seller, 100)->fresh()->delivery_surcharge);
    }

    public function test_the_charge_shows_on_the_product_page_and_in_the_cart(): void
    {
        $customer = User::factory()->create();
        $sofa = $this->product($this->shop('Big Things'), 4500, 150);
        $mug = $this->product($this->shop('Small Things'), 50);

        $this->get(route('products.show', $sofa))->assertOk()->assertSee('Plus MVR 150.00 delivery per item (bulky item), even with free delivery.');
        $this->get(route('products.show', $mug))->assertOk()->assertDontSee('delivery per item (bulky item)');

        $this->cartFor($customer, [[$sofa, 2], [$mug, 1]]);
        $this->actingAs($customer)->get('/cart')->assertOk()
            ->assertSee('Bulky item: MVR 150.00 extra delivery per item, not covered by free delivery.');
        $this->actingAs($customer)->get('/checkout')->assertOk()
            ->assertSee('Bulky items: MVR 300.00 extra delivery charge when delivered.');
    }

    public function test_the_charge_is_added_per_delivered_item(): void
    {
        $customer = User::factory()->create();
        $sofa = $this->product($this->shop('Big Things'), 300, 50);
        $mug = $this->product($this->shop('Small Things'), 100);
        $this->cartFor($customer, [[$sofa, 2], [$mug, 1]]);

        $this->placeOrder($customer)->assertRedirect();

        $order = Order::sole();
        $this->assertEquals(25 + 2 * 50, $order->shipping_amount);
        $this->assertEquals(100, $order->delivery_surcharge);
        $this->assertEquals(700 + 125, $order->total_amount);
        $this->assertEquals(100, $order->sellerOrders()->where('seller_id', $sofa->seller_id)->value('delivery_surcharge'));
        $this->assertEquals(0, $order->sellerOrders()->where('seller_id', $mug->seller_id)->value('delivery_surcharge'));
        // The shop's earnings are its items only, as before
        $this->assertEquals(600, $order->sellerOrders()->where('seller_id', $sofa->seller_id)->value('subtotal'));
    }

    public function test_free_delivery_waives_the_fee_but_not_the_charge(): void
    {
        $customer = User::factory()->create();
        $sofa = $this->product($this->shop('Big Things'), 1200, 80);
        $this->cartFor($customer, [[$sofa, 1]]);

        $this->placeOrder($customer, ['shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu', 'delivery_zone' => 'islands'])->assertRedirect();

        $order = Order::sole();
        $this->assertEquals(80, $order->shipping_amount);
        $this->assertEquals(80, $order->delivery_surcharge);
        $this->assertEquals(1280, $order->total_amount);
        // BML is asked for exactly the order total
        $this->assertSame(1280.0, $order->cardAmount());
    }
}
