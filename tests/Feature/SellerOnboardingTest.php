<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SellerOnboarded;
use App\Services\OnboardingService;
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    /**
     * A freshly approved shop with nothing set up yet. Approval alone does not complete onboarding:
     * only shops approved before the checklist existed were marked complete by the migration.
     */
    protected function newShop(): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts', 'phone' => null, 'business_description' => 'Short']);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function profileInput(array $extra = []): array
    {
        return ['name' => 'Aisha', 'business_name' => 'Island Crafts', 'phone' => '7771234', 'business_description' => str_repeat('Handmade crafts from Hithadhoo. ', 3)] + $extra;
    }

    public function test_dashboard_shows_the_checklist_until_every_item_is_done(): void
    {
        $shop = $this->newShop();

        $this->actingAs($shop)->get('/seller/dashboard')->assertOk()
            ->assertSee('Set up your shop')->assertSee('0 of 8 done')
            ->assertSee('Shop logo')->assertSee('Shop banner')->assertSee('About your shop')->assertSee('Phone number')
            ->assertSee('Delivery options')->assertSee('Home island and delivery area')->assertSee('Bank account')->assertSee('First product');

        $this->put('/seller/profile', $this->profileInput(['delivery_notes' => 'Boat to Addu twice a week, courier in Malé.', 'ships_to_islands' => 1]))->assertRedirect();
        $this->get('/seller/dashboard')->assertSee('3 of 8 done');
        $this->assertNull($shop->fresh()->onboarding_completed_at);
        Notification::assertNothingSent();
    }

    public function test_completing_the_checklist_marks_the_shop_onboarded_and_tells_admins_once(): void
    {
        $shop = $this->newShop();
        $this->actingAs($shop);

        $this->put('/seller/profile', $this->profileInput([
            'delivery_notes' => 'Boat to Addu twice a week.',
            'home_island_id' => \App\Models\Island::where('code', 'seenu-hithadhoo')->value('id'),
            'delivery_coverage' => 'home_and_male',
            'shop_logo' => UploadedFile::fake()->image('logo.png', 300, 300),
            'shop_banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
        ]))->assertRedirect();
        $shop->refresh();
        $this->assertNotNull($shop->shop_logo);
        $this->assertNotNull($shop->shop_banner);
        Storage::disk('public')->assertExists($shop->shop_logo);

        $this->put('/seller/settings/bank', ['bank' => 'bml', 'account_name' => 'Island Crafts', 'account_number' => '7730000123456']);
        $this->get('/seller/dashboard')->assertSee('7 of 8 done');
        $this->assertNull($shop->fresh()->onboarding_completed_at);

        $this->post('/seller/products', [
            'name_en' => 'Reef Safe Sunscreen', 'sku' => 'SUN-001', 'category_id' => Category::factory()->create()->id, 'price' => 150, 'stock_quantity' => 20,
        ])->assertRedirect();

        $this->assertNotNull($shop->fresh()->onboarding_completed_at);
        $this->assertTrue($shop->fresh()->isOnboarded());
        Notification::assertSentTo($this->admin, SellerOnboarded::class, fn ($n) => $n->seller->is($shop));
        Notification::assertNotSentTo($shop, SellerOnboarded::class);
        $this->assertContains(ShouldQueue::class, class_implements(SellerOnboarded::class));

        $this->actingAs($shop->fresh())->get('/seller/dashboard')->assertOk()->assertDontSee('Set up your shop');

        // Saving the profile again does not notify a second time
        $this->put('/seller/profile', $this->profileInput(['delivery_notes' => 'Updated.']));
        Notification::assertSentToTimes($this->admin, SellerOnboarded::class, 1);
    }

    public function test_products_stay_hidden_until_the_shop_is_onboarded(): void
    {
        $shop = $this->newShop();
        $product = Product::factory()->create(['seller_id' => $shop->id, 'is_active' => false, 'name' => ['en' => 'Lacquer Box', 'dv' => 'ލިޔެލާ ފޮށި']]);

        $this->actingAs($this->admin)->post(route('admin.products.approve', $product->id))->assertRedirect()->assertSessionHas('error');
        // The admin sees why on the products page (it used to look as if nothing happened)
        $this->from(route('admin.products'))->followingRedirects()->post(route('admin.products.approve', $product->id))
            ->assertSee('has not completed its checklist yet');
        $this->assertFalse($product->fresh()->is_active);
        $this->get('/products')->assertOk()->assertDontSee('Lacquer Box');
        $this->assertTrue(app(OnboardingService::class)->blocksActivation($shop));

        $shop->forceFill(['onboarding_completed_at' => now()])->save();
        $this->post(route('admin.products.approve', $product->id))->assertSessionHas('success');
        $this->assertTrue($product->fresh()->is_active);
        $this->get('/products')->assertOk()->assertSee('Lacquer Box');
    }

    public function test_demo_shops_are_onboarded_so_nothing_live_disappears(): void
    {
        $this->seed(MarketplaceDemoSeeder::class);

        $sellers = User::where('is_seller', true)->where('seller_approved', true)->get();
        $this->assertGreaterThan(0, $sellers->count());
        $this->assertTrue($sellers->every(fn ($s) => $s->isOnboarded()));
        $this->assertFalse(app(OnboardingService::class)->blocksActivation($sellers->first()));

        // iruali's own shop (an admin account) is never held back by the checklist
        $own = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $own->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->assertFalse(app(OnboardingService::class)->blocksActivation($own->fresh()));

        // A customer account is never "blocked": the rule is only for shops
        $this->assertFalse(app(OnboardingService::class)->blocksActivation(User::factory()->create()));
    }

    public function test_shop_page_shows_logo_and_banner(): void
    {
        $shop = $this->newShop();
        $shop->forceFill(['shop_logo' => 'shops/logo.png', 'shop_banner' => 'shops/banner.jpg', 'onboarding_completed_at' => now()])->save();

        $this->get(route('sellers.show', $shop))->assertOk()
            ->assertSee('/storage/shops/logo.png', false)->assertSee('/storage/shops/banner.jpg', false);
    }
}
