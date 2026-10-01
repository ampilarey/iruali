<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function hoodie(): Product
    {
        $product = Product::factory()->create(['price' => 300, 'has_variants' => true, 'is_active' => true]);
        ProductVariant::factory()->for($product)->attributes(['Size' => 'M', 'Colour' => 'Blue'])->stock(2)->create(['sku' => 'HOOD-M-BLUE']);
        ProductVariant::factory()->for($product)->attributes(['Size' => 'L', 'Colour' => 'Blue'])->stock(5)->priced(350)->create(['sku' => 'HOOD-L-BLUE']);
        ProductVariant::factory()->for($product)->attributes(['Size' => 'L', 'Colour' => 'Red'])->outOfStock()->create(['sku' => 'HOOD-L-RED']);

        return $product->fresh();
    }

    public function test_product_page_shows_the_variant_picker_and_from_price(): void
    {
        $product = $this->hoodie();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('data-variant-picker', false)
            ->assertSee('data-option-group="Size"', false)
            ->assertSee('data-option-group="Colour"', false)
            ->assertSee('Size: L / Colour: Blue')
            ->assertSee('name="product_variant_id"', false)
            ->assertSee('From')
            ->assertSee('MVR 300.00');

        $this->assertSame(7, (int) $product->stock_quantity);
    }

    public function test_adding_to_cart_requires_a_variant_and_respects_its_stock(): void
    {
        $product = $this->hoodie();
        $medium = $product->variants->firstWhere('sku', 'HOOD-M-BLUE');
        $large = $product->variants->firstWhere('sku', 'HOOD-L-BLUE');
        $red = $product->variants->firstWhere('sku', 'HOOD-L-RED');

        $this->from(route('products.show', $product))->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect(route('products.show', $product));
        $this->assertDatabaseCount('cart_items', 0);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 3, 'product_variant_id' => $medium->id]);
        $this->assertDatabaseCount('cart_items', 0); // only 2 of the medium are in stock

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1, 'product_variant_id' => $red->id]);
        $this->assertDatabaseCount('cart_items', 0); // an out-of-stock variant cannot be added

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2, 'product_variant_id' => $large->id])->assertRedirect(route('cart'));
        $line = CartItem::firstOrFail();
        $this->assertSame($large->id, $line->product_variant_id);
        $this->assertSame(350.0, (float) $line->price);
        $this->assertSame(350.0, $line->unit_price);

        $this->get(route('cart'))->assertOk()->assertSee('Size: L / Colour: Blue')->assertSee('HOOD-L-BLUE')->assertSee('MVR 700.00');

        // Quantity updates cap at the variant's stock, not the product total
        $this->put(route('cart.update', $line), ['quantity' => 9])->assertRedirect(route('cart'));
        $this->assertSame(5, $line->fresh()->quantity);
    }

    public function test_guest_cart_merge_caps_at_variant_stock(): void
    {
        $product = $this->hoodie();
        $medium = $product->variants->firstWhere('sku', 'HOOD-M-BLUE');
        $user = User::factory()->create();

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2, 'product_variant_id' => $medium->id]);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $medium->id, 'quantity' => 2]);

        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $medium->id, 'quantity' => 1]);

        $this->actingAs($user);
        app(CartService::class)->mergeGuestCart($user);

        $this->assertSame(2, $cart->items()->where('product_variant_id', $medium->id)->firstOrFail()->quantity);
    }

    public function test_product_card_sends_shoppers_to_choose_options(): void
    {
        $product = $this->hoodie();
        $html = view('components.product-card', ['product' => $product->load('mainImage', 'seller')])->render();

        $this->assertStringContainsString('Choose options', $html);
        $this->assertStringNotContainsString(route('cart.add'), $html);
        $this->assertStringContainsString('From', $html);
    }

    public function test_back_in_stock_alert_can_target_a_variant(): void
    {
        $product = $this->hoodie();
        $red = $product->variants->firstWhere('sku', 'HOOD-L-RED');

        $this->get(route('products.show', $product))->assertOk()->assertSee('data-alert-variant', false);

        $this->post(route('stock-alerts.store', $product), ['email' => 'Fan@example.com', 'product_variant_id' => $red->id])->assertRedirect();
        $this->assertDatabaseHas('stock_alerts', ['product_id' => $product->id, 'product_variant_id' => $red->id, 'email' => 'fan@example.com']);

        $other = Product::factory()->create(['is_active' => true, 'has_variants' => true]);
        $this->post(route('stock-alerts.store', $other), ['email' => 'fan@example.com', 'product_variant_id' => $red->id])->assertNotFound();
        $this->assertSame(1, StockAlert::count());
    }
}
