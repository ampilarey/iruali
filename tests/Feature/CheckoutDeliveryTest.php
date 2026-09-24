<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
    }

    protected function cartWorth(float $price): void
    {
        $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $price]);
    }

    protected function placeOrder(array $overrides = [])
    {
        return $this->actingAs($this->customer)->post('/orders', array_merge([
            'shipping_address' => 'M. Blue House',
            'shipping_city' => 'Hithadhoo',
            'shipping_state' => 'Addu',
            'shipping_zip' => '19020',
            'shipping_country' => 'Maldives',
            'payment_method' => 'cod',
            'agree_terms' => '1',
        ], $overrides));
    }

    public function test_checkout_page_shows_delivery_areas_and_posts_to_orders(): void
    {
        $this->cartWorth(200);

        $this->actingAs($this->customer)->get('/checkout')
            ->assertOk()
            ->assertSee(route('orders.store'), false)
            ->assertSee('Greater Malé')
            ->assertSee('Other islands')
            ->assertSee('Cash on delivery')
            ->assertSee('name="agree_terms"', false);
    }

    public function test_island_delivery_fee_is_added_to_the_total(): void
    {
        $this->cartWorth(200);

        $this->placeOrder(['delivery_zone' => 'islands'])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('islands', $order->delivery_zone);
        $this->assertEquals(75, $order->shipping_amount);
        $this->assertEquals(275, $order->total_amount);
        $this->assertSame('cod', $order->payment_method);
    }

    public function test_greater_male_rate_and_zone_guessed_from_island(): void
    {
        $this->cartWorth(200);

        $this->placeOrder(['shipping_city' => 'Hulhumalé'])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('greater_male', $order->delivery_zone);
        $this->assertEquals(25, $order->shipping_amount);
    }

    public function test_delivery_is_free_over_the_threshold(): void
    {
        $this->cartWorth(1200);

        $this->placeOrder(['delivery_zone' => 'islands'])->assertRedirect();

        $this->assertEquals(0, Order::firstOrFail()->shipping_amount);
        $this->assertEquals(1200, Order::firstOrFail()->total_amount);
    }

    public function test_fees_come_from_settings(): void
    {
        Setting::set(['delivery_fee_islands' => 60, 'free_delivery_over' => 0]);
        $this->cartWorth(1200);

        $this->placeOrder(['delivery_zone' => 'islands'])->assertRedirect();

        $this->assertEquals(60, Order::firstOrFail()->shipping_amount);
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $this->cartWorth(200);

        $this->placeOrder(['payment_method' => 'paypal'])->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }
}
