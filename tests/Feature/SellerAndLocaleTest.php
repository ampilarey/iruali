<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_seller_relation_uses_seller_id(): void
    {
        $seller = User::factory()->create(['business_name' => 'Island Crafts', 'city' => 'Addu City']);
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->assertTrue($product->seller->is($seller));
    }

    public function test_product_tile_shows_the_seller_and_island(): void
    {
        $seller = User::factory()->create(['business_name' => 'Island Crafts', 'city' => 'Addu City']);
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $html = view('components.product-card', ['product' => $product->fresh()])->render();

        $this->assertStringContainsString('Island Crafts', $html);
        $this->assertStringContainsString('Addu City', $html);
    }

    public function test_chosen_language_wins_over_browser_header(): void
    {
        $this->withSession(['locale' => 'dv'])
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/');

        $this->assertSame('dv', app()->getLocale());
    }

    public function test_browser_header_is_used_when_nothing_was_chosen(): void
    {
        $this->withHeader('Accept-Language', 'dv')->get('/');

        $this->assertSame('dv', app()->getLocale());
    }
}
