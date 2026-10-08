<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\BrandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandsTest extends TestCase
{
    use RefreshDatabase;

    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Traders']);
        $this->category = Category::factory()->create(['status' => 'active', 'slug' => 'fishing-marine', 'name' => ['en' => 'Fishing & marine', 'dv' => 'މަސްވެރިކަން']]);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'seller_id' => $this->shop->id,
            'is_active' => true,
            'stock_quantity' => 5,
            'price' => 100,
            'compare_price' => null,
        ], $attributes));
    }

    public function test_names_that_differ_only_in_case_spaces_or_punctuation_are_one_brand(): void
    {
        $first = $this->product(['brand' => 'Samsung']);
        $brand = $first->brandModel;
        $this->assertSame(['Samsung', 'samsung', 'samsung'], [$brand->name, $brand->slug, $brand->key]);
        $this->assertSame($this->shop->id, $brand->created_by);

        $second = $this->product(['brand' => '  SAMSUNG  ']);
        $this->assertSame($brand->id, $second->brand_id);
        $this->assertSame('Samsung', $second->brand, 'the brand keeps its own spelling');
        $this->assertStringContainsString('samsung', $second->search_text);

        $martens = $this->product(['brand' => 'Dr. Martens']);
        $this->assertSame('dr-martens', $martens->brandModel->slug);
        $this->assertSame($martens->brand_id, $this->product(['brand' => 'DR MARTENS'])->brand_id);

        $this->assertSame(2, Brand::count());
    }

    public function test_no_brand_answers_and_changes_keep_the_link_right(): void
    {
        foreach (['N/A', 'Generic', 'no brand', '', ' - '] as $typed) {
            $product = $this->product(['brand' => $typed]);
            $this->assertNull($product->brand_id, "“{$typed}” is not a brand");
            $this->assertNull($product->brand);
        }
        $this->assertSame(0, Brand::count());

        $product = $this->product(['brand' => 'Reefline']);
        $product->update(['brand' => 'Bluewave']);
        $this->assertSame('Bluewave', $product->fresh()->brandModel->name);

        $reefline = Brand::where('key', 'reefline')->first();
        $product->update(['brand_id' => $reefline->id]);
        $this->assertSame('Reefline', $product->fresh()->brand, 'setting the brand record sets the name too');

        $product->update(['brand' => null]);
        $this->assertNull($product->fresh()->brand_id);
    }

    public function test_existing_brand_names_are_linked_using_the_most_common_spelling(): void
    {
        $products = collect(['SAMSUNG', 'Samsung', 'apple', 'apple', 'Apple', 'N/A', 'LG'])
            ->map(fn ($name) => tap($this->product(['brand' => null]), fn ($p) => DB::table('products')->where('id', $p->id)->update(['brand' => $name, 'search_text' => 'old']))->id);
        Brand::query()->delete();

        $this->artisan('brands:sync')->expectsOutputToContain('Brands created: 3. Products linked: 7.')->assertSuccessful();

        $this->assertEqualsCanonicalizing(['Samsung', 'apple', 'LG'], Brand::pluck('name')->all(), 'ties go to normal capitals, otherwise the commonest spelling wins');
        $names = Product::whereIn('id', $products)->orderBy('id')->pluck('brand')->all();
        $this->assertSame(['Samsung', 'Samsung', 'apple', 'apple', 'apple', null, 'LG'], $names);
        $this->assertSame(0, Product::whereNotNull('brand')->whereNull('brand_id')->count());
        $this->assertStringContainsString('samsung', Product::find($products[0])->search_text);

        $this->artisan('brands:sync')->expectsOutputToContain('Brands created: 0. Products linked: 0.');
    }

    public function test_the_directory_lists_brands_a_to_z_with_counts(): void
    {
        $this->product(['brand' => 'Apple']);
        $this->product(['brand' => 'Apple']);
        $this->product(['brand' => 'Ångström']);
        $this->product(['brand' => '3M']);
        $this->product(['brand' => 'Bose']);
        $this->product(['brand' => 'Zephyr', 'is_active' => false]);

        $this->get('/brands')->assertOk()
            ->assertSee('<title>Brands - iruali</title>', false)
            ->assertSee('href="'.url('/brands/apple').'"', false)
            ->assertSeeInOrder(['Apple', '2 products', 'Bose', '3M'])
            ->assertSee('Ångström')
            ->assertSee('id="letter-A"', false)->assertSee('id="letter-other"', false)
            ->assertSee('href="#letter-B"', false)
            ->assertDontSee('href="#letter-Z"', false)
            ->assertDontSee('Zephyr')
            ->assertDontSee('Popular brands');

        foreach (range(1, 12) as $n) {
            $this->product(['brand' => 'Brand '.$n]);
        }
        $this->get('/brands')->assertSee('Popular brands');
    }

    public function test_a_brand_page_shows_logo_description_shops_and_departments(): void
    {
        Storage::fake('public');
        $other = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Hulhumalé Style']);
        $apparel = Category::factory()->create(['status' => 'active', 'slug' => 'apparel', 'name' => ['en' => 'Apparel', 'dv' => 'ހެދުން']]);
        $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Hand line kit']]);
        $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Dry bag']]);
        $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Rash guard'], 'seller_id' => $other->id, 'category_id' => $apparel->id]);
        $brand = Brand::where('slug', 'reefline')->first();
        $brand->logo = 'brands/reefline.png';
        $brand->replaceTranslations('description', ['en' => 'Fishing and snorkel gear made for the reef.', 'dv' => 'ފަރަށް ހެދި މަސްވެރިކަމުގެ ސާމާނު.']);
        $brand->save();

        $this->get('/brands/reefline')->assertOk()
            ->assertSee('<title>Reefline - iruali</title>', false)
            ->assertSee('Fishing and snorkel gear made for the reef.')
            ->assertSee('/storage/brands/reefline.png', false)
            ->assertSee('3 products')->assertSee('sold by 2 shops')
            ->assertSee('href="'.route('sellers.show', $this->shop).'"', false)->assertSee('Hulhumalé Style')
            ->assertSee('?category=apparel', false)->assertSee('Apparel')
            ->assertSee('href="'.route('brands.index').'"', false)
            ->assertSee('Hand line kit')->assertSee('Rash guard');

        $this->get('/brands/reefline?category=apparel')->assertOk()->assertSee('Rash guard')->assertDontSee('Hand line kit');

        $this->get('/dv/brands/reefline')->assertOk()
            ->assertSee('lang="dv"', false)
            ->assertSee('ފަރަށް ހެދި މަސްވެރިކަމުގެ ސާމާނު.')
            ->assertSee('href="'.url('/dv/brands').'"', false);
    }

    public function test_every_brand_has_one_address(): void
    {
        $this->product(['brand' => 'Reefline']);

        $this->get('/brands/Reefline')->assertStatus(301)->assertRedirect('/brands/reefline');
        $this->get('/brands/REEFLINE?sort=newest')->assertStatus(301)->assertRedirect('/brands/reefline?sort=newest');
        $this->get('/dv/brands/Reefline')->assertStatus(301)->assertRedirect('/dv/brands/reefline');
        $this->get('/brands/reefline')->assertOk()->assertSee('<h1 class="font-display text-xl lg:text-3xl font-bold text-dark">Reefline</h1>', false);

        BrandAlias::create(['brand_id' => Brand::first()->id, 'slug' => 'reef-line']);
        $this->get('/brands/reef-line')->assertStatus(301)->assertRedirect('/brands/reefline');

        $this->get('/brands/nobody')->assertNotFound();
        $this->product(['brand' => 'Hidden', 'is_active' => false]);
        $this->get('/brands/hidden')->assertNotFound();
    }

    public function test_brand_pages_with_few_products_stay_out_of_search_engines_and_the_sitemap(): void
    {
        $this->product(['brand' => 'Solo']);
        foreach (range(1, Brand::INDEX_MIN_PRODUCTS) as $n) {
            $this->product(['brand' => 'Bigrange']);
        }

        $this->get('/brands/solo')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.url('/brands/solo').'">', false);
        $page = $this->get('/brands/bigrange')->assertOk()->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('"@type":"CollectionPage"', false)->assertSee('"@type":"BreadcrumbList"', false)->getContent();
        $this->assertStringContainsString('Buy Bigrange from shops across the Maldives on iruali.', $page);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.url('/brands').'</loc>', $sitemap);
        $this->assertStringContainsString('<loc>'.url('/brands/bigrange').'</loc>', $sitemap);
        $this->assertStringContainsString('<loc>'.url('/dv/brands/bigrange').'</loc>', $sitemap);
        $this->assertStringNotContainsString('/brands/solo', $sitemap);
    }

    public function test_brands_are_reachable_from_search_products_the_menu_and_the_home_page(): void
    {
        $product = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Hand line kit']]);
        $this->product(['brand' => 'Bluewave']);

        $this->getJson('/search/suggest?q=reef')->assertOk()
            ->assertJsonPath('brands.0.name', 'Reefline')
            ->assertJsonPath('brands.0.url', url('/brands/reefline'));

        $this->get(route('products.show', $product))->assertOk()->assertSee('href="'.url('/brands/reefline').'"', false);

        $this->get('/')->assertOk()
            ->assertSee('Shop by brand')->assertSee('See all brands')
            ->assertSee('href="'.url('/brands').'"', false);
    }

    public function test_shops_pick_from_the_brand_list_on_the_product_form(): void
    {
        $this->product(['brand' => 'Samsung']);
        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        $this->actingAs($this->shop)->get('/seller/products/create')->assertOk()
            ->assertSee('list="brand-options"', false)->assertSee('<option value="Samsung"></option>', false);

        $this->actingAs($this->shop)->post('/seller/products', [
            'name_en' => 'Galaxy charger',
            'sku' => 'GAL-CHG',
            'category_id' => $this->category->id,
            'price' => 150,
            'stock_quantity' => 4,
            'brand' => ' samsung ',
        ])->assertSessionHasNoErrors();

        $product = Product::where('sku', 'GAL-CHG')->firstOrFail();
        $this->assertSame('Samsung', $product->brand);
        $this->assertSame(Brand::where('key', 'samsung')->value('id'), $product->brand_id);
        $this->assertSame(1, Brand::count());
    }

    public function test_popular_brands_are_the_best_sellers_first(): void
    {
        $this->product(['brand' => 'Many']);
        $this->product(['brand' => 'Many']);
        $sold = $this->product(['brand' => 'Seller']);
        $order = \App\Models\Order::factory()->create(['payment_status' => 'paid']);
        $order->items()->create(['product_id' => $sold->id, 'quantity' => 3, 'price' => 100]);

        $this->assertSame(['Seller', 'Many'], app(BrandService::class)->popular(5)->pluck('name')->all());
    }
}
