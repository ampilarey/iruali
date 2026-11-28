<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 2, batch 5: what customers see (cart prices, refused orders, the buy box, "bought together"),
 * Dhivehi pages and messages, and the Seller Centre and admin kept in English.
 */
class PagesAndDhivehiTest extends TestCase
{
    use RefreshDatabase;

    protected function seller(string $name = 'Island Crafts'): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    public function test_the_cart_shows_each_line_at_todays_price(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['seller_id' => $this->seller()->id, 'price' => 100, 'compare_price' => null, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 100]);

        // The shop raises it to 150 after it went in the cart
        $product->forceFill(['price' => 150])->save();
        $this->actingAs($customer)->get(route('cart'))->assertOk()->assertSee('MVR 300.00')->assertDontSee('MVR 200.00');
    }

    public function test_why_an_order_was_refused_stays_on_the_cart_page(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 100]);

        NotificationService::error('Sorry, only 0 of "Mug" are left (you asked for 1). Please change the quantity.');
        $this->actingAs($customer)->get(route('cart'))->assertOk()->assertSee('data-cart-error', false)->assertSee('Please change the quantity.');
    }

    public function test_the_buy_box_says_to_choose_an_option_and_bought_together_leaves_out_products_with_options(): void
    {
        $shop = $this->seller();
        $shirt = Product::factory()->create(['seller_id' => $shop->id, 'price' => 100, 'has_variants' => true, 'is_active' => true]);
        ProductVariant::factory()->for($shirt)->attributes(['Size' => 'M'])->stock(5)->create();
        ProductVariant::factory()->for($shirt)->attributes(['Size' => 'L'])->stock(5)->create();
        $this->get(route('products.show', $shirt->fresh()))->assertOk()->assertSee('data-variant-hint', false)->assertSee('Please choose an option first.');

        // A mug bought with a cap (no options) and with the shirt (options): only the cap is offered with it
        $mug = Product::factory()->create(['seller_id' => $shop->id, 'price' => 50, 'stock_quantity' => 9, 'is_active' => true]);
        $cap = Product::factory()->create(['seller_id' => $shop->id, 'price' => 70, 'stock_quantity' => 9, 'is_active' => true]);
        foreach ([[$mug, $cap], [$mug, $shirt]] as $pair) {
            $order = Order::factory()->create();
            foreach ($pair as $p) {
                OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'quantity' => 1, 'price' => $p->price]);
            }
        }
        $page = $this->get(route('products.show', $mug))->assertOk()->assertSee('Frequently bought together');
        $this->assertStringContainsString('value="'.$cap->id.'" data-price', $page->getContent());
        $this->assertStringNotContainsString('value="'.$shirt->id.'" data-price', $page->getContent());

        // In Dhivehi the total's currency comes from the server, not parsed out of "ރ. 120.00"
        $this->withSession(['locale' => 'dv'])->get(route('products.show', $mug))->assertOk()->assertSee('data-prefix="ރ.', false);
    }

    public function test_the_seller_centre_and_admin_stay_in_english_for_someone_reading_the_shop_in_dhivehi(): void
    {
        $shop = $this->seller();
        $this->actingAs($shop)->withSession(['locale' => 'dv'])->get(route('seller.dashboard'))->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)->assertDontSee('ރ.', false);
        // ...and the shop itself is still in Dhivehi for them
        $this->get(route('home'))->assertSee('dir="rtl"', false);
        // The guides and the application form are written in both languages
        $this->get('/seller/help/listing-products')->assertSee('dir="rtl"', false);
    }

    public function test_sign_in_messages_are_in_dhivehi(): void
    {
        app()->setLocale('dv');
        foreach (['failed', 'invalid_otp', 'invalid_2fa_code', 'account_inactive', 'two_factor_authentication'] as $key) {
            $this->assertMatchesRegularExpression('/\p{Thaana}/u', __('auth.'.$key), $key);
        }
    }

    public function test_dhivehi_order_pages_have_dhivehi_titles_and_dates(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'bml', 'paid_at' => now()->setDate(2026, 11, 3)]);
        $this->actingAs($customer)->withSession(['locale' => 'dv'])->get(route('orders'))->assertOk()->assertSee('އަހަރެންގެ އޯޑަރުތައް')->assertDontSee('My Orders - iruali');
        $this->get(route('orders.show', $order))->assertOk()->assertDontSee('3 Nov 2026');
    }

    public function test_admin_sellers_shows_why_an_action_was_refused(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->withSession(['error' => 'This is an iruali team account.'])->get(route('admin.sellers'))->assertOk()->assertSee('This is an iruali team account.');
    }

    public function test_the_apps_cart_lines_are_at_todays_price_too(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['seller_id' => $this->seller()->id, 'price' => 100, 'compare_price' => null, 'stock_quantity' => 10, 'is_active' => true]);
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 100]);
        $product->forceFill(['price' => 150])->save();

        \Laravel\Sanctum\Sanctum::actingAs($customer, ['*']);
        $items = collect(json_decode($this->getJson('/api/v1/cart')->assertOk()->getContent(), true))->dot()->filter(fn ($v, $k) => str_ends_with((string) $k, 'items.0.subtotal'));
        $this->assertEquals(300, $items->first(), 'the line, not only the cart total');
    }
}
