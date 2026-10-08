<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\BrandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Authorised sellers: admins confirm which shops may sell a brand (with an audit trail), and
 * shoppers see an "Authorised seller" badge on those shops' products and on the brand page.
 */
class BrandAuthorisedSellersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $shop;

    protected User $otherShop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Aisha Admin']);
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->shop = $this->seller('Reef Traders', 'reef@example.com');
        $this->otherShop = $this->seller('Atoll Electronics', 'atoll@example.com');
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function seller(string $shopName, string $email, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['is_seller' => true, 'seller_approved' => true, 'business_name' => $shopName, 'email' => $email], $attributes));
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

    protected function authorise(Brand $brand, User $seller): void
    {
        $brand->authorisedSellers()->attach($seller->id, ['authorised_by' => $this->admin->id]);
    }

    public function test_an_admin_authorises_a_shop_that_sells_the_brand_and_removes_it_again_with_an_audit_trail(): void
    {
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $this->product(['brand' => 'Reefline']);
        $edit = route('admin.brands.edit', $brand);

        $this->actingAs($this->admin)->get($edit)->assertOk()
            ->assertSee('id="authorised-sellers"', false)
            ->assertSee('No authorised sellers yet.')
            ->assertSee('<option value="'.$this->shop->id.'">Reef Traders (2 products)</option>', false);

        $this->post(route('admin.brands.sellers.store', $brand), ['seller_id' => $this->shop->id])
            ->assertRedirect($edit.'#authorised-sellers')
            ->assertSessionHas('success', 'Reef Traders is now an authorised seller of Reefline.');

        $this->assertDatabaseHas('brand_authorised_sellers', ['brand_id' => $brand->id, 'seller_id' => $this->shop->id, 'authorised_by' => $this->admin->id]);
        $log = AuditLog::latest('id')->first();
        $this->assertSame('brand.seller_authorised', $log->action);
        $this->assertSame([Brand::class, $brand->id], [$log->subject_type, $log->subject_id]);
        $this->assertSame('Reef Traders', $log->changes['seller']);
        $this->assertSame($this->admin->id, $log->user_id);

        $this->get($edit)->assertOk()
            ->assertSeeInOrder(['data-authorised-sellers', 'Reef Traders', 'Since '.now()->format('d M Y'), 'by Aisha Admin', 'Remove'], false)
            ->assertDontSee('<option value="'.$this->shop->id.'">', false)
            ->assertSee('No other shop has Reefline products on sale.');

        // A second click changes nothing
        $this->post(route('admin.brands.sellers.store', $brand), ['seller_id' => $this->shop->id])
            ->assertSessionHas('success', 'Reef Traders is already an authorised seller of Reefline.');
        $this->assertSame(1, DB::table('brand_authorised_sellers')->count());
        $this->assertSame(1, AuditLog::where('action', 'brand.seller_authorised')->count());

        $this->delete(route('admin.brands.sellers.destroy', [$brand, $this->shop]))
            ->assertRedirect($edit.'#authorised-sellers')
            ->assertSessionHas('success', 'Reef Traders is no longer an authorised seller of Reefline.');
        $this->assertDatabaseMissing('brand_authorised_sellers', ['brand_id' => $brand->id, 'seller_id' => $this->shop->id]);
        $this->assertSame('brand.seller_unauthorised', AuditLog::latest('id')->value('action'));

        // Both actions can be picked on the audit page
        $this->get('/admin/audit')->assertOk()->assertSee('Brand authorised seller added')->assertSee('Brand authorised seller removed');
        $this->get('/admin/audit?action=brand.seller_authorised')->assertOk()->assertSee('Reef Traders');
    }

    public function test_any_approved_shop_can_be_found_by_name_or_email_and_authorised(): void
    {
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $pending = $this->seller('Atoll Pending Shop', 'pending@example.com', ['seller_approved' => false]);
        $suspended = $this->seller('Atoll Suspended Shop', 'suspended@example.com');
        $suspended->forceFill(['status' => 'suspended'])->save();
        User::factory()->create(['name' => 'Atoll Customer', 'email' => 'customer@example.com']);
        $edit = route('admin.brands.edit', $brand);

        $this->actingAs($this->admin)->get($edit.'?seller_q=atoll')->assertOk()
            ->assertSee('data-seller-matches', false)
            ->assertSee('Atoll Electronics')->assertSee('atoll@example.com')
            ->assertDontSee('Atoll Pending Shop')->assertDontSee('Atoll Suspended Shop')->assertDontSee('Atoll Customer');
        $this->get($edit.'?seller_q='.urlencode('atoll@example'))->assertOk()->assertSee('Atoll Electronics');
        $this->get($edit.'?seller_q=nobody-at-all')->assertOk()->assertSee('No other approved shop matches that search.');
        $this->get($edit.'?seller_q=%25')->assertOk()->assertSee('No other approved shop matches that search.');

        // Shops that are not approved sellers are refused
        foreach ([$pending, $suspended] as $notApproved) {
            $this->post(route('admin.brands.sellers.store', $brand), ['seller_id' => $notApproved->id])
                ->assertSessionHasErrors(['seller_id' => 'Only approved shops can be authorised sellers.']);
        }
        $this->post(route('admin.brands.sellers.store', $brand), [])->assertSessionHasErrors('seller_id');
        $this->assertSame(0, DB::table('brand_authorised_sellers')->count());

        // A shop that does not list the brand yet can still be authorised
        $this->post(route('admin.brands.sellers.store', $brand), ['seller_id' => $this->otherShop->id])->assertSessionHasNoErrors();
        $this->assertTrue($brand->fresh()->isAuthorisedSeller($this->otherShop->id));
        $this->get($edit.'?seller_q=atoll')->assertOk()->assertSee('No other approved shop matches that search.');
    }

    public function test_only_admins_manage_authorised_sellers(): void
    {
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $this->authorise($brand, $this->otherShop);

        $this->post(route('admin.brands.sellers.store', $brand), ['seller_id' => $this->shop->id])->assertRedirect(route('login'));

        foreach (['support', 'finance', 'seller'] as $role) {
            $user = User::factory()->create();
            $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);
            $this->actingAs($user)->post(route('admin.brands.sellers.store', $brand), ['seller_id' => $this->shop->id])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.brands.sellers.destroy', [$brand, $this->otherShop]))->assertForbidden();
        }

        $this->assertSame([$this->otherShop->id], DB::table('brand_authorised_sellers')->pluck('seller_id')->all());
        $this->assertSame(0, AuditLog::where('action', 'like', 'brand.seller_%')->count());
    }

    public function test_the_product_page_shows_the_badge_only_for_an_authorised_shop_of_the_products_brand(): void
    {
        $authorised = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Hand line kit']]);
        $otherShops = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Dry bag'], 'seller_id' => $this->otherShop->id]);
        $otherBrand = $this->product(['brand' => 'Bluewave', 'name' => ['en' => 'Snorkel']]);
        $unbranded = $this->product(['brand' => null, 'name' => ['en' => 'Plain rope']]);
        $this->authorise($authorised->brandModel, $this->shop);

        $this->get(route('products.show', $authorised))->assertOk()
            ->assertSee('data-authorised-seller', false)
            ->assertSeeInOrder(['Reef Traders', 'Authorised seller', 'iruali has confirmed this shop is an authorised seller of Reefline.']);

        foreach ([$otherShops, $otherBrand, $unbranded] as $product) {
            $this->get(route('products.show', $product))->assertOk()
                ->assertDontSee('data-authorised-seller', false)
                ->assertDontSee('Authorised seller');
        }

        $this->get('/dv/products/'.$authorised->slug)->assertOk()
            ->assertSee('ހުއްދަ ލިބިފައިވާ ވިއްކާ ފަރާތް')
            ->assertSee('މި ފިހާރައަކީ Reefline ގެ ހުއްދަ ލިބިފައިވާ ވިއްކާ ފަރާތެއްކަން iruali އިން ކަށަވަރުކޮށްފައިވޭ.');
    }

    public function test_the_brand_page_lists_authorised_shops_first_with_the_badge(): void
    {
        foreach (range(1, 3) as $n) {
            $this->product(['brand' => 'Reefline']);
        }
        $this->product(['brand' => 'Reefline', 'seller_id' => $this->otherShop->id]);
        $brand = Brand::where('slug', 'reefline')->firstOrFail();

        // By products on sale while nobody is authorised
        $this->get('/brands/reefline')->assertOk()
            ->assertSeeInOrder(['Sold by', 'Reef Traders', 'Atoll Electronics'])
            ->assertDontSee('data-authorised-seller', false);

        $this->authorise($brand, $this->otherShop);
        $this->get('/brands/reefline')->assertOk()
            ->assertSeeInOrder(['Sold by', 'Atoll Electronics', 'Authorised seller', 'Reef Traders'])
            ->assertSee('title="iruali has confirmed this shop is an authorised seller of Reefline."', false)
            ->assertSee('iruali has confirmed this shop is an authorised seller of Reefline.');

        $this->authorise($brand, $this->shop);
        $this->get('/brands/reefline')->assertOk()->assertSee('iruali has confirmed these shops are authorised sellers of Reefline.');

        // First even when the strip has room for one shop only
        $brand->authorisedSellers()->detach($this->shop->id);
        $this->assertSame([$this->otherShop->id], app(BrandService::class)->shops($brand->fresh(), 1)['shops']->pluck('id')->all());
    }

    public function test_checking_shops_costs_one_query_per_brand_and_none_when_eager_loaded(): void
    {
        $product = $this->product(['brand' => 'Reefline']);
        $this->product(['brand' => 'Reefline', 'seller_id' => $this->otherShop->id]);
        $this->authorise($product->brandModel, $this->shop);

        $brand = Brand::findOrFail($product->brand_id);
        DB::enableQueryLog();
        $answers = [$brand->isAuthorisedSeller($this->shop->id), $brand->isAuthorisedSeller($this->otherShop->id), $brand->isAuthorisedSeller($this->shop->id)];
        $this->assertSame([true, false, true], $answers);
        $this->assertCount(1, DB::getQueryLog());

        $products = Product::with('brandModel.authorisedSellers:id')->where('brand_id', $brand->id)->get();
        DB::flushQueryLog();
        $this->assertSame([true, false], $products->sortBy('id')->map(fn (Product $p) => $p->brandModel->isAuthorisedSeller($p->seller_id))->values()->all());
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_merging_keeps_authorised_sellers_and_deleting_a_brand_drops_them(): void
    {
        $samsung = $this->product(['brand' => 'Samsung'])->brandModel;
        $duplicate = $this->product(['brand' => 'Samsung Electronics'])->brandModel;
        $this->authorise($duplicate, $this->shop);
        $this->authorise($duplicate, $this->otherShop);
        $this->authorise($samsung, $this->otherShop);

        $this->actingAs($this->admin)->post(route('admin.brands.merge', $duplicate), ['target_id' => $samsung->id])->assertRedirect();

        $this->assertEqualsCanonicalizing([$this->shop->id, $this->otherShop->id], $samsung->fresh()->authorisedSellers()->pluck('users.id')->all());
        $this->assertSame(2, DB::table('brand_authorised_sellers')->count());

        $unused = Brand::create(['name' => 'Old Label', 'slug' => 'old-label', 'key' => 'oldlabel']);
        $this->authorise($unused, $this->shop);
        $this->assertTrue(app(BrandService::class)->delete($unused));
        $this->assertSame(0, DB::table('brand_authorised_sellers')->where('brand_id', $unused->id)->count());
    }
}
