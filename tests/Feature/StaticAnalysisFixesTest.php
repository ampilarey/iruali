<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bugs that static analysis (Larastan) pointed at, each pinned down by a test.
 */
class StaticAnalysisFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function orderFields(): array
    {
        return [
            'shipping_address' => 'Blue House', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'greater_male',
            'payment_method' => 'bml', 'agree_terms' => '1',
        ];
    }

    protected function cartWith(User $user, Product $product, int $quantity = 1, ?ProductVariant $variant = null): void
    {
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create([
            'cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id,
            'quantity' => $quantity, 'price' => $product->price,
        ]);
    }

    public function test_signed_in_checkout_explains_a_sold_out_item_instead_of_crashing(): void
    {
        $this->enableBml();
        $customer = User::factory()->create();
        $this->cartWith($customer, Product::factory()->create(['name' => ['en' => 'Reef Hat'], 'stock_quantity' => 1, 'is_active' => true]), 3);

        $this->actingAs($customer)->post('/orders', $this->orderFields())
            ->assertRedirect()
            ->assertSessionHasErrors(['cart' => 'Not enough stock for Reef Hat.']);
        $this->assertSame(0, Order::count());
    }

    public function test_signed_in_checkout_explains_an_item_that_is_no_longer_on_sale(): void
    {
        $this->enableBml();
        $customer = User::factory()->create();
        $this->cartWith($customer, Product::factory()->create(['stock_quantity' => 5, 'is_active' => false]));

        $this->actingAs($customer)->post('/orders', $this->orderFields())
            ->assertSessionHasErrors(['cart' => 'A product in your cart is no longer available.']);

        // A product deleted in bulk (no model events, so it is still in the cart)
        $other = User::factory()->create();
        $gone = Product::factory()->create(['stock_quantity' => 5, 'is_active' => true]);
        $this->cartWith($other, $gone);
        Product::whereKey($gone->id)->delete();

        $this->actingAs($other)->post('/orders', $this->orderFields())
            ->assertSessionHasErrors(['cart' => 'A product in your cart is no longer available.']);
        $this->assertSame(0, Order::count());
    }

    public function test_signed_in_checkout_checks_the_chosen_variants_own_stock(): void
    {
        $this->enableBml();
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => ['en' => 'Board Shorts'], 'has_variants' => true, 'is_active' => true]);
        $small = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 0]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 9]);
        $this->assertSame(9, $product->fresh()->stock_quantity, 'the product total counts the other size');
        $this->cartWith($customer, $product->fresh(), 1, $small);

        $this->actingAs($customer)->post('/orders', $this->orderFields())
            ->assertSessionHasErrors(['cart' => 'Not enough stock for Board Shorts.']);
    }

    public function test_a_product_added_while_working_in_dhivehi_gets_a_web_address_from_its_english_name(): void
    {
        app()->setLocale('dv');

        $named = Product::factory()->create(['name' => ['en' => 'Fish Curry Paste', 'dv' => 'ފިހުނު ރިހަ'], 'slug' => null]);
        $this->assertSame('fish-curry-paste', $named->slug);

        // Only a Dhivehi name: the SKU gives the address rather than leaving it empty
        $thaanaOnly = Product::factory()->create(['name' => ['dv' => 'ހަކުރު'], 'sku' => 'SUGAR-1KG', 'slug' => null]);
        $this->assertSame('sugar-1kg', $thaanaOnly->slug);
        $this->get('/products/sugar-1kg')->assertOk();
    }

    public function test_a_banned_account_cannot_sign_in_and_is_told_why(): void
    {
        $user = User::factory()->create(['email' => 'spam@example.com', 'password' => \Illuminate\Support\Facades\Hash::make('secret-pass-1')]);
        $this->assertFalse($user->isBanned());

        $user->ban('Repeated spam listings');
        $this->assertTrue($user->fresh()->isBanned());

        $this->post('/login', ['email' => 'spam@example.com', 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors(['email' => __('auth.account_banned', ['reason' => 'Repeated spam listings'])]);
        $this->assertGuest();

        $user->fresh()->unban();
        $this->post('/login', ['email' => 'spam@example.com', 'password' => 'secret-pass-1']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_the_api_reports_whether_a_category_is_active(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $hidden = Category::factory()->create(['status' => 'inactive']);

        $this->assertTrue((new \App\Http\Resources\CategoryResource($category))->resolve()['is_active']);
        $this->assertFalse((new \App\Http\Resources\CategoryResource($hidden))->resolve()['is_active']);
    }
}
