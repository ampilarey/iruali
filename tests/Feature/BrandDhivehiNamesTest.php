<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\BrandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Brands with a name in Thaana: shown on Dhivehi pages, matched when shops type it, found by
 * Dhivehi searches. products.brand keeps the English name.
 */
class BrandDhivehiNamesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Traders']);
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'seller_id' => $this->shop->id,
            'is_active' => true,
            'stock_quantity' => 5,
            'compare_price' => null,
        ], $attributes));
    }

    protected function brand(string $name): Brand
    {
        return $this->product(['brand' => $name])->brandModel;
    }

    protected function form(Brand $brand, array $changes = []): array
    {
        return array_merge(['name' => $brand->name, 'name_dv' => $brand->name_dv, 'slug' => $brand->slug, 'description_en' => '', 'description_dv' => '', 'reviewed' => 1], $changes);
    }

    /** Save a Dhivehi name the way an admin does, through Admin → Brands → Edit. */
    protected function nameInDhivehi(Brand $brand, ?string $nameDv): Brand
    {
        $this->actingAs($this->admin)->put(route('admin.brands.update', $brand), $this->form($brand, ['name_dv' => $nameDv]))->assertSessionHasNoErrors();

        return $brand->refresh();
    }

    public function test_admins_give_a_brand_a_dhivehi_name_that_shops_and_old_links_match(): void
    {
        $samsung = $this->brand('Samsung');

        $this->actingAs($this->admin)->get(route('admin.brands.edit', $samsung))->assertOk()
            ->assertSee('Name in Dhivehi (optional)')
            ->assertSee('id="name_dv" name="name_dv"', false);

        $this->put(route('admin.brands.update', $samsung), $this->form($samsung, ['name_dv' => '  ސާމްސަންގް  ']))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.brands.edit', $samsung));
        $samsung->refresh();
        $this->assertSame(['Samsung', 'ސާމްސަންގް'], [$samsung->name, $samsung->name_dv]);
        $this->assertSame(['ސާމްސަންގް'], BrandAlias::where('brand_id', $samsung->id)->whereNotNull('key')->pluck('key')->all());
        $this->assertSame(['name_dv' => '— → ސާމްސަންގް', 'reviewed' => 'yes'], AuditLog::latest('id')->value('changes'));
        $this->get(route('admin.brands.edit', $samsung))->assertSee('value="ސާމްސަންގް"', false)->assertSee('Name matching “ސާމްސަންގް”');
        $this->get('/admin/brands?q='.urlencode('ސާމް'))->assertOk()->assertSee('/brands/samsung');

        // A shop typing the Thaana name gets the brand; the product keeps the English name
        $typed = $this->product(['brand' => 'ސާމްސަންގް']);
        $this->assertSame($samsung->id, $typed->brand_id);
        $this->assertSame('Samsung', $typed->brand);
        $this->assertSame(1, Brand::count());

        // So do addresses made of the Thaana name
        $this->get('/brands/'.rawurlencode('ސާމްސަންގް'))->assertStatus(301)->assertRedirect('/brands/samsung');
        $this->get('/dv/brands/'.rawurlencode('ސާމްސަންގް'))->assertStatus(301)->assertRedirect('/dv/brands/samsung');

        // A new Dhivehi name keeps the old one matched, as a rename keeps the old name
        $this->nameInDhivehi($samsung, 'ސެމްސަންގް');
        $this->assertSame(['ސާމްސަންގް', 'ސެމްސަންގް'], BrandAlias::where('brand_id', $samsung->id)->whereNotNull('key')->orderBy('id')->pluck('key')->all());
        $this->assertSame($samsung->id, $this->product(['brand' => 'ސާމްސަންގް'])->brand_id);
        $this->assertSame($samsung->id, $this->product(['brand' => 'ސެމްސަންގް'])->brand_id);

        // Cleared: English everywhere again, the Thaana names still match
        $this->nameInDhivehi($samsung, '');
        $this->assertNull($samsung->name_dv);
        $this->assertSame(2, BrandAlias::where('brand_id', $samsung->id)->whereNotNull('key')->count());
        $this->get('/dv/brands/samsung')->assertOk()->assertSee('<title>Samsung - iruali</title>', false);
    }

    public function test_a_dhivehi_name_that_belongs_to_another_brand_is_refused(): void
    {
        $samsung = $this->brand('Samsung');
        $this->nameInDhivehi($this->brand('Apple'), 'އެޕަލް');
        $this->brand('ނޯކިއާ'); // a shop typed this brand in Thaana: it is that brand's own name
        BrandAlias::create(['brand_id' => $this->brand('Old Label')->id, 'key' => 'އޯލްޑް']);

        foreach (['އެޕަލް' => 'Apple', 'ނޯކިއާ' => 'ނޯކިއާ', 'އޯލްޑް' => 'Old Label'] as $typed => $owner) {
            $this->actingAs($this->admin)->from(route('admin.brands.edit', $samsung))
                ->put(route('admin.brands.update', $samsung), $this->form($samsung, ['name_dv' => $typed]))
                ->assertRedirect(route('admin.brands.edit', $samsung))
                ->assertSessionHasErrors(['name_dv' => "“{$owner}” already has this name, now or as an old name. Use another Dhivehi name, or merge the two brands."]);
        }
        $this->put(route('admin.brands.update', $samsung), $this->form($samsung, ['name_dv' => str_repeat('ސ', 121)]))->assertSessionHasErrors('name_dv');
        $this->put(route('admin.brands.update', $samsung), $this->form($samsung, ['name_dv' => '، ،']))->assertSessionHasErrors(['name_dv' => 'That is not a brand name.']);
        $this->assertNull($samsung->fresh()->name_dv);
        $this->assertSame(0, BrandAlias::where('brand_id', $samsung->id)->count());

        // Writing the brand's own name is not a clash (and adds nothing to match)
        $this->nameInDhivehi($samsung, 'SAMSUNG');
        $this->assertSame(0, BrandAlias::where('brand_id', $samsung->id)->count());
    }

    public function test_dhivehi_pages_show_the_dhivehi_name_and_english_pages_the_english_one(): void
    {
        $samsung = $this->nameInDhivehi($this->brand('Samsung'), 'ސާމްސަންގް');
        $this->product(['brand' => 'Samsung', 'slug' => 'galaxy-charger', 'name' => ['en' => 'Galaxy charger', 'dv' => 'ގެލެކްސީ ޗާޖަރ']]);
        $this->product(['brand' => 'Bose', 'name' => ['en' => 'Headphones', 'dv' => 'ހެޑްފޯން']]);

        $this->get('/dv/brands/samsung')->assertOk()
            ->assertSee('<title>ސާމްސަންގް - iruali</title>', false)
            ->assertSee('<h1 class="font-display text-xl lg:text-3xl font-bold text-dark">ސާމްސަންގް</h1>', false)
            ->assertSee('<bdi>Samsung</bdi>', false)
            ->assertSee('<span class="text-dark font-medium">ސާމްސަންގް</span>', false)
            ->assertSee('އިރުއަލީގައިވާ ސާމްސަންގް ގެ ހުރިހާ ތަކެތި.')
            ->assertSee('"alternateName":"ސާމްސަންގް"', false)
            ->assertSee('"name":"ސާމްސަންގް","item":"'.url('/dv/brands/samsung').'"', false);
        $this->get('/brands/samsung')->assertOk()
            ->assertSee('<title>Samsung - iruali</title>', false)
            ->assertSee('<h1 class="font-display text-xl lg:text-3xl font-bold text-dark">Samsung</h1>', false)
            ->assertSee('Everything from Samsung on iruali.')
            ->assertDontSee('<bdi>Samsung</bdi>', false);

        // The A–Z list keeps the English letter; Dhivehi shows both names, and the filter knows both
        $this->get('/dv/brands')->assertOk()
            ->assertSeeInOrder(['id="letter-S"', 'ސާމްސަންގް', '<bdi>Samsung</bdi>'], false)
            ->assertSee('data-brand-name="samsung ސާމްސަންގް"', false);
        $this->get('/brands')->assertOk()
            ->assertSee('data-brand-name="samsung ސާމްސަންގް"', false)
            ->assertDontSee('<bdi>Samsung</bdi>', false);

        // Shop by brand on the home page, the product page's brand link, the catalogue's brand filter
        $this->get('/dv')->assertOk()->assertSee('ސާމްސަންގް')->assertSee('<bdi>Samsung</bdi>', false);
        $this->get('/dv/products/galaxy-charger')->assertOk()->assertSee('>ސާމްސަންގް</a>', false);
        $this->get('/products/galaxy-charger')->assertOk()->assertSee('>Samsung</a>', false);
        $this->get('/dv/shop')->assertOk()
            ->assertSee('name="brand[]" value="Samsung"', false)
            ->assertSee('<span class="flex-1 truncate">ސާމްސަންގް</span>', false)
            ->assertSee('<span class="flex-1 truncate">Bose</span>', false);
        $this->get('/shop')->assertOk()->assertSee('<span class="flex-1 truncate">Samsung</span>', false);
        $this->get('/dv/shop?brand[]=Samsung')->assertOk()->assertSee('ގެލެކްސީ ޗާޖަރ')->assertDontSee('ހެޑްފޯން');
    }

    public function test_product_cards_the_compare_table_and_filter_chips_show_the_dhivehi_name(): void
    {
        $this->nameInDhivehi($this->brand('Samsung'), 'ސާމްސަންގް');
        $charger = $this->product(['brand' => 'Samsung', 'slug' => 'galaxy-charger', 'name' => ['en' => 'Galaxy charger', 'dv' => 'ގެލެކްސީ ޗާޖަރ']]);
        $headphones = $this->product(['brand' => 'Bose', 'slug' => 'bose-headphones', 'name' => ['en' => 'Headphones', 'dv' => 'ހެޑްފޯން']]);
        $grid = fn (string $brand) => '<p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 truncate">'.$brand.'</p>';
        $list = fn (string $brand) => '<p class="text-xs font-semibold uppercase tracking-wide text-gray-500">'.$brand.'</p>';

        // The catalogue's cards in both layouts; a brand without a Dhivehi name keeps its own
        $this->get('/dv/shop')->assertOk()
            ->assertSee($grid('ސާމްސަންގް'), false)->assertSee($grid('Bose'), false)->assertDontSee($grid('Samsung'), false);
        $this->get('/dv/shop?view=list')->assertOk()->assertSee($list('ސާމްސަންގް'), false)->assertDontSee($list('Samsung'), false);
        $this->get('/shop')->assertOk()->assertSee($grid('Samsung'), false)->assertDontSee($grid('ސާމްސަންގް'), false);
        $this->get('/shop?view=list')->assertOk()->assertSee($list('Samsung'), false);

        // The rows on the home page and under a product (more from the shop, related products)
        $this->get('/dv')->assertOk()->assertSee($grid('ސާމްސަންގް'), false)->assertDontSee($grid('Samsung'), false);
        $this->get('/')->assertOk()->assertSee($grid('Samsung'), false);
        $this->get('/dv/products/bose-headphones')->assertOk()->assertSee($grid('ސާމްސަންގް'), false)->assertDontSee($grid('Samsung'), false);

        // A campaign page (its language follows the session)
        $campaign = Campaign::factory()->create(['name' => 'Eid sale', 'headline' => ['en' => 'Eid sale']]);
        CampaignProduct::create(['campaign_id' => $campaign->id, 'product_id' => $charger->id, 'seller_id' => $charger->seller_id, 'approved_at' => now()]);
        $this->withSession(['locale' => 'dv'])->get(route('campaigns.show', $campaign))->assertOk()->assertSee($grid('ސާމްސަންގް'), false);
        $this->withSession(['locale' => 'en'])->get(route('campaigns.show', $campaign))->assertOk()->assertSee($grid('Samsung'), false);

        // The compare table's brand row
        $this->withSession(['compare' => [$charger->id, $headphones->id]])->get('/dv/compare')->assertOk()
            ->assertSeeInOrder(['ސާމްސަންގް', 'Bose'])->assertDontSee('Samsung');

        // The chip for a picked brand is labelled like the brand filter (the link keeps the English value)
        $chip = '<a href="'.url('/dv/shop').'" class="inline-flex items-center gap-1.5 ps-3 pe-2 py-1 rounded-full bg-primary-50 text-primary-800 text-sm font-medium hover:bg-primary-100">';
        $this->get('/dv/shop?brand[]=Samsung')->assertOk()->assertSee($chip."\n                                ސާމްސަންގް<svg", false);
        $this->get('/shop?brand[]=Samsung')->assertOk()->assertSee(str_replace('/dv/shop', '/shop', $chip)."\n                                Samsung<svg", false);

        // One query loads the brands of a whole page of cards, however many there are
        $brandQueries = 0;
        DB::listen(function ($query) use (&$brandQueries) {
            $brandQueries += (int) (bool) preg_match('/from [`"]?brands[`"]?/i', $query->sql);
        });
        $this->get('/dv/shop')->assertOk();
        $withTwoProducts = $brandQueries;
        foreach (['Apple' => 'އެޕަލް', 'Sony' => 'ސޯނީ', 'Canon' => 'ކެނަން', 'Philips' => 'ފިލިޕްސް'] as $name => $nameDv) {
            $this->brand($name)->forceFill(['name_dv' => $nameDv])->save();
        }
        $brandQueries = 0;
        $this->get('/dv/shop')->assertOk()->assertSee($grid('ކެނަން'), false);
        $this->assertSame($withTwoProducts, $brandQueries, 'the cards add no query per product');
    }

    public function test_thaana_searches_and_suggestions_find_the_brand(): void
    {
        $samsung = $this->brand('Samsung');
        $charger = $this->product(['brand' => 'Samsung', 'name' => ['en' => 'Galaxy charger']]);
        $this->product(['brand' => 'Bose', 'name' => ['en' => 'Headphones']]);
        $this->assertStringNotContainsString('ސާމްސަންގް', $charger->fresh()->search_text);

        $this->nameInDhivehi($samsung, 'ސާމްސަންގް');
        $this->assertStringContainsString('ސާމްސަންގް', $charger->fresh()->search_text, "naming the brand rewrites its products' search text");
        $cable = $this->product(['brand' => 'Samsung', 'name' => ['en' => 'USB cable']]);
        $this->assertStringContainsString('ސާމްސަންގް', $cable->fresh()->search_text, 'new products of the brand have it too');

        $this->get('/search?q='.urlencode('ސާމްސަންގް'))->assertOk()->assertSee('Galaxy charger')->assertSee('USB cable')->assertDontSee('Headphones');
        $this->get('/dv/search?q='.urlencode('ސާމް'))->assertOk()->assertSee('Galaxy charger');

        $suggestions = $this->getJson('/dv/search/suggest?q='.urlencode('ސާމް'))->assertOk()
            ->assertJsonPath('brands.0.name', 'ސާމްސަންގް')
            ->assertJsonPath('brands.0.url', url('/dv/brands/samsung'));
        $this->assertContains('Galaxy charger', $suggestions->json('products.*.name'));
        $this->assertNotContains('Headphones', $suggestions->json('products.*.name'));
        $this->getJson('/dv/search/suggest?q=sams')->assertOk()->assertJsonPath('brands.0.name', 'ސާމްސަންގް');
        $this->getJson('/search/suggest?q='.urlencode('ސާމް'))->assertOk()
            ->assertJsonPath('brands.0.name', 'Samsung')
            ->assertJsonPath('brands.0.url', url('/brands/samsung'));

        // A new Dhivehi name replaces the old one in the search text
        $this->nameInDhivehi($samsung, 'ސެމްސަންގް');
        $text = $charger->fresh()->search_text;
        $this->assertStringContainsString('ސެމްސަންގް', $text);
        $this->assertStringNotContainsString('ސާމްސަންގް', $text);
    }

    public function test_merging_keeps_the_dhivehi_name_matched_by_the_brand_it_joins(): void
    {
        $samsung = $this->brand('Samsung');
        $duplicate = $this->nameInDhivehi($this->brand('Samsung Electronics'), 'ސާމްސަންގް');
        $moved = $this->product(['brand' => 'Samsung Electronics', 'name' => ['en' => 'Smart TV']]);

        app(BrandService::class)->merge($duplicate, $samsung);

        $this->assertSame($samsung->id, $this->product(['brand' => 'ސާމްސަންގް'])->brand_id);
        $this->assertStringNotContainsString('ސާމްސަންގް', $moved->fresh()->search_text, 'the products take the names of the brand they join');
        $this->assertNull($samsung->fresh()->name_dv, 'the Dhivehi name of a duplicate is not shown for the other brand');
    }
}
