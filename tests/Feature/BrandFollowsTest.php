<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandFollow;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BrandFollowDigest;
use App\Services\BrandDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Following brands: the Follow / Following button on brand pages (also under /dv), My Account →
 * Brands you follow, and the daily email about what followed brands put on sale.
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
            ->assertSee('Once a day, we email you')
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

    public function test_products_remember_when_shoppers_could_first_see_their_markdown(): void
    {
        $product = $this->product(['brand' => 'Reefline', 'price' => 100, 'compare_price' => 150, 'is_active' => false]);
        $this->assertNull($product->fresh()->sale_started_at, 'waiting for approval: nobody can see it yet');

        // Approved (shown) a day later: that is when shoppers could first see it
        $this->travel(1)->days();
        $product->update(['is_active' => true]);
        $this->assertSame('2026-10-10 09:00:00', Carbon::parse($product->fresh()->sale_started_at)->toDateTimeString());

        // A deeper markdown or a stock change is not a new sale
        $this->travel(1)->hours();
        $product->update(['price' => 90, 'stock_quantity' => 3]);
        $this->assertSame('2026-10-10 09:00:00', Carbon::parse($product->fresh()->sale_started_at)->toDateTimeString());

        // The markdown ends, and later starts again
        $product->update(['compare_price' => null]);
        $this->assertNull($product->fresh()->sale_started_at);
        $this->travel(1)->hours();
        $product->update(['compare_price' => 120]);
        $this->assertSame('2026-10-10 11:00:00', Carbon::parse($product->fresh()->sale_started_at)->toDateTimeString());
    }

    public function test_the_daily_email_lists_what_followed_brands_put_on_sale_and_never_repeats_it(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $handLine = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Hand line kit']]);
        $brand = $handLine->brandModel;
        $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Old deal'], 'compare_price' => 150]);
        $this->product(['brand' => 'Bluewave', 'name' => ['en' => 'Other brand deal'], 'compare_price' => 150]);

        $this->travel(1)->minutes();
        $this->follow($customer, $brand);
        $this->artisan('brands:notify-followers')->expectsOutputToContain('Brand updates emailed to 0 customer(s).')->assertSuccessful();
        Notification::assertNothingSent();

        // After following: one product goes on sale, one joins a running campaign, others do not count
        $this->travel(1)->hours();
        $handLine->update(['compare_price' => 150]);
        $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Sold out deal'], 'compare_price' => 150, 'stock_quantity' => 0]);
        $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Hidden deal'], 'compare_price' => 150, 'is_active' => false]);
        $this->travel(1)->hours();
        $dryBag = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Dry bag'], 'price' => 200]);
        $running = Campaign::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->addWeek()]);
        CampaignProduct::create(['campaign_id' => $running->id, 'product_id' => $dryBag->id, 'seller_id' => $this->shop->id, 'approved_at' => now()]);
        $notApproved = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Waiting product']]);
        CampaignProduct::create(['campaign_id' => $running->id, 'product_id' => $notApproved->id, 'seller_id' => $this->shop->id]);
        $later = Campaign::factory()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeeks(2)]);
        $upcoming = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Next week product']]);
        CampaignProduct::create(['campaign_id' => $later->id, 'product_id' => $upcoming->id, 'seller_id' => $this->shop->id, 'approved_at' => now()]);

        $this->travel(1)->hours();
        $this->artisan('brands:notify-followers')->expectsOutputToContain('Brand updates emailed to 1 customer(s).')->assertSuccessful();

        Notification::assertSentTo($customer, BrandFollowDigest::class, function (BrandFollowDigest $digest, array $channels) use ($dryBag, $handLine, $brand, $running) {
            return $channels === ['mail']
                && $digest->productIds() === [$dryBag->id, $handLine->id]
                && $digest->items[0]['campaign'] === $running->id && $digest->items[1]['campaign'] === null
                && $digest->totals === [$brand->id => 2];
        });
        Notification::assertSentTimes(BrandFollowDigest::class, 1);
        $this->assertSame('2026-10-09 12:00:59', BrandFollow::first()->notified_at->toDateTimeString());

        // Nothing new: nothing sent, however often it runs
        $this->artisan('brands:notify-followers')->expectsOutputToContain('emailed to 0 customer(s)');
        $this->travel(1)->days();
        $this->artisan('brands:notify-followers')->expectsOutputToContain('emailed to 0 customer(s)');
        Notification::assertSentTimes(BrandFollowDigest::class, 1);

        // The campaign that was still to come starts: that is news now, and only that
        $this->travel(7)->days();
        $this->artisan('brands:notify-followers')->expectsOutputToContain('emailed to 1 customer(s)');
        Notification::assertSentTimes(BrandFollowDigest::class, 2);
        Notification::assertSentTo($customer, BrandFollowDigest::class, fn (BrandFollowDigest $digest) => $digest->productIds() === [$upcoming->id]);
    }

    public function test_one_email_per_customer_covers_every_brand_they_follow(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $reefline = $this->product(['brand' => 'Reefline'])->brandModel;
        $bluewave = $this->product(['brand' => 'Bluewave'])->brandModel;
        $this->follow($customer, $reefline);
        $this->follow($customer, $bluewave);
        $this->follow($other, $bluewave);

        $this->travel(1)->hours();
        $reef = $this->product(['brand' => 'Reefline', 'compare_price' => 150]);
        $this->travel(1)->minutes();
        $blue = $this->product(['brand' => 'Bluewave', 'compare_price' => 150]);
        $this->travel(1)->hours();
        $this->artisan('brands:notify-followers')->expectsOutputToContain('emailed to 2 customer(s)');

        Notification::assertSentToTimes($customer, BrandFollowDigest::class, 1);
        Notification::assertSentTo($customer, BrandFollowDigest::class, fn (BrandFollowDigest $digest) => $digest->productIds() === [$blue->id, $reef->id]
            && $digest->totals === [$bluewave->id => 1, $reefline->id => 1]);
        Notification::assertSentTo($other, BrandFollowDigest::class, fn (BrandFollowDigest $digest) => $digest->productIds() === [$blue->id]);
    }

    public function test_a_long_list_is_capped_with_a_link_to_the_brand_page(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['name' => 'Aisha']);
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $quiet = $this->product(['brand' => 'Bluewave'])->brandModel;
        $this->follow($customer, $brand);
        $this->follow($customer, $quiet);

        $this->travel(1)->hours();
        foreach (range(1, BrandDigestService::MAX_PRODUCTS + 2) as $n) {
            $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Reef deal '.$n], 'compare_price' => 150]);
            $this->travel(1)->minutes();
        }
        $this->product(['brand' => 'Bluewave', 'name' => ['en' => 'Blue deal'], 'compare_price' => 150]);
        $this->travel(1)->minutes();
        $this->artisan('brands:notify-followers');

        $digest = Notification::sent($customer, BrandFollowDigest::class)->first();
        $this->assertCount(BrandDigestService::MAX_PRODUCTS, $digest->items);
        $this->assertSame([$quiet->id => 1, $brand->id => BrandDigestService::MAX_PRODUCTS + 2], $digest->totals);
        $this->assertSame('Blue deal', Product::find($digest->items[0]['product'])->name, 'every brand gets a turn before one fills the email');

        $mail = $digest->toMail($customer);
        $html = (string) $mail->render();
        $this->assertSame('New deals from brands you follow', $mail->subject);
        $this->assertStringContainsString('Hello Aisha,', $html);
        $this->assertStringContainsString('Reef deal 14', $html);
        $this->assertStringNotContainsString('Reef deal 3<', $html);
        $this->assertStringContainsString('And 3 more new products.', $html);
        $this->assertStringContainsString('href="'.url('/brands/reefline').'"', $html);
        $this->assertStringContainsString('See everything from Reefline', $html);
        $this->assertStringContainsString('Now on sale', $html);
        $this->assertStringContainsString('MVR 100.00', $html);
        $this->assertStringContainsString(route('account.notifications'), $html);
    }

    public function test_customers_who_switch_brand_emails_off_get_none(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $brand = $this->product(['brand' => 'Reefline'])->brandModel;
        $this->follow($customer, $brand);

        $this->actingAs($customer)->get(route('account.notifications'))->assertOk()
            ->assertSee('Brands you follow')->assertSee('name="brand_updates" value="off"', false);
        $this->put(route('account.notifications.update'), ['order_updates' => 'email', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email', 'brand_updates' => 'off'])
            ->assertSessionHasNoErrors()->assertRedirect(route('account.notifications'));
        $this->assertSame('off', $customer->fresh()->emailPreference('brand_updates'));
        $this->assertSame('email', $customer->fresh()->notificationPreference('order_updates'));
        $this->get(route('account.brands'))->assertSee('Emails about the brands you follow are off.');

        $this->travel(1)->hours();
        $this->product(['brand' => 'Reefline', 'compare_price' => 150]);
        $this->travel(1)->hours();
        $this->artisan('brands:notify-followers')->expectsOutputToContain('emailed to 0 customer(s)');
        Notification::assertNothingSent();

        // Switched back on: only news from then on, not what was skipped
        $this->put(route('account.notifications.update'), ['order_updates' => 'email', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email', 'brand_updates' => 'email'])
            ->assertSessionHasNoErrors();
        $this->artisan('brands:notify-followers');
        Notification::assertNothingSent();
        $this->travel(1)->hours();
        $fresh = $this->product(['brand' => 'Reefline', 'compare_price' => 150]);
        $this->travel(1)->hours();
        $this->artisan('brands:notify-followers');
        Notification::assertSentTo($customer, BrandFollowDigest::class, fn (BrandFollowDigest $digest) => $digest->productIds() === [$fresh->id]);

        // A form without the field leaves the choice alone; nonsense is refused
        $this->put(route('account.notifications.update'), ['order_updates' => 'email', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email'])->assertSessionHasNoErrors();
        $this->assertSame('email', $customer->fresh()->emailPreference('brand_updates'));
        $this->put(route('account.notifications.update'), ['order_updates' => 'email', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email', 'brand_updates' => 'sms'])
            ->assertSessionHasErrors('brand_updates');
    }

    public function test_marketing_opt_outs_get_no_brand_emails(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $customer->forceFill(['marketing_opt_out_at' => now()])->save();
        $this->follow($customer, $this->product(['brand' => 'Reefline'])->brandModel);

        $this->travel(1)->hours();
        $this->product(['brand' => 'Reefline', 'compare_price' => 150]);
        $this->travel(1)->hours();
        $this->artisan('brands:notify-followers')->expectsOutputToContain('emailed to 0 customer(s)');
        Notification::assertNothingSent();
        $this->assertNotNull(BrandFollow::first()->notified_at);

        $this->actingAs($customer)->get(route('account.brands'))->assertSee('Marketing emails are off, so we do not email you about the brands you follow.');
        $this->get(route('account.notifications'))->assertSee('Marketing emails are off, so these are not sent until you turn them back on in My Account.');
    }

    public function test_the_email_is_written_in_the_customers_language(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['preferred_language' => 'dv']);
        $handLine = $this->product(['brand' => 'Reefline', 'name' => ['en' => 'Hand line kit', 'dv' => 'ބޮޑުވަޅި ކިޓް']]);
        $brand = $handLine->brandModel;
        $brand->name_dv = 'ރީފްލައިން';
        $brand->save();
        $this->follow($customer, $brand);

        $this->travel(1)->hours();
        $handLine->update(['compare_price' => 150]);
        $this->travel(1)->hours();
        $this->artisan('brands:notify-followers');

        Notification::assertSentTo($customer, BrandFollowDigest::class, fn (BrandFollowDigest $digest, array $channels, User $notifiable, ?string $locale) => $locale === 'dv');

        $digest = Notification::sent($customer, BrandFollowDigest::class)->first();
        app()->setLocale('dv');
        $mail = $digest->toMail($customer);
        $html = (string) $mail->render();
        app()->setLocale('en');

        $this->assertSame('ރީފްލައިން ގެ އާ ޑީލްތައް', $mail->subject);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('ބޮޑުވަޅި ކިޓް', $html);
        $this->assertStringContainsString('މިހާރު ސޭލްގައި', $html);
        $this->assertStringContainsString('href="'.url('/dv/brands/reefline').'"', $html);
        $this->assertStringContainsString('ރީފްލައިން ގެ ހުރިހާ ތަކެތި ބައްލަވާ', $html);
    }

    public function test_the_digest_is_scheduled_once_a_day(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('brands:notify-followers');
    }
}
