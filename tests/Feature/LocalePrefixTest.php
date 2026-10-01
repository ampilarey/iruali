<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Language in the URL: English storefront pages at the plain URLs, Dhivehi under /dv/...
 */
class LocalePrefixTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        return Product::factory()->create(['slug' => 'copper-kettle', 'name' => ['en' => 'Copper kettle', 'dv' => 'ކޮޕަރ ކެތަލް'], 'stock_quantity' => 5]);
    }

    public function test_dv_prefix_renders_dhivehi_and_plain_urls_stay_english(): void
    {
        $product = $this->product();

        $this->get('/')->assertOk()->assertSee('dir="ltr"', false)->assertSee('View All Products');
        $this->get('/dv')->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="dv"', false)->assertSee('ހުރިހާ ތަކެތި ބައްލަވާ');
        $this->get('/dv/')->assertOk()->assertSee('dir="rtl"', false);
        $this->get('/dv/products/copper-kettle')->assertOk()->assertSee('dir="rtl"', false)->assertSee('ކޮޕަރ ކެތަލް');
        $this->get('/products/copper-kettle')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Copper kettle');
        $this->get('/dv/deals')->assertOk()->assertSee('dir="rtl"', false);
        $this->get('/dv/does-not-exist')->assertNotFound();
        $this->get('/dvd')->assertNotFound();
    }

    public function test_non_storefront_routes_are_not_prefixed(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->actingAs($user)->get('/account')->assertOk();
        $this->actingAs($user)->get('/dv/account')->assertNotFound();
        $this->actingAs($user)->get('/dv/checkout')->assertNotFound();
        $this->actingAs($user)->get('/dv/orders')->assertNotFound();
        $this->actingAs($admin)->get('/dv/admin/dashboard')->assertNotFound();
        $this->get('/dv/login')->assertNotFound();
        $this->get('/dv/api/v1/products')->assertNotFound();
        $this->get('/dv/sitemap.xml')->assertNotFound();
        $this->get('/dv/robots.txt')->assertNotFound();

        // A Dhivehi session still renders private pages in Dhivehi at their plain URL
        $this->actingAs($user)->withSession(['locale' => 'dv'])->get('/account')->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_route_generation_follows_the_locale(): void
    {
        $product = $this->product();

        app()->setLocale('en');
        $this->assertSame(url('/products/copper-kettle'), route('products.show', $product));
        $this->assertSame(url('/'), route('home'));
        $this->assertSame(url('/checkout'), route('checkout'));

        app()->setLocale('dv');
        $this->assertSame(url('/dv/products/copper-kettle'), route('products.show', $product));
        $this->assertSame(url('/dv'), route('home'));
        $this->assertSame(url('/dv/deals'), route('deals'));
        $this->assertSame(url('/dv/categories'), route('categories.index'));
        $this->assertSame(url('/dv/search').'?q=boat', route('search', ['q' => 'boat']));
        $this->assertSame(url('/dv/cart/add'), route('cart.add'));
        // never prefixed
        $this->assertSame(url('/checkout'), route('checkout'));
        $this->assertSame(url('/account'), route('account'));
        $this->assertSame(url('/admin/dashboard'), route('admin.dashboard'));
        $this->assertSame(url('/seller/dashboard'), route('seller.dashboard'));
        $this->assertSame(url('/login'), route('login'));
        $this->assertSame(url('/sitemap.xml'), route('sitemap'));
        $this->assertSame(url('/offline'), route('offline'));
        $this->assertSame(url('/storage/x.jpg'), route('storage.file', 'x.jpg'));
        $this->assertSame(url('/images/a.png'), asset('images/a.png'));
        $this->assertSame(url('/anything'), url('/anything'));
        app()->setLocale('en');
    }

    public function test_links_assets_and_current_url_on_a_dv_page(): void
    {
        $this->product();
        Product::factory()->count(2)->create();

        $html = $this->get('/dv/products')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.url('/dv/deals').'"', $html);
        $this->assertStringContainsString('href="'.url('/dv/products/copper-kettle').'"', $html);
        $this->assertStringContainsString('action="'.url('/dv/search').'"', $html);
        $this->assertStringContainsString('action="'.url('/dv/products').'"', $html); // filter form uses request()->url()
        $this->assertStringContainsString('href="'.url('/login').'"', $html);
        $this->assertStringContainsString('href="'.url('/dv/cart').'"', $html);
        $this->assertStringNotContainsString('/dv/build/', $html);
        $this->assertStringNotContainsString('/dv/images/', $html);
        $this->assertStringNotContainsString('/dv/dv/', $html);
        $this->assertStringNotContainsString(url('/dv/account'), $html);
        $this->assertStringNotContainsString(url('/dv/login'), $html);
    }

    public function test_canonical_hreflang_and_og_locale(): void
    {
        $product = $this->product();

        $this->get('/products/copper-kettle')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/products/copper-kettle').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/products/copper-kettle').'">', false)
            ->assertSee('<link rel="alternate" hreflang="dv" href="'.url('/dv/products/copper-kettle').'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/products/copper-kettle').'">', false)
            ->assertSee('<meta property="og:locale" content="en_US">', false)
            ->assertSee('<meta property="og:locale:alternate" content="dv_MV">', false);

        $this->get('/dv/products/copper-kettle')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/dv/products/copper-kettle').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/products/copper-kettle').'">', false)
            ->assertSee('<link rel="alternate" hreflang="dv" href="'.url('/dv/products/copper-kettle').'">', false)
            ->assertSee('<meta property="og:locale" content="dv_MV">', false)
            ->assertSee('<meta property="og:locale:alternate" content="en_US">', false);

        // Query strings travel with the alternates; private pages have none
        $this->get('/dv/search?q=boat')->assertOk()
            ->assertSee('hreflang="en" href="'.url('/search').'?q=boat"', false);
        $this->actingAs(User::factory()->create())->get('/account')->assertOk()->assertDontSee('rel="alternate" hreflang=', false);
    }

    public function test_language_links_switch_to_the_same_page_and_remember_the_choice(): void
    {
        $product = $this->product();

        $html = $this->get('/products/copper-kettle')->assertOk()->getContent();
        $switch = url('/locale/switch').'?locale=dv&amp;to='.urlencode('/dv/products/copper-kettle');
        $this->assertStringContainsString('href="'.$switch.'"', $html);
        $this->assertStringNotContainsString('name="locale"', $html); // no POST form any more

        $this->get('/locale/switch?locale=dv&to=/dv/products/copper-kettle')
            ->assertRedirect(url('/dv/products/copper-kettle'))->assertSessionHas('locale', 'dv');

        $html = $this->withSession(['locale' => 'dv'])->get('/dv/products/copper-kettle')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.url('/locale/switch').'?locale=en&amp;to='.urlencode('/products/copper-kettle').'"', $html);

        $this->get('/locale/switch?locale=en&to=/products/copper-kettle')
            ->assertRedirect(url('/products/copper-kettle'))->assertSessionHas('locale', 'en');

        // Only paths on this site are followed
        $this->get('/locale/switch?locale=en&to=https://evil.example.com/')->assertRedirect(url('/'));
        $this->get('/locale/switch?locale=en&to=//evil.example.com/')->assertRedirect(url('/'));
        $this->get('/locale/switch?locale=xx&to=/')->assertSessionHasErrors('locale');

        // The old POST form still works and goes back to the referring page in the new language
        $this->from('/products/copper-kettle')->post('/locale/switch', ['locale' => 'dv'])
            ->assertRedirect(url('/dv/products/copper-kettle'))->assertSessionHas('locale', 'dv');

        // Signed-in users keep the preference on their account
        $user = User::factory()->create(['preferred_language' => 'en']);
        $this->actingAs($user)->get('/locale/switch?locale=dv&to=/dv');
        $this->assertSame('dv', $user->fresh()->preferred_language);
    }

    public function test_session_only_dhivehi_still_works_without_the_prefix(): void
    {
        $this->product();

        $html = $this->withSession(['locale' => 'dv'])->get('/products/copper-kettle')->assertOk()
            ->assertSee('dir="rtl"', false)->assertSee('ކޮޕަރ ކެތަލް')->getContent();
        // ...and from there every storefront link leads to the /dv URLs
        $this->assertStringContainsString('href="'.url('/dv/deals').'"', $html);
        $this->assertStringNotContainsString('/dv/dv/', $html);
    }

    public function test_cart_forms_on_dv_pages_post_to_the_prefixed_routes(): void
    {
        $product = $this->product();

        $this->post('/dv/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect(url('/dv/cart'));
        $this->get('/dv/cart')->assertOk()->assertSee('dir="rtl"', false)->assertSee('ކޮޕަރ ކެތަލް');
    }

    public function test_sitemap_lists_both_languages_with_alternates(): void
    {
        $product = $this->product();
        Category::create(['name' => ['en' => 'Boats', 'dv' => 'ދޯނި'], 'slug' => 'boats', 'status' => 'active']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'sitemap is not well-formed XML');
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        $en = url('/products/copper-kettle');
        $dv = url('/dv/products/copper-kettle');
        $this->assertSame(1, $xpath->query('//s:url[s:loc="'.$en.'"]')->length);
        $this->assertSame(1, $xpath->query('//s:url[s:loc="'.$dv.'"]')->length);
        foreach ([$en, $dv] as $loc) {
            $this->assertSame($en, $xpath->query('//s:url[s:loc="'.$loc.'"]/xhtml:link[@hreflang="en"]/@href')->item(0)->nodeValue);
            $this->assertSame($dv, $xpath->query('//s:url[s:loc="'.$loc.'"]/xhtml:link[@hreflang="dv"]/@href')->item(0)->nodeValue);
            $this->assertSame($en, $xpath->query('//s:url[s:loc="'.$loc.'"]/xhtml:link[@hreflang="x-default"]/@href')->item(0)->nodeValue);
        }
        $this->assertSame(1, $xpath->query('//s:url[s:loc="'.url('/dv/categories/boats').'"]')->length);
        $this->assertSame(1, $xpath->query('//s:url[s:loc="'.url('/dv').'"]')->length);
        $this->assertSame(0, $xpath->query('//s:url[s:loc="'.url('/dv/sitemap.xml').'"]')->length);
    }
}
