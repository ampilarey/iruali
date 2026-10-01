<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * robots.txt, sitemap, per-page titles, noindex on private pages, branded error pages.
 */
class SeoAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_and_sitemap_are_served_from_the_app(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')->assertSee('Disallow: /checkout')->assertSee('Sitemap: '.url('/sitemap.xml'));

        $product = Product::factory()->create(['is_active' => true]);
        $category = Category::create(['name' => ['en' => 'Boats', 'dv' => 'ދޯނި'], 'slug' => 'boats', 'status' => 'active']);
        $hidden = Product::factory()->create(['is_active' => false]);
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reefline']);
        $seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString(route('products.show', $product->slug), $xml);
        $this->assertStringContainsString(route('categories.show', $category->slug), $xml);
        $this->assertStringContainsString(route('sellers.show', $seller), $xml);
        $this->assertStringContainsString(route('policies.terms'), $xml);
        $this->assertStringNotContainsString(route('products.show', $hidden->slug), $xml);
    }

    public function test_pages_get_their_own_titles(): void
    {
        $this->get('/')->assertSee('<title>'.config('app.name').'</title>', false)->assertSee('content="index, follow"', false);
        $this->get(route('policies.terms'))->assertSee('<title>Terms &amp; Conditions - iruali</title>', false);
        $this->get(route('login'))->assertSee('- iruali</title>', false)->assertDontSee('<title>iruali</title>', false);

        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reefline Marine']);
        $this->get(route('sellers.show', $seller))->assertOk()->assertSee('Reefline Marine', false);
    }

    public function test_private_pages_are_noindex(): void
    {
        $user = User::factory()->create();
        foreach ([route('login'), route('register'), route('password.request')] as $url) {
            $this->get($url)->assertSee('content="noindex, nofollow"', false);
        }
        foreach ([route('cart'), route('account'), route('orders'), route('wishlist')] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('content="noindex, nofollow"', false);
        }
        $this->get('/search?q=boat')->assertSee('content="noindex, nofollow"', false);
        $this->get(route('shop'))->assertSee('content="index, follow"', false);
    }

    public function test_error_pages_are_branded(): void
    {
        $this->get('/definitely-not-a-page')->assertNotFound()
            ->assertSee('Page not found')->assertSee(route('home'), false)->assertSee('images/brand/iruali-mark.svg', false)
            ->assertSee('content="noindex, nofollow"', false);
    }
}
