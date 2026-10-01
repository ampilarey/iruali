<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerStockPageTest extends TestCase
{
    use RefreshDatabase;

    protected function seller(): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    public function test_stock_page_lists_products_and_variants_and_filters_low_stock(): void
    {
        $seller = $this->seller();
        $plain = Product::factory()->create(['seller_id' => $seller->id, 'name' => ['en' => 'Plain Tee'], 'stock_quantity' => 40, 'reorder_point' => 5]);
        $lowPlain = Product::factory()->create(['seller_id' => $seller->id, 'name' => ['en' => 'Scarce Cap'], 'stock_quantity' => 2, 'reorder_point' => 5]);
        $hoodie = Product::factory()->create(['seller_id' => $seller->id, 'name' => ['en' => 'Hoodie'], 'has_variants' => true, 'reorder_point' => 5]);
        ProductVariant::factory()->for($hoodie)->attributes(['Size' => 'M'])->stock(30)->create(['sku' => 'HOOD-M']);
        ProductVariant::factory()->for($hoodie)->attributes(['Size' => 'L'])->stock(1)->create(['sku' => 'HOOD-L']);
        Product::factory()->create(['name' => ['en' => 'Someone Elses']]);

        $this->actingAs($seller)->get('/seller/stock')
            ->assertOk()
            ->assertSee('Plain Tee')->assertSee('Scarce Cap')->assertSee('Hoodie')
            ->assertSee('HOOD-M')->assertSee('HOOD-L')
            ->assertSee('name="variants['.$hoodie->variants->firstWhere('sku', 'HOOD-L')->id.']"', false)
            ->assertSee('name="products['.$plain->id.']"', false)
            ->assertDontSee('name="products['.$hoodie->id.']"', false)
            ->assertDontSee('Someone Elses')
            ->assertSee('2 products low on stock');

        $this->get('/seller/stock?low=1')
            ->assertOk()
            ->assertSee('Scarce Cap')->assertSee('Hoodie')
            ->assertDontSee('Plain Tee');
    }

    public function test_stock_levels_are_saved_inline_and_only_for_the_shops_own_rows(): void
    {
        $seller = $this->seller();
        $plain = Product::factory()->create(['seller_id' => $seller->id, 'stock_quantity' => 4]);
        $hoodie = Product::factory()->create(['seller_id' => $seller->id, 'has_variants' => true]);
        $large = ProductVariant::factory()->for($hoodie)->attributes(['Size' => 'L'])->stock(1)->create();
        $foreign = ProductVariant::factory()->stock(1)->create();

        $this->actingAs($seller)->put('/seller/stock', [
            'products' => [$plain->id => 12],
            'variants' => [$large->id => 7],
        ])->assertRedirect(route('seller.stock'))->assertSessionHas('success', '2 stock levels updated.');

        $this->assertSame(12, $plain->fresh()->stock_quantity);
        $this->assertSame(7, $large->fresh()->stock_quantity);
        $this->assertSame(7, (int) $hoodie->fresh()->stock_quantity);

        $this->put('/seller/stock', ['variants' => [$large->id => 9, $foreign->id => 9]])->assertForbidden();
        $this->assertSame(7, $large->fresh()->stock_quantity);
        $this->assertSame(1, $foreign->fresh()->stock_quantity);

        $this->actingAs(User::factory()->create())->get('/seller/stock')->assertForbidden();
    }
}
