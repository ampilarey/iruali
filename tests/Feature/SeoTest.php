<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    #[Test]
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

    #[Test]
    public function it_generates_product_seo_data()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);

        $product = Product::factory()->create([
            'name' => [
                'en' => 'Test Product',
                'dv' => 'ޓެސްޓް ޕްރޮޑަކްޓް',
            ],
            'description' => [
                'en' => 'This is a test product description',
                'dv' => 'މިއަކީ ޓެސްޓް ޕްރޮޑަކްޓް ޑިސްކްރިޕްޝަންއެވެ',
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

    #[Test]
    public function it_generates_category_seo_data()
    {
        $category = Category::create([
            'name' => [
                'en' => 'Electronics',
                'dv' => 'އިލެކްޓްރޮނިކްސް',
            ],
            'description' => [
                'en' => 'Electronic devices and gadgets',
                'dv' => 'އިލެކްޓްރޮނިކްސް ޑިވައިސްތަކާއި ގަޑްޖެޓްތައްތަކް',
            ],
            'slug' => 'electronics',
            'status' => 'active',
        ]);

        $seo = SeoService::forCategory($category);

        $this->assertEquals('Electronics - iruali', $seo['title']);
        $this->assertStringContainsString('Electronic devices and gadgets', $seo['description']);
        $this->assertStringContainsString('Electronics', $seo['keywords']);
        $this->assertEquals('website', $seo['og_type']);
        $this->assertArrayHasKey('schema', $seo);
        $this->assertEquals('CollectionPage', $seo['schema']['@type']);
    }

    #[Test]
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

    #[Test]
    public function it_generates_shop_seo_data_from_the_shop_name()
    {
        $user = User::factory()->create([
            'name' => 'John Seller',
            'business_name' => 'Island Crafts',
            'is_seller' => true,
            'seller_approved' => true,
        ]);

        $seo = SeoService::forUser($user);

        $this->assertEquals('Island Crafts - iruali', $seo['title']);
        $this->assertStringContainsString('Island Crafts', $seo['description']);
        $this->assertStringNotContainsString('John Seller', json_encode($seo));
        $this->assertStringContainsString('shop', $seo['keywords']);
        $this->assertEquals('profile', $seo['og_type']);
        $this->assertEquals('Organization', $seo['schema']['@type']);
        $this->assertEquals('Island Crafts', $seo['schema']['name']);
    }

    #[Test]
    public function a_404_for_someone_who_is_not_a_shop_does_not_show_their_name()
    {
        $customer = User::factory()->create(['name' => 'Private Person Name', 'is_seller' => false]);

        $this->get('/shops/'.$customer->id)->assertNotFound()->assertDontSee('Private Person Name');
    }

    #[Test]
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

    #[Test]
    public function it_handles_localized_content_in_seo()
    {
        app()->setLocale('dv');

        $category = Category::create([
            'name' => [
                'en' => 'Electronics',
                'dv' => 'އިލެކްޓްރޮނިކްސް',
            ],
            'description' => [
                'en' => 'Electronic devices',
                'dv' => 'އިލެކްޓްރޮނިކްސް ޑިވައިސްތަކް',
            ],
            'slug' => 'electronics',
            'status' => 'active',
        ]);

        $seo = SeoService::forCategory($category);

        $this->assertStringContainsString('އިލެކްޓްރޮނިކްސް', $seo['title']);
        $this->assertStringContainsString('އިލެކްޓްރޮނިކްސް ޑިވައިސްތަކް', $seo['description']);
    }

    #[Test]
    public function it_generates_json_ld_schema_for_products()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);

        $product = Product::factory()->create([
            'name' => [
                'en' => 'Test Product',
                'dv' => 'ޓެސްޓް ޕްރޮޑަކްޓް',
            ],
            'description' => [
                'en' => 'Test description',
                'dv' => 'ޓެސްޓް ޑިސްކްރިޕްޝަން',
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
