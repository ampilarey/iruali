<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProductImportService;
use App\Support\SafeHttp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * "Import from a link" (App\Services\ProductImportService, App\Support\SafeHttp): a shop allowed pre-orders
 * fills a product from its page on an online shop abroad, with no outside service: the page's schema.org
 * data, share tags or Shopify data, its photos, and an MVR price from Admin → Settings → Pre-order imports.
 */
class ProductLinkImportTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    /** A 1×1 PNG. */
    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected User $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
        SafeHttp::resolveUsing(fn (string $host) => match ($host) {
            'intranet.test' => ['10.0.0.5'],
            'metadata.test' => ['169.254.169.254'],
            'sneaky.test' => ['93.184.216.34', '127.0.0.1'],
            default => ['93.184.216.34'],
        });
        Setting::set(['import_exchange_rates' => "USD 15.42\nEUR 16.80", 'import_shipping_per_kg' => 100, 'import_margin_percent' => 10]);
        $this->shop = $this->seller('Lens House');
        $this->shop->forceFill(['onboarding_completed_at' => now()])->save();
    }

    protected function tearDown(): void
    {
        SafeHttp::resolveUsing(null);
        parent::tearDown();
    }

    protected function page(string $head, string $body = ''): string
    {
        return '<!doctype html><html><head>'.$head.'</head><body>'.$body.'</body></html>';
    }

    protected function cameraPage(): string
    {
        $data = json_encode(['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'WebSite', 'name' => 'Camera World'],
            ['@type' => 'Product', 'name' => 'Mirrorless Camera X100 &amp; lens', 'description' => '<p>Compact camera.</p><p>24 MP sensor.</p>',
                'brand' => ['@type' => 'Brand', 'name' => 'Fujix'], 'image' => ['/img/front.png', ['url' => 'https://cdn.camera.test/side.png']],
                'weight' => ['@type' => 'QuantitativeValue', 'value' => 500, 'unitCode' => 'GRM'],
                'offers' => ['@type' => 'Offer', 'price' => '1,299.00', 'priceCurrency' => 'USD']],
        ]]);

        return $this->page('<title>Camera World</title><meta property="og:image" content="https://cdn.camera.test/og.png"><script type="application/ld+json">'.$data.'</script>');
    }

    public function test_it_reads_schema_org_data_and_share_tags(): void
    {
        $import = app(ProductImportService::class);

        $camera = $import->parse($this->cameraPage(), 'https://camera.test/p/x100');
        $this->assertSame('Mirrorless Camera X100 & lens', $camera['name']);
        $this->assertSame("Compact camera.\n24 MP sensor.", $camera['description']);
        $this->assertSame('Fujix', $camera['brand']);
        $this->assertSame(1299.0, $camera['price']);
        $this->assertSame('USD', $camera['currency']);
        $this->assertSame(0.5, $camera['weight']);
        $this->assertSame(['https://camera.test/img/front.png', 'https://cdn.camera.test/side.png', 'https://cdn.camera.test/og.png'], $camera['images']);

        // Only share tags: still a name, a price and a photo
        $mug = $import->parse($this->page('<meta property="og:title" content="Coffee mug"><meta property="og:image" content="//cdn.mug.test/a.jpg"><meta property="product:price:amount" content="12,50"><meta property="product:price:currency" content="eur">'), 'https://mug.test/item');
        $this->assertSame('Coffee mug', $mug['name']);
        $this->assertSame(12.5, $mug['price']);
        $this->assertSame('EUR', $mug['currency']);
        $this->assertSame(['https://cdn.mug.test/a.jpg'], $mug['images']);

        // A product group's variants
        $group = json_encode(['@type' => 'ProductGroup', 'name' => 'Rain jacket', 'variesBy' => ['https://schema.org/size', 'https://schema.org/color'], 'hasVariant' => [
            ['@type' => 'Product', 'size' => 'M', 'color' => 'Blue', 'offers' => ['price' => 40, 'priceCurrency' => 'USD']],
            ['@type' => 'Product', 'size' => 'L', 'color' => 'Blue', 'offers' => ['price' => 42, 'priceCurrency' => 'USD']],
        ]]);
        $jacket = $import->parse($this->page('<script type="application/ld+json">'.$group.'</script>'), 'https://wear.test/jacket');
        $this->assertSame([['attributes' => ['Size' => 'M', 'Colour' => 'Blue'], 'price' => 40.0], ['attributes' => ['Size' => 'L', 'Colour' => 'Blue'], 'price' => 42.0]], $jacket['variants']);
        $this->assertSame(40.0, $jacket['price']);
        $this->assertSame('USD', $jacket['currency']);
    }

    public function test_a_shopify_shop_gives_every_option_and_photo(): void
    {
        Http::fake([
            'boots.test/products/trail-boot.js' => Http::response(json_encode([
                'title' => 'Trail boot', 'description' => '<p>Waterproof.</p>', 'vendor' => 'Hikeco', 'price' => 8900,
                'options' => [['name' => 'Size'], ['name' => 'Colour']], 'images' => ['//cdn.shopify.com/boot1.png', '//cdn.shopify.com/boot2.png'],
                'variants' => [
                    ['option1' => '42', 'option2' => 'Brown', 'price' => 8900, 'grams' => 1200],
                    ['option1' => '43', 'option2' => 'Brown', 'price' => 9100, 'grams' => 1200],
                ],
            ])),
        ]);
        $html = $this->page('<script>Shopify.shop = "boots.myshopify.com"; Shopify.currency = {"active":"USD","rate":"1.0"};</script>');

        $boot = app(ProductImportService::class)->shopify('https://boots.test/products/trail-boot?variant=1', $html);
        $this->assertSame('Trail boot', $boot['name']);
        $this->assertSame('Hikeco', $boot['brand']);
        $this->assertSame(89.0, $boot['price']);
        $this->assertSame('USD', $boot['currency']);
        $this->assertSame(1.2, $boot['weight']);
        $this->assertSame(['https://cdn.shopify.com/boot1.png', 'https://cdn.shopify.com/boot2.png'], $boot['images']);
        $this->assertSame([['attributes' => ['Size' => '42', 'Colour' => 'Brown'], 'price' => 89.0], ['attributes' => ['Size' => '43', 'Colour' => 'Brown'], 'price' => 91.0]], $boot['variants']);

        $this->assertNull(app(ProductImportService::class)->shopify('https://other.test/p/1', $html), 'not a Shopify product address');
    }

    public function test_only_public_web_addresses_are_fetched(): void
    {
        Http::fake([
            'shop.test/moved' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
            'shop.test/elsewhere' => Http::response('', 301, ['Location' => '/here']),
            'shop.test/here' => Http::response('here', 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('ok', 200, ['Content-Type' => 'text/html']),
        ]);

        foreach (['http://localhost/x', 'http://127.0.0.1/x', 'http://10.1.2.3/', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/',
            'https://intranet.test/', 'https://metadata.test/', 'https://sneaky.test/', 'https://shop.test:8080/', 'https://user:pass@shop.test/',
            'ftp://shop.test/file', 'file:///etc/passwd', 'javascript:alert(1)'] as $url) {
            $this->assertNull(SafeHttp::get($url, 1000), $url);
        }
        $this->assertSame('ok', SafeHttp::get('https://shop.test/item', 1000)['body']);
        $this->assertNull(SafeHttp::get('https://shop.test/item', 1), 'too large');

        // A redirect is followed only to a public address
        $this->assertNull(SafeHttp::get('https://shop.test/moved', 1000));
        $this->assertSame('here', SafeHttp::get('https://shop.test/elsewhere', 1000)['body']);
    }

    public function test_the_mvr_price_is_suggested_from_the_admins_rates(): void
    {
        $import = app(ProductImportService::class);

        // USD 100 × 15.42 = 1,542 + 0.5 kg × MVR 100 = 1,592, + 10% = 1,751.20
        $this->assertSame(['price' => 1751.2, 'rate' => 15.42, 'shipping' => 50.0, 'margin' => 10.0], $import->suggestPrice(100, 'USD', 0.5));
        $this->assertSame(1696.2, $import->suggestPrice(100, 'usd', null)['price'], 'no weight: no shipping');
        $this->assertSame(110.0, $import->suggestPrice(100, 'MVR', null)['price']);
        $this->assertNull($import->suggestPrice(100, 'JPY', 1), 'no rate for it');
        $this->assertNull($import->suggestPrice(null, 'USD', 1));

        // The rates are set in Admin → Settings
        $admin = $this->staffMember('admin');
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('Pre-order imports')->assertSee('USD 15.42');
        $required = ['loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50];
        $this->put(route('admin.settings.update'), $required + ['import_exchange_rates' => 'not a rate'])->assertSessionHasErrors('import_exchange_rates');
        $this->put(route('admin.settings.update'), $required + ['import_exchange_rates' => "GBP 19.5\r\nusd: 15.5", 'import_shipping_per_kg' => 80, 'import_margin_percent' => 5])->assertSessionHasNoErrors();
        $this->assertSame(['MVR' => 1.0, 'GBP' => 19.5, 'USD' => 15.5], ProductImportService::rates());
    }

    public function test_a_shop_fills_and_saves_a_pre_order_product_from_a_link(): void
    {
        Http::fake([
            'camera.test/p/x100' => Http::response($this->cameraPage(), 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'camera.test/img/front.png' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/png']),
            'cdn.camera.test/side.png' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/png']),
            'cdn.camera.test/og.png' => Http::response('<html>not a picture</html>', 200, ['Content-Type' => 'image/png']),
        ]);
        $category = Category::factory()->create(['status' => 'active']);

        $this->actingAs($this->shop)->get(route('seller.products.index'))->assertOk()->assertSee('data-import-from-link', false);
        $this->get(route('seller.products.from-link'))->assertOk()->assertSee('data-from-link-form', false);
        $this->post(route('seller.products.from-link.store'), ['url' => 'https://camera.test/p/x100'])
            ->assertRedirect(route('seller.products.create', ['from_link' => 1]));

        $draft = app(ProductImportService::class)->draft();
        $this->assertCount(2, $draft['images'], 'the page that is not a picture is skipped');
        foreach ($draft['images'] as $path) {
            Storage::disk('public')->assertExists($path);
        }

        // USD 1,299 × 15.42 + 0.5 kg × 100, + 10% = MVR 22,088.64
        $this->get(route('seller.products.create', ['from_link' => 1]))->assertOk()
            ->assertSee('data-imported', false)->assertSee('Mirrorless Camera X100 &amp; lens', false)->assertSee('Fujix')
            ->assertSee('value="22088.64"', false)->assertSee('Price on the page: USD 1,299.00')
            ->assertSee('value="https://camera.test/p/x100"', false)->assertSee('name="imported_images[]"', false);

        [$main, $other] = $draft['images'];
        $this->post(route('seller.products.store', ['from_link' => 1]), [
            'from_link' => 1, 'name_en' => 'Mirrorless Camera X100', 'description_en' => 'Compact camera.', 'sku' => 'IMP-X100', 'category_id' => $category->id,
            'price' => 22088.64, 'stock_quantity' => 0, 'reorder_point' => 0, 'has_variants' => 0, 'weight' => 0.5, 'brand' => 'Fujix',
            'preorder_form' => 1, 'preorder_enabled' => 1, 'preorder_ships' => 'days', 'preorder_lead_days' => 21, 'preorder_limit' => 5,
            'source_url' => 'https://camera.test/p/x100', 'imported_images' => [$main],
        ])->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index'));

        $product = Product::where('sku', 'IMP-X100')->sole();
        $this->assertSame('https://camera.test/p/x100', $product->source_url);
        $this->assertTrue($product->preorder_enabled);
        $this->assertSame($main, $product->main_image);
        $this->assertSame(1, $product->images()->count());
        Storage::disk('public')->assertMissing($other); // the photo the shop untick is deleted
        $this->assertNull(app(ProductImportService::class)->draft());

        // Admins see where it is bought; shoppers don't
        $this->actingAs($this->staffMember('admin'))->get(route('admin.products'))->assertOk()->assertSee('data-source-url', false)->assertSee('camera.test');
    }

    public function test_a_page_that_cannot_be_read_and_shops_not_allowed(): void
    {
        Http::fake([
            'blocked.test/*' => Http::response('Robot check', 403, ['Content-Type' => 'text/html']),
            'empty.test/*' => Http::response('<html><body>Nothing here</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($this->shop)->from(route('seller.products.from-link'))->post(route('seller.products.from-link.store'), ['url' => 'https://blocked.test/item'])
            ->assertRedirect(route('seller.products.from-link'))
            ->assertSessionHasErrors(['url' => 'We couldn\'t read that page. Some shops block this; please fill in the product yourself.']);
        $this->post(route('seller.products.from-link.store'), ['url' => 'https://empty.test/item'])
            ->assertSessionHasErrors(['url' => 'We couldn\'t find a product on that page. Please check the link, or fill in the product yourself.']);
        $this->post(route('seller.products.from-link.store'), ['url' => 'http://127.0.0.1/admin'])->assertSessionHasErrors('url');
        $this->post(route('seller.products.from-link.store'), ['url' => 'not a link'])->assertSessionHasErrors('url');

        // A shop not allowed pre-orders has no such tool
        $plain = $this->seller('Coral Mart');
        $plain->forceFill(['preorders_allowed' => false, 'onboarding_completed_at' => now()])->save();
        $this->actingAs($plain)->get(route('seller.products.index'))->assertOk()->assertDontSee('data-import-from-link', false);
        $this->get(route('seller.products.from-link'))->assertNotFound();
        $this->post(route('seller.products.from-link.store'), ['url' => 'https://camera.test/p/x100'])->assertNotFound();
    }
}
