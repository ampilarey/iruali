<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_price_uses_own_price_or_product_price_plus_adjustment(): void
    {
        $product = Product::factory()->create(['price' => 100, 'has_variants' => true]);

        $own = ProductVariant::factory()->for($product)->priced(149.5)->create();
        $adjusted = ProductVariant::factory()->for($product)->create(['price' => null, 'price_adjustment' => 25]);
        $plain = ProductVariant::factory()->for($product)->create(['price' => null, 'price_adjustment' => 0]);

        $this->assertSame(149.5, $own->fresh()->effectivePrice());
        $this->assertSame(125.0, $adjusted->fresh()->effectivePrice());
        $this->assertSame(100.0, $plain->fresh()->effectivePrice());
    }

    public function test_display_name_joins_attribute_values_and_translates_keys(): void
    {
        $variant = ProductVariant::factory()->attributes(['Size' => 'M', 'Colour' => 'Blue'])->make();

        $this->assertSame('M / Blue', $variant->displayName());
        $this->assertSame('Size: M / Colour: Blue', $variant->displayNameWithKeys());

        app()->setLocale('dv');
        $this->assertStringContainsString(__('Size').': M', $variant->displayNameWithKeys());
        $this->assertNotSame('Size: M / Colour: Blue', $variant->displayNameWithKeys());
        app()->setLocale('en');
    }

    public function test_product_stock_is_the_sum_of_active_variant_stock(): void
    {
        $product = Product::factory()->create(['has_variants' => true, 'stock_quantity' => 99]);

        $a = ProductVariant::factory()->for($product)->stock(3)->create();
        $b = ProductVariant::factory()->for($product)->stock(4)->create();
        ProductVariant::factory()->for($product)->stock(10)->inactive()->create();

        $this->assertSame(7, $product->fresh()->effectiveStock());
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);
        $this->assertTrue($product->fresh()->is_in_stock);

        $a->update(['stock_quantity' => 0]);
        $this->assertSame(4, (int) $product->fresh()->stock_quantity);

        $b->delete();
        $this->assertSame(0, (int) $product->fresh()->stock_quantity);
        $this->assertFalse($product->fresh()->is_in_stock);
        $this->assertFalse($a->fresh()->isInStock());
    }

    public function test_low_stock_considers_variants_and_their_thresholds(): void
    {
        $product = Product::factory()->create(['has_variants' => true, 'reorder_point' => 5]);
        ProductVariant::factory()->for($product)->stock(50)->create();
        $this->assertFalse($product->fresh()->isLowStock());

        $low = ProductVariant::factory()->for($product)->stock(8)->create(['low_stock_threshold' => 10]);
        $this->assertTrue($low->isLowStock());
        $this->assertTrue($product->fresh()->isLowStock());

        $plain = Product::factory()->create(['has_variants' => false, 'stock_quantity' => 2, 'reorder_point' => 5]);
        $this->assertTrue($plain->isLowStock());
    }

    public function test_from_price_only_when_variant_prices_differ(): void
    {
        $product = Product::factory()->create(['price' => 100, 'has_variants' => true]);
        ProductVariant::factory()->for($product)->create();
        ProductVariant::factory()->for($product)->create();
        $this->assertNull($product->fresh()->from_price);

        ProductVariant::factory()->for($product)->priced(80)->create();
        $this->assertSame(80.0, $product->fresh()->from_price);
        $this->assertSame([80.0, 100.0], $product->fresh()->variantPriceRange());
    }
}
