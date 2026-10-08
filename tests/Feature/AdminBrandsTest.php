<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBrandsTest extends TestCase
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
        ], $attributes));
    }

    protected function brand(string $name): Brand
    {
        return $this->product(['brand' => $name])->brandModel;
    }

    protected function form(Brand $brand, array $changes = []): array
    {
        return array_merge(['name' => $brand->name, 'slug' => $brand->slug, 'description_en' => '', 'description_dv' => '', 'reviewed' => 1], $changes);
    }

    public function test_admins_see_the_list_and_mark_new_brands_as_reviewed(): void
    {
        $samsung = $this->brand('Samsung');
        $this->brand('Reefline')->update(['reviewed_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/brands')->assertOk()
            ->assertSee('Samsung')->assertSee('Reefline')->assertSee('Reef Traders')
            ->assertSee('To review (1)');
        $this->get('/admin/brands?show=review')->assertOk()->assertSee('Samsung')->assertDontSee('/brands/reefline');
        $this->get('/admin/brands?q=reef')->assertOk()->assertSee('Reefline')->assertDontSee('/brands/samsung');

        $this->get('/admin/inbox')->assertOk()->assertSee('Brands to review');

        $this->post(route('admin.brands.review', $samsung))->assertRedirect();
        $this->assertNotNull($samsung->fresh()->reviewed_at);
        $this->assertSame('brand.reviewed', AuditLog::latest('id')->value('action'));
    }

    public function test_renaming_moves_products_and_keeps_the_old_address_working(): void
    {
        $brand = $this->brand('samsung');
        $product = $this->product(['brand' => 'Samsung', 'name' => ['en' => 'Galaxy charger']]);

        $this->actingAs($this->admin)->get(route('admin.brands.edit', $brand))->assertOk()->assertSee('value="samsung"', false);
        $this->put(route('admin.brands.update', $brand), $this->form($brand, [
            'name' => 'Samsung', 'slug' => 'samsung-electronics',
            'description_en' => 'Phones, TVs and home appliances.', 'description_dv' => 'ފޯނާއި ޓީވީ.',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.brands.edit', $brand));

        $brand->refresh();
        $this->assertSame(['Samsung', 'samsung-electronics'], [$brand->name, $brand->slug]);
        $this->assertNotNull($brand->reviewed_at);
        $this->assertSame('Samsung', $product->fresh()->brand);
        $this->assertSame('ފޯނާއި ޓީވީ.', $brand->getTranslation('description', 'dv'));
        $this->assertSame('samsung', BrandAlias::where('brand_id', $brand->id)->value('slug'));

        $this->get('/brands/samsung')->assertStatus(301)->assertRedirect('/brands/samsung-electronics');
        $this->get('/brands/samsung-electronics')->assertOk()->assertSee('Phones, TVs and home appliances.');
        $this->assertSame('brand.updated', AuditLog::latest('id')->value('action'));

        // Taking the old address back is allowed and stops it being an alias
        $this->put(route('admin.brands.update', $brand), $this->form($brand, ['slug' => 'samsung']))->assertSessionHasNoErrors();
        $this->assertSame('samsung', $brand->fresh()->slug);
        $this->assertSame(0, BrandAlias::where('slug', 'samsung')->count());
        $this->get('/brands/samsung-electronics')->assertStatus(301)->assertRedirect('/brands/samsung');
    }

    public function test_names_and_addresses_of_other_brands_are_refused(): void
    {
        $samsung = $this->brand('Samsung');
        $other = $this->brand('Samsung Electronics');

        $this->actingAs($this->admin)->from(route('admin.brands.edit', $other))
            ->put(route('admin.brands.update', $other), $this->form($other, ['name' => 'SAMSUNG']))
            ->assertSessionHasErrors('name');
        $this->put(route('admin.brands.update', $other), $this->form($other, ['slug' => 'samsung']))->assertSessionHasErrors('slug');
        $this->put(route('admin.brands.update', $other), $this->form($other, ['slug' => 'Not A Slug']))->assertSessionHasErrors('slug');
        $this->put(route('admin.brands.update', $other), $this->form($other, ['name' => 'N/A']))->assertSessionHasErrors('name');
        $this->assertSame('Samsung Electronics', $other->fresh()->name);
        $this->assertSame('samsung', $samsung->fresh()->slug);
    }

    public function test_logos_are_uploaded_safely_and_can_be_removed(): void
    {
        Storage::fake('public');
        $brand = $this->brand('Reefline');

        $this->actingAs($this->admin)->put(route('admin.brands.update', $brand), $this->form($brand, [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]))->assertSessionHasNoErrors();
        $logo = $brand->fresh()->logo;
        $this->assertStringStartsWith('brands/', $logo);
        $this->assertStringEndsWith('.png', $logo);
        Storage::disk('public')->assertExists($logo);
        $this->get('/brands/reefline')->assertSee(Storage::disk('public')->url($logo), false);

        $this->put(route('admin.brands.update', $brand), $this->form($brand, ['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')]))
            ->assertSessionHasErrors('logo');

        $this->put(route('admin.brands.update', $brand), $this->form($brand, ['remove_logo' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($brand->fresh()->logo);
        Storage::disk('public')->assertMissing($logo);
    }

    public function test_merging_moves_products_and_redirects_the_old_page(): void
    {
        Storage::fake('public');
        $samsung = $this->brand('Samsung');
        $duplicate = $this->brand('Samsung Electronics');
        $this->product(['brand' => 'Samsung Electronics', 'name' => ['en' => 'Smart TV']]);
        $duplicate->update(['logo' => 'brands/samsung.png']);

        $this->actingAs($this->admin)->get(route('admin.brands.edit', $duplicate))->assertOk()
            ->assertSee('Looks similar')->assertSee('<option value="'.$samsung->id.'">Samsung</option>', false);

        $this->post(route('admin.brands.merge', $duplicate), ['target_id' => $duplicate->id])->assertSessionHasErrors('target_id');
        $this->post(route('admin.brands.merge', $duplicate), ['target_id' => $samsung->id])
            ->assertRedirect(route('admin.brands.edit', $samsung));

        $this->assertNull(Brand::find($duplicate->id));
        $this->assertSame(3, Product::where('brand_id', $samsung->id)->count());
        $this->assertSame(0, Product::where('brand', 'Samsung Electronics')->count());
        $this->assertSame('brands/samsung.png', $samsung->fresh()->logo, 'a brand without a logo borrows the merged one');
        $this->get('/brands/samsung-electronics')->assertStatus(301)->assertRedirect('/brands/samsung');
        $this->get('/search?q=smart+tv')->assertOk()->assertSee('Smart TV');
        $this->assertSame('brand.merged', AuditLog::latest('id')->value('action'));

        // Shops typing the old name now get the brand it was merged into
        $this->assertSame($samsung->id, $this->product(['brand' => 'Samsung Electronics'])->brand_id);
        $this->assertSame(1, Brand::count());
    }

    public function test_only_unused_brands_can_be_deleted(): void
    {
        $used = $this->brand('Reefline');
        $this->actingAs($this->admin)->delete(route('admin.brands.destroy', $used))->assertSessionHas('error');
        $this->assertNotNull($used->fresh());

        $unused = Brand::create(['name' => 'Old Label', 'slug' => 'old-label', 'key' => 'oldlabel']);
        $this->delete(route('admin.brands.destroy', $unused))->assertRedirect(route('admin.brands'));
        $this->assertNull(Brand::find($unused->id));
        $this->assertSame('brand.deleted', AuditLog::latest('id')->value('action'));
    }

    public function test_brands_are_for_admins_only(): void
    {
        $brand = $this->brand('Reefline');

        $this->get('/admin/brands')->assertRedirect(route('login'));

        $support = User::factory()->create();
        $support->roles()->attach(Role::firstOrCreate(['name' => 'support'], ['display_name' => 'Support'])->id);
        $this->actingAs($support)->get('/admin/brands')->assertForbidden();
        $this->actingAs($support)->post(route('admin.brands.review', $brand))->assertForbidden();

        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->actingAs($this->shop)->get(route('admin.brands.edit', $brand))->assertForbidden();
        $this->actingAs($this->shop)->post(route('admin.brands.merge', $brand), ['target_id' => $brand->id])->assertForbidden();
    }
}
