<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\BackInStock;
use App\Notifications\NowOnSale;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\ActsAsShopStaff;
use Tests\TestCase;

/**
 * "Coming soon" products (listed, not on sale until their launch day or until the shop switches it
 * off, with "email me when it goes on sale"), and the pre-order additions: a last day for taking
 * pre-orders, the label on cards, the listing filters and the home page rows.
 */
class ComingSoonTest extends TestCase
{
    use ActsAsShopStaff, RefreshDatabase;

    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        $this->shop = $this->staffedShop('Reef Goods', ['onboarding_completed_at' => now()]);
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function product(array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'seller_id' => $this->shop->id, 'category_id' => $this->category->id, 'name' => ['en' => 'Reef Lamp'],
            'description' => ['en' => 'A lamp.'], 'sku' => 'LAMP-'.uniqid(), 'price' => 300, 'compare_price' => null,
            'stock_quantity' => 10, 'is_active' => true, 'approved_at' => now()->subWeek(),
        ], array_diff_key($attributes, array_flip(['coming_soon', 'available_on', 'preorder_enabled', 'preorder_ship_date', 'preorder_limit', 'preorder_closes_on']))));
        $product->forceFill(array_intersect_key($attributes, array_flip(['coming_soon', 'available_on', 'preorder_enabled', 'preorder_ship_date', 'preorder_limit', 'preorder_closes_on'])))->save();

        return $product->fresh();
    }

    protected function cartWith(User $customer, Product $product): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $product->price]);

        return $cart;
    }

    public function test_a_coming_soon_product_is_shown_but_cannot_be_bought(): void
    {
        $lamp = $this->product(['name' => ['en' => 'Moon Lamp'], 'coming_soon' => true, 'available_on' => '2026-11-01']);
        $this->assertTrue($lamp->isComingSoon());

        $this->get(route('products.show', $lamp))->assertOk()
            ->assertSee('data-coming-soon', false)
            ->assertSee('Coming soon: on sale from 1 Nov 2026')
            ->assertSee('Email me when it goes on sale')
            // Delivery counts from the launch day (1–2 days to Greater Malé after the shop's 1 day)
            ->assertSee('Arrives 3–4 Nov')
            ->assertDontSee('id="buy-form"', false);

        // Cards say so, and offer the page instead of the cart
        $this->get(route('shop'))->assertOk()->assertSee('Moon Lamp')->assertSee('Coming 1 Nov')->assertSee('data-coming-soon-details', false);

        // Adding it is refused, and an old cart line can't be ordered
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $lamp->id, 'quantity' => 1])->assertSessionHas('notification.type', 'error');
        $this->assertSame(0, CartItem::count());

        $cart = $this->cartWith($customer, $lamp);
        $result = app(OrderService::class)->createOrderFromCart($customer, [
            'shipping_address' => 'H. Blue', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu', 'shipping_zip' => '',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml',
        ], $cart);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('is coming soon: it goes on sale on 1 Nov 2026', $result['message']);
    }

    public function test_the_shop_switches_it_on_and_off_at_once_without_a_review(): void
    {
        $lamp = $this->product();
        $form = fn (array $changes) => array_merge([
            'name_en' => 'Reef Lamp', 'description_en' => 'A lamp.', 'sku' => $lamp->sku, 'category_id' => $this->category->id,
            'price' => 300, 'stock_quantity' => 10, 'has_variants' => 0, 'brand' => $lamp->brand, 'weight' => $lamp->weight,
            'compare_price' => $lamp->compare_price, 'reorder_point' => $lamp->reorder_point, 'coming_soon_form' => 1,
        ], $changes);

        $this->actingAs($this->shop)->get(route('seller.products.edit', $lamp))->assertOk()->assertSee('data-coming-soon-fieldset', false);

        $this->put(route('seller.products.update', $lamp), $form(['coming_soon' => 1, 'available_on' => '2026-10-19']))->assertSessionHasErrors('available_on');
        $this->put(route('seller.products.update', $lamp), $form(['coming_soon' => 1, 'available_on' => '2026-12-01']))->assertSessionHasNoErrors();

        $lamp->refresh();
        $this->assertTrue($lamp->isComingSoon());
        $this->assertSame('2026-12-01', $lamp->available_on->toDateString());
        $this->assertSame(0, ProductChangeRequest::count(), 'coming soon applies at once, like stock');

        // Someone asks; switching it off puts it on sale and emails them
        $this->post(route('stock-alerts.store', $lamp), ['email' => 'Fan@Example.com'])->assertRedirect();
        $this->assertTrue(StockAlert::sole()->for_launch);

        $this->actingAs($this->shop)->put(route('seller.products.update', $lamp), $form(['coming_soon' => 0]))->assertSessionHasNoErrors();
        $lamp->refresh();
        $this->assertFalse($lamp->isComingSoon());
        $this->assertNull($lamp->available_on);
        Notification::assertSentOnDemand(NowOnSale::class, fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'fan@example.com');
        $this->assertNotNull(StockAlert::sole()->notified_at);
    }

    public function test_it_goes_on_sale_by_itself_on_its_launch_day(): void
    {
        $lamp = $this->product(['coming_soon' => true, 'available_on' => '2026-10-22']);
        $undated = $this->product(['coming_soon' => true]);
        StockAlert::create(['product_id' => $lamp->id, 'email' => 'fan@example.com', 'locale' => 'en', 'for_launch' => true]);

        $this->artisan('products:launch')->expectsOutputToContain('0 product(s) went on sale.')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo(Carbon::parse('2026-10-22 00:05'));
        $this->assertFalse($lamp->fresh()->isComingSoon(), 'on sale from the morning of its day');
        $this->artisan('products:launch')->expectsOutputToContain('1 product(s) went on sale.')->assertSuccessful();

        $this->assertFalse((bool) $lamp->fresh()->coming_soon);
        $this->assertTrue($undated->fresh()->isComingSoon(), 'no date: until the shop switches it off');
        Notification::assertSentOnDemandTimes(NowOnSale::class, 1);
    }

    public function test_launching_with_no_stock_waits_for_the_stock_to_email(): void
    {
        $lamp = $this->product(['coming_soon' => true, 'stock_quantity' => 0]);
        StockAlert::create(['product_id' => $lamp->id, 'email' => 'fan@example.com', 'locale' => 'en', 'for_launch' => true]);
        StockAlert::create(['product_id' => $lamp->id, 'email' => 'old@example.com', 'locale' => 'en']);

        $lamp->forceFill(['coming_soon' => false])->save();
        Notification::assertNothingSent();

        // Stock arrives: the launch fan hears it is on sale, the other one that it is back
        $lamp->update(['stock_quantity' => 4]);
        Notification::assertSentOnDemandTimes(NowOnSale::class, 1);
        Notification::assertSentOnDemandTimes(BackInStock::class, 1);
    }

    public function test_no_back_in_stock_email_and_no_pre_orders_while_coming_soon(): void
    {
        $lamp = $this->product(['coming_soon' => true, 'stock_quantity' => 0, 'preorder_enabled' => true, 'preorder_ship_date' => '2026-11-10', 'preorder_limit' => 5]);
        StockAlert::create(['product_id' => $lamp->id, 'email' => 'old@example.com', 'locale' => 'en']);

        $lamp->update(['stock_quantity' => 3]);
        Notification::assertNothingSent();

        $lamp->update(['stock_quantity' => 0]);
        $this->get(route('products.show', $lamp))->assertOk()->assertDontSee('data-preorder-notice', false)->assertSee('data-coming-soon', false);
    }

    public function test_pre_orders_stop_after_their_last_day(): void
    {
        $lamp = $this->product(['stock_quantity' => 0, 'preorder_enabled' => true, 'preorder_ship_date' => '2026-11-10', 'preorder_limit' => 5, 'preorder_closes_on' => '2026-10-25']);

        $this->get(route('products.show', $lamp))->assertOk()->assertSee('data-preorder-notice', false)->assertSee('Pre-order by 25 Oct');
        $this->get(route('shop'))->assertOk()->assertSee('Pre-order · ships around 10 Nov')->assertSee('data-preorder-details', false);

        $this->travelTo(Carbon::parse('2026-10-26 09:00'));
        $this->get(route('products.show', $lamp))->assertOk()->assertDontSee('data-preorder-notice', false)->assertSee('Out of stock');

        // The shop's form checks the day sits between today and the ship date
        $form = [
            'name_en' => 'Reef Lamp', 'description_en' => 'A lamp.', 'sku' => $lamp->sku, 'category_id' => $this->category->id,
            'price' => 300, 'stock_quantity' => 0, 'has_variants' => 0, 'preorder_form' => 1, 'preorder_enabled' => 1,
            'preorder_ship_date' => '2026-11-10', 'preorder_limit' => 5,
        ];
        $this->actingAs($this->shop)->put(route('seller.products.update', $lamp), $form + ['preorder_closes_on' => '2026-11-20'])->assertSessionHasErrors('preorder_closes_on');
        $this->put(route('seller.products.update', $lamp), $form + ['preorder_closes_on' => '2026-10-20'])->assertSessionHasErrors('preorder_closes_on');
    }

    public function test_filters_and_home_rows(): void
    {
        $this->product(['name' => ['en' => 'Ready Kettle']]);
        $this->product(['name' => ['en' => 'Ship Fridge'], 'stock_quantity' => 0, 'preorder_enabled' => true, 'preorder_ship_date' => '2026-11-10', 'preorder_limit' => 5]);
        $this->product(['name' => ['en' => 'Moon Lamp'], 'coming_soon' => true, 'available_on' => '2026-11-01']);

        $this->get(route('shop', ['preorder' => 1]))->assertOk()->assertSee('Ship Fridge')->assertDontSee('Ready Kettle')->assertDontSee('Moon Lamp');
        $this->get(route('shop', ['coming_soon' => 1]))->assertOk()->assertSee('Moon Lamp')->assertDontSee('Ready Kettle')->assertDontSee('Ship Fridge');
        $this->get(route('shop', ['in_stock' => 1]))->assertOk()->assertSee('Ready Kettle')->assertDontSee('Moon Lamp');

        $this->get('/')->assertOk()->assertSee('Pre-order now')->assertSee(route('shop', ['preorder' => 1]), false)
            ->assertSee('Coming soon')->assertSee(route('shop', ['coming_soon' => 1]), false);
    }
}
