<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_generates_default_seo_data()
    {
        $seo = SeoService::getDefault();

        $this->assertArrayHasKey('title', $seo);
        $this->assertArrayHasKey('description', $seo);
        $this->assertArrayHasKey('keywords', $seo);
        $this->assertArrayHasKey('og_title', $seo);
        $this->assertArrayHasKey('og_description', $seo);
        $this->assertArrayHasKey('og_type', $seo);
        $this->assertArrayHasKey('canonical_url', $seo);
        $this->assertArrayHasKey('schema', $seo);
    }

    /** @test */
    public function it_generates_product_seo_data()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Test Product',
                'dv' => 'ޓެސްޓް ޕްރޮޑަކްޓް'
            ],
            'description' => [
                'en' => 'This is a test product description',
                'dv' => 'މިއަކީ ޓެސްޓް ޕްރޮޑަކްޓް ޑިސްކްރިޕްޝަންއެވެ'
            ],
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'brand' => 'Test Brand',
            'tags' => ['test', 'product'],
            'is_active' => true,
        ]);

        $seo = SeoService::forProduct($product);

        $this->assertEquals('Test Product - iruali', $seo['title']);
        $this->assertStringContainsString('This is a test product description', $seo['description']);
        $this->assertStringContainsString('Test Product', $seo['keywords']);
        $this->assertStringContainsString('Test Brand', $seo['keywords']);
        $this->assertEquals('product', $seo['og_type']);
        $this->assertArrayHasKey('schema', $seo);
        $this->assertEquals('Product', $seo['schema']['@type']);
    }

    /** @test */
    public function it_generates_category_seo_data()
    {
        $category = Category::create([
            'name' => [
                'en' => 'Electronics',
                'dv' => 'އިލެކްޓްރޮނިކްސް'
            ],
            'description' => [
                'en' => 'Electronic devices and gadgets',
                'dv' => 'އިލެކްޓްރޮނިކްސް ޑިވައިސްތަކާއި ގަޑްޖެޓްތައްތަކް'
            ],
            'slug' => 'electronics',
            'status' => 'active'
        ]);

        $seo = SeoService::forCategory($category);

        $this->assertEquals('Electronics - iruali', $seo['title']);
        $this->assertStringContainsString('Electronic devices and gadgets', $seo['description']);
        $this->assertStringContainsString('Electronics', $seo['keywords']);
        $this->assertEquals('website', $seo['og_type']);
        $this->assertArrayHasKey('schema', $seo);
        $this->assertEquals('CollectionPage', $seo['schema']['@type']);
    }

    /** @test */
    public function it_generates_search_seo_data()
    {
        $query = 'test product';
        $totalResults = 25;

        $seo = SeoService::forSearch($query, $totalResults);

        $this->assertEquals("Search results for 'test product' - iruali", $seo['title']);
        $this->assertStringContainsString('test product', $seo['description']);
        $this->assertStringContainsString('25 products found', $seo['description']);
        $this->assertStringContainsString('test product', $seo['keywords']);
        $this->assertEquals('website', $seo['og_type']);
        $this->assertNull($seo['schema']);
    }

    /** @test */
    public function it_generates_user_seo_data_for_seller()
    {
        $user = User::factory()->create([
            'name' => 'John Seller',
            'is_seller' => true,
            'seller_approved' => true
        ]);

        $seo = SeoService::forUser($user);

        $this->assertEquals('Shop by John Seller - iruali', $seo['title']);
        $this->assertStringContainsString('John Seller', $seo['description']);
        $this->assertStringContainsString('seller', $seo['keywords']);
        $this->assertEquals('profile', $seo['og_type']);
        $this->assertArrayHasKey('schema', $seo);
        $this->assertEquals('Organization', $seo['schema']['@type']);
    }

    /** @test */
    public function it_generates_user_seo_data_for_customer()
    {
        $user = User::factory()->create([
            'name' => 'John Customer',
            'is_seller' => false
        ]);

        $seo = SeoService::forUser($user);

        $this->assertEquals("John Customer's Profile - iruali", $seo['title']);
        $this->assertStringContainsString('John Customer', $seo['description']);
        $this->assertStringNotContainsString('seller', $seo['keywords']);
        $this->assertEquals('profile', $seo['og_type']);
        $this->assertArrayHasKey('schema', $seo);
        $this->assertEquals('Person', $seo['schema']['@type']);
    }

    /** @test */
    public function it_includes_canonical_urls()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'is_active' => true,
        ]);

        $productSeo = SeoService::forProduct($product);
        $categorySeo = SeoService::forCategory($category);

        $this->assertStringContainsString($product->slug, $productSeo['canonical_url']);
        $this->assertStringContainsString($category->slug, $categorySeo['canonical_url']);
    }

    /** @test */
    public function it_handles_localized_content_in_seo()
    {
        app()->setLocale('dv');

        $category = Category::create([
            'name' => [
                'en' => 'Electronics',
                'dv' => 'އިލެކްޓްރޮނިކްސް'
            ],
            'description' => [
                'en' => 'Electronic devices',
                'dv' => 'އިލެކްޓްރޮނިކްސް ޑިވައިސްތަކް'
            ],
            'slug' => 'electronics',
            'status' => 'active'
        ]);

        $seo = SeoService::forCategory($category);

        $this->assertStringContainsString('އިލެކްޓްރޮނިކްސް', $seo['title']);
        $this->assertStringContainsString('އިލެކްޓްރޮނިކްސް ޑިވައިސްތަކް', $seo['description']);
    }

    /** @test */
    public function it_generates_json_ld_schema_for_products()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Test Product',
                'dv' => 'ޓެސްޓް ޕްރޮޑަކްޓް'
            ],
            'description' => [
                'en' => 'Test description',
                'dv' => 'ޓެސްޓް ޑިސްކްރިޕްޝަން'
            ],
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'price' => 100,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $seo = SeoService::forProduct($product);
        $schema = $seo['schema'];

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Product', $schema['@type']);
        $this->assertEquals('Test Product', $schema['name']);
        $this->assertEquals('Test description', $schema['description']);
        $this->assertEquals(100, $schema['offers']['price']);
        $this->assertEquals('MVR', $schema['offers']['priceCurrency']);
        $this->assertEquals('https://schema.org/InStock', $schema['offers']['availability']);
    }
} 