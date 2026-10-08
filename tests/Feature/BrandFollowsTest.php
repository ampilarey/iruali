<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandFollow;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Following brands: the Follow / Following button on brand pages (also under /dv) and My Account →
 * Brands you follow.
 */
class BrandFollowsTest extends TestCase
{
    use RefreshDatabase;

    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 09:00:00');
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Traders']);
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    protected function follow(User $user, Brand $brand): BrandFollow
    {
        return BrandFollow::create(['user_id' => $user->id, 'brand_id' => $brand->id]);
    }

    public function test_customers_follow_and_unfollow_a_brand_from_its_page(): void
    {
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/brands/reefline')->assertOk()
            ->assertSee('action="'.route('brands.follow', $brand).'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('Follow')
            ->assertDontSee('Following');

        $this->from('/brands/reefline')->post(route('brands.follow', $brand))
            ->assertRedirect('/brands/reefline')
            ->assertSessionHas('notification', fn (array $flash) => str_contains($flash['message'], 'You follow Reefline now.'));
        $this->assertDatabaseHas('brand_follows', ['user_id' => $customer->id, 'brand_id' => $brand->id, 'notified_at' => null]);

        // Pressing it again changes nothing
        $this->from('/brands/reefline')->post(route('brands.follow', $brand));
        $this->assertSame(1, BrandFollow::count());
        $this->assertSame([$brand->id], $customer->followedBrands()->pluck('brands.id')->all());
        $this->assertSame([$customer->id], $brand->followers()->pluck('users.id')->all());

        $this->get('/brands/reefline')->assertOk()
            ->assertSee('Following')
            ->assertSee('action="'.route('brands.unfollow', $brand).'"', false)
            ->assertSee('name="_method" value="DELETE"', false);

        $this->from('/brands/reefline')->delete(route('brands.unfollow', $brand))->assertRedirect('/brands/reefline');
        $this->assertSame(0, BrandFollow::count());
        $this->get('/brands/reefline')->assertSee('action="'.route('brands.follow', $brand).'"', false);

        $this->post('/brands/nobody/follow')->assertNotFound();
    }

    public function test_guests_sign_in_first_and_come_back_to_the_brand_page(): void
    {
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $customer = User::factory()->create();

        // The button is there for guests too
        $this->get('/brands/reefline')->assertOk()->assertSee('action="'.route('brands.follow', $brand).'"', false);
        $this->post(route('brands.follow', $brand))->assertRedirect(route('login'));
        $this->assertSame(0, BrandFollow::count());

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect('/brands/reefline');
        $this->assertAuthenticatedAs($customer);
        $this->assertSame(0, BrandFollow::count(), 'signing in only brings them back; they press Follow themselves');

        // From the Dhivehi page they come back to the Dhivehi page
        $this->post('/logout');
        $this->from('/dv/brands/reefline')->post('/dv/brands/reefline/follow')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect('/dv/brands/reefline');
    }

    public function test_following_works_on_dhivehi_pages(): void
    {
        $this->product(['brand' => 'Reefline']);
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/dv/brands/reefline')->assertOk()
            ->assertSee('action="'.url('/dv/brands/reefline/follow').'"', false)
            ->assertSee('ފޮލޯ ކުރައްވާ');

        $this->from('/dv/brands/reefline')->post('/dv/brands/reefline/follow')
            ->assertRedirect('/dv/brands/reefline')
            ->assertSessionHas('notification', fn (array $flash) => str_contains($flash['message'], 'ފޮލޯ ކުރައްވަނީ'));
        $this->assertSame(1, $customer->followedBrands()->count());

        $this->get('/dv/brands/reefline')->assertOk()->assertSee('ފޮލޯ ކުރައްވަނީ')->assertSee('dir="rtl"', false);

        $this->from('/dv/brands/reefline')->delete('/dv/brands/reefline/follow')->assertRedirect('/dv/brands/reefline');
        $this->assertSame(0, $customer->followedBrands()->count());
    }

    public function test_the_follower_count_shows_once_five_people_follow(): void
    {
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $fans = User::factory()->count(Brand::FOLLOWERS_SHOWN_FROM)->create();

        $fans->take(Brand::FOLLOWERS_SHOWN_FROM - 1)->each(fn (User $fan) => $this->follow($fan, $brand));
        $this->get('/brands/reefline')->assertOk()->assertDontSee('4 followers');

        $this->follow($fans->last(), $brand);
        $this->get('/brands/reefline')->assertOk()->assertSee('5 followers');
        $this->get('/dv/brands/reefline')->assertOk()->assertSee('5 ފޮލޯވަރުން');
    }

    public function test_the_account_page_lists_followed_brands_with_an_unfollow_button(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/account')->assertOk()->assertSee('href="'.route('account.brands').'"', false);
        $this->get(route('account.brands'))->assertOk()
            ->assertSee('<title>Brands you follow - iruali</title>', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('You do not follow any brands yet.')
            ->assertSee('href="'.route('brands.index').'"', false);

        $reefline = $this->product(['brand' => 'Reefline'])->brandModel;
        $bluewave = $this->product(['brand' => 'Bluewave', 'is_active' => false])->brandModel;
        $this->follow($customer, $reefline);
        $this->follow($customer, $bluewave);
        $this->product(['brand' => 'Not Followed']);

        $this->get(route('account.brands'))->assertOk()
            ->assertSeeInOrder(['Bluewave', 'Reefline'])
            ->assertSee('href="'.route('brands.show', $reefline).'"', false)
            // Nothing of Bluewave's is on sale, so there is no page to link to
            ->assertDontSee('href="'.route('brands.show', $bluewave).'"', false)
            ->assertSee('Nothing on sale right now')
            ->assertSee('action="'.route('brands.unfollow', $reefline).'"', false)
            ->assertDontSee('Not Followed');

        $this->from(route('account.brands'))->delete(route('brands.unfollow', $reefline))->assertRedirect(route('account.brands'));
        $this->assertSame([$bluewave->id], $customer->followedBrands()->pluck('brands.id')->all());

        $this->withSession(['locale' => 'dv'])->get(route('account.brands'))->assertOk()
            ->assertSee('dir="rtl"', false)->assertSee('ތިބާ ފޮލޯ ކުރައްވާ ބްރޭންޑްތައް')->assertSee('އަންފޮލޯ ކުރައްވާ');

        $this->post('/logout');
        $this->get(route('account.brands'))->assertRedirect(route('login'));
    }

    public function test_admins_see_how_many_people_follow_each_brand(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        User::factory()->count(3)->create()->each(fn (User $fan) => $this->follow($fan, $brand));

        $this->actingAs($admin)->get('/admin/brands')->assertOk()
            ->assertSee('<th class="px-4 py-3 text-end">Followers</th>', false)
            ->assertSee('<td class="px-4 py-3 text-end tabular-nums">3</td>', false);
        $this->get(route('admin.brands.edit', $brand))->assertOk()
            ->assertSee('<dt class="text-gray-500">Followers</dt><dd class="font-medium tabular-nums">3</dd>', false);
    }

    public function test_merging_a_brand_moves_its_followers(): void
    {
        $samsung = $this->product(['brand' => 'Samsung'])->brandModel;
        $duplicate = $this->product(['brand' => 'Samsung Electronics'])->brandModel;
        [$fan, $both] = User::factory()->count(2)->create()->all();
        $this->follow($fan, $duplicate);
        $this->follow($both, $duplicate);
        $this->follow($both, $samsung);

        app(\App\Services\BrandService::class)->merge($duplicate, $samsung);

        $this->assertSame([$samsung->id], $fan->followedBrands()->pluck('brands.id')->all());
        $this->assertSame([$samsung->id], $both->followedBrands()->pluck('brands.id')->all());
        $this->assertSame(2, $samsung->followers()->count());
    }
}
