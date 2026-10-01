<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\FeedToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product / Organization JSON-LD and the Google Merchant + Facebook catalogue feeds.
 */
class StructuredDataAndFeedsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The JSON-LD blocks on a page, decoded.
     */
    protected function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $m);
        $this->assertNotEmpty($m[1], 'No JSON-LD on the page');

        return array_map(function ($json) {
            $data = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5), true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON-LD is not valid JSON');

            return $data;
        }, $m[1]);
    }

    public function test_product_page_has_rich_product_and_breadcrumb_schema(): void
    {
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reefline']);
        $parent = Category::create(['name' => ['en' => 'Home', 'dv' => 'ގެ'], 'slug' => 'home-dept', 'status' => 'active']);
        $category = Category::create(['name' => ['en' => 'Kitchen', 'dv' => 'ބަދިގެ'], 'slug' => 'kitchen', 'status' => 'active', 'parent_id' => $parent->id]);
        $product = Product::factory()->create(['name' => 'Copper kettle', 'brand' => 'Kettleco', 'price' => 350, 'stock_quantity' => 3, 'seller_id' => $seller->id, 'category_id' => $category->id, 'main_image' => null]);
        $product->images()->create(['url' => '/storage/products/kettle.jpg', 'is_main' => true]);
        $product->images()->create(['url' => '/storage/products/kettle-2.jpg', 'is_main' => false]);
        ProductReview::create(['product_id' => $product->id, 'reviewer_name' => 'Aisha', 'reviewer_email' => 'a@example.com', 'rating' => 5, 'comment' => 'Lovely', 'is_approved' => true]);
        ProductReview::create(['product_id' => $product->id, 'reviewer_name' => 'Ali', 'reviewer_email' => 'b@example.com', 'rating' => 4, 'comment' => 'Good', 'is_approved' => true]);
        ProductReview::create(['product_id' => $product->id, 'reviewer_name' => 'Spam', 'reviewer_email' => 's@example.com', 'rating' => 1, 'comment' => 'Nope', 'is_approved' => false]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();
        [$schema] = $this->jsonLd($html);
        [$productLd, $breadcrumbs] = $schema;

        $this->assertSame('Product', $productLd['@type']);
        $this->assertSame('Copper kettle', $productLd['name']);
        $this->assertSame(['@type' => 'Brand', 'name' => 'Kettleco'], $productLd['brand']);
        $this->assertSame([url('/storage/products/kettle.jpg'), url('/storage/products/kettle-2.jpg')], $productLd['image']);
        $this->assertSame(4.5, $productLd['aggregateRating']['ratingValue']);
        $this->assertSame(2, $productLd['aggregateRating']['reviewCount']);
        $this->assertSame('350.00', $productLd['offers']['price']);
        $this->assertSame('MVR', $productLd['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $productLd['offers']['availability']);
        $this->assertSame('https://schema.org/NewCondition', $productLd['offers']['itemCondition']);
        $this->assertSame(now()->addYear()->toDateString(), $productLd['offers']['priceValidUntil']);
        $this->assertSame(['@type' => 'Organization', 'name' => 'Reefline', 'url' => route('sellers.show', $seller)], $productLd['offers']['seller']);

        $this->assertSame('BreadcrumbList', $breadcrumbs['@type']);
        $this->assertSame(['Home', 'Home', 'Kitchen', 'Copper kettle'], array_column($breadcrumbs['itemListElement'], 'name'));
        $this->assertSame([1, 2, 3, 4], array_column($breadcrumbs['itemListElement'], 'position'));
        $this->assertSame(route('categories.show', 'kitchen'), $breadcrumbs['itemListElement'][2]['item']);
    }

    public function test_product_without_reviews_has_no_rating_and_out_of_stock_is_marked(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0, 'brand' => null]);

        [$schema] = $this->jsonLd($this->get(route('products.show', $product))->assertOk()->getContent());
        $this->assertArrayNotHasKey('aggregateRating', $schema[0]);
        $this->assertArrayNotHasKey('brand', $schema[0]);
        $this->assertSame('https://schema.org/OutOfStock', $schema[0]['offers']['availability']);
    }

    public function test_home_page_has_organization_schema_from_settings(): void
    {
        Setting::set([
            'company_trading_name' => 'iruali', 'company_legal_name' => 'Iruali Pvt Ltd', 'contact_phone' => '+960 777 1234',
            'contact_email' => 'hello@iruali.mv', 'company_address' => 'M. Blue House, Malé',
            'social_facebook' => 'https://facebook.com/iruali', 'social_instagram' => 'https://instagram.com/iruali',
        ]);

        [$schema] = $this->jsonLd($this->get('/')->assertOk()->getContent());
        [$website, $org] = $schema;
        $this->assertSame('WebSite', $website['@type']);
        $this->assertSame('Organization', $org['@type']);
        $this->assertSame('Iruali Pvt Ltd', $org['legalName']);
        $this->assertSame('+960 777 1234', $org['telephone']);
        $this->assertSame('hello@iruali.mv', $org['email']);
        $this->assertSame('M. Blue House, Malé', $org['address']['streetAddress']);
        $this->assertSame('MV', $org['address']['addressCountry']);
        $this->assertSame(['https://facebook.com/iruali', 'https://instagram.com/iruali'], $org['sameAs']);

        // Other pages keep the plain WebSite schema
        [$terms] = $this->jsonLd($this->get(route('policies.terms'))->assertOk()->getContent());
        $this->assertSame('WebSite', $terms['@type']);
    }

    public function test_feeds_need_the_token(): void
    {
        $token = FeedToken::get();
        $this->assertSame(40, strlen($token));
        $this->assertSame($token, FeedToken::get(), 'The token is generated once');

        $this->get('/feeds/google-merchant.xml')->assertForbidden();
        $this->get('/feeds/google-merchant.xml?token=wrong')->assertForbidden();
        $this->get('/feeds/facebook-catalog.csv')->assertForbidden();
        $this->get('/feeds/google-merchant.xml?token='.$token)->assertOk();
        $this->get('/feeds/facebook-catalog.csv?token='.$token)->assertOk();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Product feeds')->assertSee('google-merchant.xml?token='.$token, false);

        $this->get('/robots.txt')->assertOk()->assertDontSee('Disallow: /feeds');
    }

    public function test_google_merchant_feed_is_valid_rss_with_variant_groups(): void
    {
        $parent = Category::create(['name' => ['en' => 'Clothing'], 'slug' => 'clothing', 'status' => 'active']);
        $category = Category::create(['name' => ['en' => 'Shirts'], 'slug' => 'shirts', 'status' => 'active', 'parent_id' => $parent->id]);
        $plain = Product::factory()->create(['name' => 'Plain mug', 'brand' => 'Mugco', 'price' => 45, 'stock_quantity' => 2, 'main_image' => '/images/mug.jpg', 'description' => 'A <b>plain</b> mug & more']);
        $shirt = Product::factory()->create(['name' => 'Linen shirt', 'brand' => 'Reef', 'price' => 400, 'has_variants' => true, 'category_id' => $category->id, 'main_image' => null]);
        $shirt->images()->create(['url' => '/storage/products/shirt.jpg', 'is_main' => true]);
        ProductVariant::factory()->for($shirt)->attributes(['Size' => 'M', 'Colour' => 'Blue'])->create(['stock_quantity' => 5, 'price' => 420]);
        ProductVariant::factory()->for($shirt)->attributes(['Size' => 'L', 'Colour' => 'Blue'])->create(['stock_quantity' => 0, 'price' => null, 'price_adjustment' => 10]);
        Product::factory()->create(['name' => 'Hidden', 'is_active' => false]);

        $response = $this->get('/feeds/google-merchant.xml?token='.FeedToken::get())->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $xml = $response->getContent();

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'The feed is not well-formed XML');
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');

        $this->assertSame(3, $xpath->query('//item')->length);
        $this->assertStringNotContainsString('Hidden', $xml);

        $ids = array_map(fn ($n) => $n->nodeValue, iterator_to_array($xpath->query('//item/g:id')));
        $this->assertSame(['P'.$plain->id, 'P'.$shirt->id.'-V'.$shirt->variants[0]->id, 'P'.$shirt->id.'-V'.$shirt->variants[1]->id], $ids);

        $groups = array_map(fn ($n) => $n->nodeValue, iterator_to_array($xpath->query('//item/g:item_group_id')));
        $this->assertSame(['P'.$shirt->id, 'P'.$shirt->id], $groups);

        $mug = $xpath->query('//item[g:id="P'.$plain->id.'"]')->item(0);
        $this->assertSame('45.00 MVR', $xpath->query('g:price', $mug)->item(0)->nodeValue);
        $this->assertSame('in_stock', $xpath->query('g:availability', $mug)->item(0)->nodeValue);
        $this->assertSame('Mugco', $xpath->query('g:brand', $mug)->item(0)->nodeValue);
        $this->assertSame('new', $xpath->query('g:condition', $mug)->item(0)->nodeValue);
        $this->assertSame('A plain mug & more', $xpath->query('description', $mug)->item(0)->nodeValue);
        $this->assertSame(url('/images/mug.jpg'), $xpath->query('g:image_link', $mug)->item(0)->nodeValue);
        $this->assertSame(route('products.show', $plain->slug), $xpath->query('link', $mug)->item(0)->nodeValue);
        $this->assertSame('MV', $xpath->query('g:shipping/g:country', $mug)->item(0)->nodeValue);

        $m = $xpath->query('//item[g:id="P'.$shirt->id.'-V'.$shirt->variants[0]->id.'"]')->item(0);
        $this->assertSame('Linen shirt - M / Blue', $xpath->query('title', $m)->item(0)->nodeValue);
        $this->assertSame('420.00 MVR', $xpath->query('g:price', $m)->item(0)->nodeValue);
        $this->assertSame('M', $xpath->query('g:size', $m)->item(0)->nodeValue);
        $this->assertSame('Blue', $xpath->query('g:color', $m)->item(0)->nodeValue);
        $this->assertSame('Clothing > Shirts', $xpath->query('g:product_type', $m)->item(0)->nodeValue);
        $this->assertSame(url('/storage/products/shirt.jpg'), $xpath->query('g:image_link', $m)->item(0)->nodeValue);

        $l = $xpath->query('//item[g:id="P'.$shirt->id.'-V'.$shirt->variants[1]->id.'"]')->item(0);
        $this->assertSame('410.00 MVR', $xpath->query('g:price', $l)->item(0)->nodeValue);
        $this->assertSame('out_of_stock', $xpath->query('g:availability', $l)->item(0)->nodeValue);
    }

    public function test_facebook_catalog_csv_has_the_standard_columns(): void
    {
        $product = Product::factory()->create(['name' => 'Coral lamp', 'brand' => 'Lumo', 'price' => 120.5, 'stock_quantity' => 1, 'main_image' => '/images/lamp.jpg']);

        $csv = $this->get('/feeds/facebook-catalog.csv?token='.FeedToken::get())->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();

        $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));
        $this->assertSame(['id', 'title', 'description', 'availability', 'condition', 'price', 'link', 'image_link', 'brand', 'item_group_id', 'additional_image_link', 'product_type', 'size', 'color'], $rows[0]);
        $row = array_combine($rows[0], $rows[1]);
        $this->assertSame('P'.$product->id, $row['id']);
        $this->assertSame('Coral lamp', $row['title']);
        $this->assertSame('in stock', $row['availability']);
        $this->assertSame('new', $row['condition']);
        $this->assertSame('120.50 MVR', $row['price']);
        $this->assertSame(route('products.show', $product->slug), $row['link']);
        $this->assertSame(url('/images/lamp.jpg'), $row['image_link']);
        $this->assertSame('Lumo', $row['brand']);
    }
}
