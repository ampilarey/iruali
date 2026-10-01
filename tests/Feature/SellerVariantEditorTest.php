<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerVariantEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function seller(): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function row(array $attributes, string $sku, array $extra = []): array
    {
        return array_merge([
            'attributes' => json_encode($attributes),
            'sku' => $sku,
            'price' => '',
            'stock_quantity' => 5,
            'low_stock_threshold' => '',
            'is_active' => 1,
        ], $extra);
    }

    public function test_seller_creates_a_product_with_generated_variants(): void
    {
        $seller = $this->seller();
        $category = Category::factory()->create();

        $this->actingAs($seller)->post('/seller/products', [
            'name_en' => 'Island Hoodie',
            'sku' => 'HOOD-1',
            'category_id' => $category->id,
            'price' => 300,
            'has_variants' => 1,
            'variants' => [
                $this->row(['Size' => 'M', 'Colour' => 'Blue'], 'HOOD-1-M-BLUE', ['stock_quantity' => 4]),
                $this->row(['Size' => 'L', 'Colour' => 'Blue'], 'HOOD-1-L-BLUE', ['stock_quantity' => 6, 'price' => 320, 'low_stock_threshold' => 2]),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index'));

        $product = Product::where('sku', 'HOOD-1')->firstOrFail();
        $this->assertTrue($product->has_variants);
        $this->assertSame(10, (int) $product->stock_quantity);
        $this->assertCount(2, $product->variants);

        $large = $product->variants->firstWhere('sku', 'HOOD-1-L-BLUE');
        $this->assertSame(['Size' => 'L', 'Colour' => 'Blue'], $large->attributes_list);
        $this->assertSame('L / Blue', $large->displayName());
        $this->assertSame(320.0, $large->effectivePrice());
        $this->assertSame(2, $large->low_stock_threshold);
        $this->assertSame(300.0, $product->variants->firstWhere('sku', 'HOOD-1-M-BLUE')->effectivePrice());
    }

    public function test_removed_variants_are_deleted_or_deactivated_when_ordered(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'has_variants' => true, 'price' => 100]);
        $kept = ProductVariant::factory()->for($product)->attributes(['Size' => 'S'])->stock(1)->create();
        $sold = ProductVariant::factory()->for($product)->attributes(['Size' => 'M'])->create();
        $unsold = ProductVariant::factory()->for($product)->attributes(['Size' => 'L'])->create();
        Order::factory()->create()->items()->create(['product_id' => $product->id, 'product_variant_id' => $sold->id, 'quantity' => 1, 'price' => 100]);

        $this->actingAs($seller)->put(route('seller.products.update', $product), [
            'name_en' => 'Renamed',
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'price' => 100,
            'has_variants' => 1,
            'variants' => [
                ['id' => $kept->id] + $this->row(['Size' => 'S'], $kept->sku, ['stock_quantity' => 9]),
                $this->row(['Size' => 'XL'], 'NEW-XL', ['stock_quantity' => 2]),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(9, $kept->fresh()->stock_quantity);
        $this->assertFalse($sold->fresh()->is_active, 'a variant with order history is deactivated, not deleted');
        $this->assertNull($unsold->fresh(), 'a variant without orders is deleted');
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'NEW-XL']);
        $this->assertSame(11, (int) $product->fresh()->stock_quantity);
    }

    public function test_variant_rows_are_validated(): void
    {
        $seller = $this->seller();
        $other = ProductVariant::factory()->create(['sku' => 'TAKEN-1']);
        $category = Category::factory()->create();

        $this->actingAs($seller)->from('/seller/products/create')->post('/seller/products', [
            'name_en' => 'Bad',
            'sku' => 'BAD-1',
            'category_id' => $category->id,
            'price' => 10,
            'has_variants' => 1,
            'variants' => [
                $this->row(['Size' => 'M'], 'TAKEN-1'),
                $this->row(['Size' => 'm'], 'BAD-1-M'),
                $this->row([], 'BAD-1-X'),
            ],
        ])->assertSessionHasErrors(['variants.0.sku', 'variants.1.attributes', 'variants.2.attributes']);

        // Turning variants on without any rows is refused; the plain stock field is required without them
        $this->actingAs($seller)->from('/seller/products/create')->post('/seller/products', [
            'name_en' => 'Bad', 'sku' => 'BAD-2', 'category_id' => $category->id, 'price' => 10, 'has_variants' => 1,
        ])->assertSessionHasErrors(['variants']);
        $this->actingAs($seller)->from('/seller/products/create')->post('/seller/products', [
            'name_en' => 'Bad', 'sku' => 'BAD-3', 'category_id' => $category->id, 'price' => 10,
        ])->assertSessionHasErrors(['stock_quantity']);

        $this->assertDatabaseMissing('products', ['sku' => 'BAD-1']);
    }

    public function test_another_shop_cannot_edit_variants_and_cannot_claim_a_foreign_variant_id(): void
    {
        $seller = $this->seller();
        $mine = Product::factory()->create(['seller_id' => $seller->id, 'has_variants' => true]);
        $foreign = ProductVariant::factory()->create();

        $this->actingAs($seller)->put(route('seller.products.update', $foreign->product), [
            'name_en' => 'X', 'sku' => 'X-1', 'category_id' => $mine->category_id, 'price' => 10, 'stock_quantity' => 1,
        ])->assertForbidden();

        $this->actingAs($seller)->from(route('seller.products.edit', $mine))->put(route('seller.products.update', $mine), [
            'name_en' => 'X', 'sku' => $mine->sku, 'category_id' => $mine->category_id, 'price' => 10, 'has_variants' => 1,
            'variants' => [['id' => $foreign->id] + $this->row(['Size' => 'M'], 'MINE-M')],
        ])->assertSessionHasErrors(['variants.0.id']);
        $this->assertSame($foreign->product_id, $foreign->fresh()->product_id);
    }

    public function test_edit_page_shows_the_variant_editor_and_admin_list_shows_variants(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['seller_id' => $seller->id, 'has_variants' => true]);
        ProductVariant::factory()->for($product)->attributes(['Size' => 'M', 'Colour' => 'Red'])->create(['sku' => 'SEE-M-RED']);

        $this->actingAs($seller)->get(route('seller.products.edit', $product))
            ->assertOk()
            ->assertSee('Generate combinations')
            ->assertSee('SEE-M-RED')
            ->assertSee('Size: M / Colour: Red');

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get('/admin/products')->assertOk()->assertSee('SEE-M-RED')->assertSee('M / Red');
    }
}
