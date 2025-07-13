<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\LocalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
    }

    /** @test */
    public function it_uses_fallback_locale_when_translation_missing()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        // Create product with only English translation
        $product = Product::factory()->create([
            'name' => [
                'en' => 'English Product Name',
                'dv' => null
            ],
            'description' => [
                'en' => 'English description',
                'dv' => null
            ],
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        // Set locale to Dhivehi
        app()->setLocale('dv');

        // Should fallback to English
        $this->assertEquals('English Product Name', $product->localized_name);
        $this->assertEquals('English description', $product->localized_description);
    }

    /** @test */
    public function it_returns_correct_translation_when_available()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        // Create product with both translations
        $product = Product::factory()->create([
            'name' => [
                'en' => 'English Product Name',
                'dv' => 'Dhivehi Product Name'
            ],
            'description' => [
                'en' => 'English description',
                'dv' => 'Dhivehi description'
            ],
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        // Test English
        app()->setLocale('en');
        $this->assertEquals('English Product Name', $product->localized_name);
        $this->assertEquals('English description', $product->localized_description);

        // Test Dhivehi
        app()->setLocale('dv');
        $this->assertEquals('Dhivehi Product Name', $product->localized_name);
        $this->assertEquals('Dhivehi description', $product->localized_description);
    }

    /** @test */
    public function localization_service_handles_fallbacks_correctly()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $product = Product::factory()->create([
            'name' => [
                'en' => 'English Name',
                'dv' => null
            ],
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        app()->setLocale('dv');

        $localizedName = LocalizationService::getLocalizedValue($product, 'name');
        $this->assertEquals('English Name', $localizedName);
    }

    /** @test */
    public function category_model_supports_translatable_fields()
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

        app()->setLocale('en');
        $this->assertEquals('Electronics', $category->localized_name);
        $this->assertEquals('Electronic devices and gadgets', $category->localized_description);

        app()->setLocale('dv');
        $this->assertEquals('އިލެކްޓްރޮނިކްސް', $category->localized_name);
        $this->assertEquals('އިލެކްޓްރޮނިކްސް ޑިވައިސްތަކާއި ގަޑްޖެޓްތައްތަކް', $category->localized_description);
    }

    /** @test */
    public function api_resources_use_localized_values()
    {
        $category = Category::factory()->create();
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $product = Product::factory()->create([
            'name' => [
                'en' => 'English Product',
                'dv' => 'Dhivehi Product'
            ],
            'description' => [
                'en' => 'English description',
                'dv' => 'Dhivehi description'
            ],
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'is_active' => true,
        ]);

        app()->setLocale('dv');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Dhivehi Product',
                    'description' => 'Dhivehi description'
                ]
            ]);
    }

    /** @test */
    public function locale_switching_works_correctly()
    {
        $response = $this->actingAs($this->admin)
            ->post('/locale/switch', ['locale' => 'dv']);

        $response->assertRedirect();
        $this->assertEquals('dv', session('locale'));
    }

    /** @test */
    public function invalid_locale_is_rejected()
    {
        $response = $this->actingAs($this->admin)
            ->post('/locale/switch', ['locale' => 'invalid']);

        $response->assertSessionHasErrors(['locale']);
    }

    /** @test */
    public function translatable_request_validation_works()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/products', [
                'name' => [
                    'en' => 'Test Product'
                    // Missing Dhivehi translation
                ],
                'sku' => 'TEST-001',
                'category_id' => Category::factory()->create()->id,
                'price' => 100
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name.dv']);
    }

    /** @test */
    public function at_least_one_translation_is_required()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/products', [
                'name' => [
                    'en' => '',
                    'dv' => ''
                ],
                'sku' => 'TEST-001',
                'category_id' => Category::factory()->create()->id,
                'price' => 100
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function localization_service_returns_available_locales()
    {
        $locales = LocalizationService::getAvailableLocales();
        
        $this->assertContains('en', $locales);
        $this->assertContains('dv', $locales);
        $this->assertCount(2, $locales);
    }

    /** @test */
    public function localization_service_returns_current_and_fallback_locales()
    {
        $this->assertEquals('en', LocalizationService::getFallbackLocale());
        
        app()->setLocale('dv');
        $this->assertEquals('dv', LocalizationService::getCurrentLocale());
    }

    /** @test */
    public function category_full_path_uses_localized_names()
    {
        $parent = Category::create([
            'name' => [
                'en' => 'Electronics',
                'dv' => 'އިލެކްޓްރޮނިކްސް'
            ],
            'slug' => 'electronics',
            'status' => 'active'
        ]);

        $child = Category::create([
            'name' => [
                'en' => 'Phones',
                'dv' => 'ފޯންތައް'
            ],
            'slug' => 'phones',
            'parent_id' => $parent->id,
            'status' => 'active'
        ]);

        app()->setLocale('dv');
        $this->assertEquals('އިލެކްޓްރޮނިކްސް > ފޯންތައް', $child->full_path);

        app()->setLocale('en');
        $this->assertEquals('Electronics > Phones', $child->full_path);
    }
} 