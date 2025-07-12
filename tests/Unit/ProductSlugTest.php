<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create required Category and User for foreign key constraints
        Category::create([
            'id' => 1,
            'name' => 'Test Category',
            'slug' => 'test-category',
            'status' => 'active'
        ]);
        
        User::create([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true
        ]);
    }

    public function test_product_uses_slug_as_route_key()
    {
        $product = Product::factory()->create([
            'name' => ['en' => 'Test Product', 'dv' => 'Test Product DV'],
            'slug' => 'test-product'
        ]);

        $this->assertEquals('slug', $product->getRouteKeyName());
    }

    public function test_product_automatically_generates_slug_on_creation()
    {
        $product = Product::factory()->create([
            'name' => ['en' => 'New Test Product', 'dv' => 'New Test Product DV'],
            'slug' => null
        ]);

        $this->assertNotNull($product->slug);
        $this->assertEquals('new-test-product', $product->slug);
    }

    public function test_product_generates_unique_slug_when_duplicate_exists()
    {
        // Create first product
        Product::factory()->create([
            'name' => ['en' => 'Test Product', 'dv' => 'Test Product DV'],
            'slug' => 'test-product'
        ]);

        // Create second product with same name
        $product2 = Product::factory()->create([
            'name' => ['en' => 'Test Product', 'dv' => 'Test Product DV'],
            'slug' => null
        ]);

        $this->assertEquals('test-product-1', $product2->slug);
    }

    public function test_product_updates_slug_when_name_changes()
    {
        $product = Product::factory()->create([
            'name' => ['en' => 'Original Name', 'dv' => 'Original Name DV'],
            'slug' => 'original-name'
        ]);

        $product->update([
            'name' => ['en' => 'Updated Name', 'dv' => 'Updated Name DV']
        ]);

        $this->assertEquals('updated-name', $product->fresh()->slug);
    }

    public function test_product_does_not_update_slug_when_name_unchanged()
    {
        $product = Product::factory()->create([
            'name' => ['en' => 'Test Product', 'dv' => 'Test Product DV'],
            'slug' => 'test-product'
        ]);

        $originalSlug = $product->slug;

        $product->update([
            'price' => 99.99 // Change something other than name
        ]);

        $this->assertEquals($originalSlug, $product->fresh()->slug);
    }

    public function test_product_can_be_found_by_slug()
    {
        $product = Product::factory()->create([
            'name' => ['en' => 'Findable Product', 'dv' => 'Findable Product DV'],
            'slug' => 'findable-product'
        ]);

        $foundProduct = Product::where('slug', 'findable-product')->first();

        $this->assertNotNull($foundProduct);
        $this->assertEquals($product->id, $foundProduct->id);
    }
} 