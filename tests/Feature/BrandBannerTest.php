<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A brand's cover banner: uploaded under Admin → Brands → Edit, shown across the top of the brand
 * page (with smaller WebP copies for phones), used when the page is shared, carried by a merge.
 */
class BrandBannerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function brand(string $name): Brand
    {
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);

        return Product::factory()->create([
            'brand' => $name, 'category_id' => $this->category->id, 'seller_id' => $shop->id, 'is_active' => true, 'stock_quantity' => 5,
        ])->brandModel;
    }

    /** Save the brand through Admin → Brands → Edit with these changes. */
    protected function save(Brand $brand, array $changes): TestResponse
    {
        $brand->refresh();

        return $this->actingAs($this->admin)->put(route('admin.brands.update', $brand), array_merge([
            'name' => $brand->name, 'name_dv' => $brand->name_dv, 'slug' => $brand->slug, 'description_en' => '', 'description_dv' => '', 'reviewed' => 1,
        ], $changes));
    }

    protected function url(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public function test_the_banner_tops_the_brand_page_in_both_languages_and_is_its_share_image(): void
    {
        $brand = $this->brand('Reefline');
        $this->get('/brands/reefline')->assertOk()->assertDontSee('data-brand-banner', false);

        $this->save($brand, ['banner' => UploadedFile::fake()->image('cover.jpg', 1600, 400)])->assertSessionHasNoErrors();

        $banner = $brand->fresh()->banner;
        $this->assertStringStartsWith('brands/banners/', $banner);
        $this->assertStringEndsWith('.jpg', $banner);
        foreach ([$banner, ImageVariants::variantPath($banner, 400), ImageVariants::variantPath($banner, 1200)] as $file) {
            Storage::disk('public')->assertExists($file);
        }

        // Phones load the small WebP copy, wide screens the large one; links shared show the banner
        $small = $this->url(ImageVariants::variantPath($banner, 400));
        $large = $this->url(ImageVariants::variantPath($banner, 1200));
        foreach (['/brands/reefline', '/dv/brands/reefline'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('<img src="'.$large.'" srcset="'.$small.' 400w, '.$large.' 1200w"', false)
                ->assertSee('data-brand-banner', false)
                ->assertSee('<meta property="og:image" content="'.$this->url($banner).'">', false);
        }

        // The edit page previews it
        $this->actingAs($this->admin)->get(route('admin.brands.edit', $brand))->assertOk()
            ->assertSee('<img src="'.$small.'"', false)->assertSee('data-banner-preview', false)->assertSee('Remove the banner');
        $this->assertSame('new banner', AuditLog::where('action', 'brand.updated')->latest('id')->first()->changes['banner']);
    }

    public function test_a_new_banner_replaces_the_old_files_and_removing_it_deletes_them(): void
    {
        $brand = $this->brand('Reefline');
        $this->save($brand, ['banner' => UploadedFile::fake()->image('first.png', 1600, 400)])->assertSessionHasNoErrors();
        $first = $brand->fresh()->banner;

        $this->save($brand, ['banner' => UploadedFile::fake()->image('second.jpg', 1200, 300)])->assertSessionHasNoErrors();
        $second = $brand->fresh()->banner;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertMissing(ImageVariants::variantPath($first, 400));
        Storage::disk('public')->assertMissing(ImageVariants::variantPath($first, 1200));
        Storage::disk('public')->assertExists($second);

        // Saving other changes keeps it
        $this->save($brand, ['description_en' => 'Marine gear for island life.'])->assertSessionHasNoErrors();
        $this->assertSame($second, $brand->fresh()->banner);

        $this->save($brand, ['remove_banner' => 1])->assertSessionHasNoErrors();
        $this->assertNull($brand->fresh()->banner);
        Storage::disk('public')->assertMissing($second);
        Storage::disk('public')->assertMissing(ImageVariants::variantPath($second, 1200));
        $this->assertSame('removed', AuditLog::where('action', 'brand.updated')->latest('id')->first()->changes['banner']);

        // Without a banner the page shares the logo, or else iruali's own image
        $this->get('/brands/reefline')->assertOk()->assertDontSee('data-brand-banner', false)
            ->assertSee('<meta property="og:image" content="'.asset('images/og-image.png').'">', false);
        $this->save($brand, ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])->assertSessionHasNoErrors();
        $this->get('/brands/reefline')->assertOk()->assertSee('<meta property="og:image" content="'.$this->url($brand->fresh()->logo).'">', false);
    }

    public function test_small_large_or_non_image_banners_are_refused(): void
    {
        $brand = $this->brand('Reefline');

        $this->save($brand, ['banner' => UploadedFile::fake()->image('small.jpg', 800, 200)])
            ->assertSessionHasErrors(['banner' => 'The banner must be at least 1000 pixels wide and 200 high (1600 × 400 is ideal).']);
        $this->save($brand, ['banner' => UploadedFile::fake()->image('big.jpg', 1600, 400)->size(3000)])
            ->assertSessionHasErrors(['banner' => 'The banner must be under 2 MB.']);
        $this->save($brand, ['banner' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf')])->assertSessionHasErrors('banner');
        $this->save($brand, ['banner' => UploadedFile::fake()->create('cover.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('banner');

        $this->assertNull($brand->fresh()->banner);
        $this->assertSame([], Storage::disk('public')->allFiles('brands/banners'));
    }

    public function test_a_merge_carries_the_banner_and_deleting_a_brand_removes_it(): void
    {
        $samsung = $this->brand('Samsung');
        $duplicate = $this->brand('Samsung Electronics');
        $this->save($duplicate, ['banner' => UploadedFile::fake()->image('cover.jpg', 1600, 400)])->assertSessionHasNoErrors();
        $banner = $duplicate->fresh()->banner;

        // A brand without a banner takes the merged one's
        $this->post(route('admin.brands.merge', $duplicate), ['target_id' => $samsung->id])->assertRedirect(route('admin.brands.edit', $samsung));
        $this->assertSame($banner, $samsung->fresh()->banner);
        Storage::disk('public')->assertExists($banner);

        // A brand with its own keeps it, and the merged one's files go
        $mobile = $this->brand('Samsung Mobile');
        $this->save($mobile, ['banner' => UploadedFile::fake()->image('mobile.jpg', 1600, 400)])->assertSessionHasNoErrors();
        $mobileBanner = $mobile->fresh()->banner;
        $this->post(route('admin.brands.merge', $mobile), ['target_id' => $samsung->id])->assertRedirect();
        $this->assertSame($banner, $samsung->fresh()->banner);
        Storage::disk('public')->assertMissing($mobileBanner);
        Storage::disk('public')->assertMissing(ImageVariants::variantPath($mobileBanner, 400));

        // Deleting a brand nobody sells any more removes its banner
        $old = $this->brand('Old Brand');
        $this->save($old, ['banner' => UploadedFile::fake()->image('old.jpg', 1600, 400)])->assertSessionHasNoErrors();
        $oldBanner = $old->fresh()->banner;
        Product::where('brand_id', $old->id)->get()->each->delete();
        $this->delete(route('admin.brands.destroy', $old))->assertRedirect(route('admin.brands'));
        $this->assertNull(Brand::find($old->id));
        Storage::disk('public')->assertMissing($oldBanner);
    }
}
