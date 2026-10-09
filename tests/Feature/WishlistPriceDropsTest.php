<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Wishlist;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\WishlistPriceDrop;
use App\Services\WishlistPriceDropService;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

/**
 * Price-drop alerts: the price when a product is wishlisted, the daily wishlist:price-drops digest
 * (email and push), the preference to switch it off, and the saving shown on the wishlist page.
 */
class WishlistPriceDropsTest extends TestCase
{
    use RefreshDatabase;

    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 10:00:00');
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
            'price' => 200,
            'compare_price' => null,
        ], $attributes));
    }

    protected function save(User $user, Product $product): Wishlist
    {
        return Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
    }

    protected function runAlerts(): void
    {
        $this->artisan('wishlist:price-drops')->assertSuccessful();
    }

    public function test_the_final_price_is_stored_when_a_product_is_wishlisted(): void
    {
        $customer = User::factory()->create();
        $plain = $this->product(['price' => 200]);
        $markedDown = $this->product(['price' => 150, 'compare_price' => 200]);
        $inCampaign = $this->product(['price' => 300]);
        $campaign = Campaign::factory()->create(['discount_percent' => 10]);
        CampaignProduct::create(['campaign_id' => $campaign->id, 'product_id' => $inCampaign->id, 'seller_id' => $this->shop->id, 'approved_at' => now()]);
        Campaign::forgetDiscounts();

        $this->actingAs($customer)->post(route('wishlist.add', $plain), ['product_id' => $plain->id])->assertRedirect(route('wishlist'));
        $this->post(route('wishlist.add', $markedDown), ['product_id' => $markedDown->id]);
        \Laravel\Sanctum\Sanctum::actingAs($customer);
        $this->postJson('/api/v1/wishlist/add', ['product_id' => $inCampaign->id])->assertOk();

        $this->assertSame('200.00', Wishlist::where('product_id', $plain->id)->value('saved_price'));
        $this->assertSame('150.00', Wishlist::where('product_id', $markedDown->id)->value('saved_price'));
        $this->assertSame('270.00', Wishlist::where('product_id', $inCampaign->id)->value('saved_price'), 'the campaign price, 10% off');
        $this->assertNull(Wishlist::where('product_id', $plain->id)->value('notified_price'));
    }

    public function test_a_drop_must_be_at_least_five_percent_and_ten_rufiyaa(): void
    {
        $this->assertTrue(Wishlist::isPriceDrop(200, 190), 'MVR 10 is exactly 5% of 200');
        $this->assertFalse(Wishlist::isPriceDrop(200, 190.01));
        $this->assertTrue(Wishlist::isPriceDrop(100, 90), 'MVR 10 and 10%');
        $this->assertFalse(Wishlist::isPriceDrop(100, 91), 'only MVR 9 less');
        $this->assertFalse(Wishlist::isPriceDrop(1000, 960), 'MVR 40 but only 4%');
        $this->assertTrue(Wishlist::isPriceDrop(1000, 950));
        $this->assertFalse(Wishlist::isPriceDrop(150, 150));
        $this->assertFalse(Wishlist::isPriceDrop(150, 180), 'dearer is not a drop');
        $this->assertFalse(Wishlist::isPriceDrop(null, 100));
        $this->assertFalse(Wishlist::isPriceDrop(100, null));
    }

    public function test_one_digest_lists_the_drops_biggest_first_and_never_repeats_one(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $kit = $this->product(['price' => 200, 'name' => ['en' => 'Hand line kit']]);
        $bag = $this->product(['price' => 100, 'name' => ['en' => 'Dry bag']]);
        $rod = $this->product(['price' => 500, 'name' => ['en' => 'Fishing rod']]);
        $kitItem = $this->save($customer, $kit);
        $bagItem = $this->save($customer, $bag);
        $rodItem = $this->save($customer, $rod);

        // Nothing cheaper yet
        $this->artisan('wishlist:price-drops')->expectsOutputToContain('Price-drop alerts sent to 0 customer(s).')->assertSuccessful();
        Notification::assertNothingSent();

        $kit->update(['price' => 150]);  // MVR 50 (25%) less
        $bag->update(['price' => 95]);   // only MVR 5 less
        $rod->update(['compare_price' => 500, 'price' => 400]); // a markdown: MVR 100 less

        $this->artisan('wishlist:price-drops')->expectsOutputToContain('Price-drop alerts sent to 1 customer(s).');
        Notification::assertSentToTimes($customer, WishlistPriceDrop::class, 1);
        Notification::assertSentTo($customer, WishlistPriceDrop::class, function (WishlistPriceDrop $alert, array $channels) use ($rod, $kit) {
            return $channels === ['mail']
                && $alert->productIds() === [$rod->id, $kit->id]
                && $alert->drops[0]['was'] === 500.0 && $alert->drops[0]['now'] === 400.0
                && $alert->drops[1]['was'] === 200.0 && $alert->drops[1]['now'] === 150.0;
        });

        // The reference moved to the price they were told about; the saved price stays
        $this->assertSame(['150.00', '200.00'], [$kitItem->fresh()->notified_price, $kitItem->fresh()->saved_price]);
        $this->assertSame('400.00', $rodItem->fresh()->notified_price);
        $this->assertNull($bagItem->fresh()->notified_price);
        $this->assertNotNull($kitItem->fresh()->price_drop_notified_at);

        // The same prices tomorrow: nothing new
        $this->travel(1)->days();
        $this->runAlerts();
        Notification::assertSentTimes(WishlistPriceDrop::class, 1);

        // A small further drop is not enough; a rise and a fall back to the told price is not news
        $kit->update(['price' => 145]);
        $rod->update(['price' => 450]);
        $this->runAlerts();
        $rod->update(['price' => 400]);
        $this->runAlerts();
        Notification::assertSentTimes(WishlistPriceDrop::class, 1);

        // Another real drop from the told price: only that item
        $kit->update(['price' => 135]);
        $this->runAlerts();
        Notification::assertSentTimes(WishlistPriceDrop::class, 2);
        Notification::assertSentTo($customer, WishlistPriceDrop::class, fn (WishlistPriceDrop $alert) => $alert->productIds() === [$kit->id] && $alert->drops[0]['was'] === 150.0);
    }

    public function test_hidden_sold_out_binned_and_smoke_test_cases_are_skipped(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $hidden = $this->product();
        $soldOut = $this->product();
        $binned = $this->product();
        foreach ([$hidden, $soldOut, $binned] as $product) {
            $this->save($customer, $product);
        }
        $smoke = User::factory()->create(['is_smoke_test' => true]);
        $banned = User::factory()->create(['banned_until' => now()->addMonth()]);
        $inactive = User::factory()->create(['is_active' => false]);
        $shared = $this->product();
        foreach ([$smoke, $banned, $inactive] as $user) {
            $this->save($user, $shared);
        }

        $hidden->update(['price' => 100, 'is_active' => false]);
        $soldOut->update(['price' => 100, 'stock_quantity' => 0]);
        $binned->update(['price' => 100]);
        $binned->delete();
        $shared->update(['price' => 100]);

        $this->artisan('wishlist:price-drops')->expectsOutputToContain('sent to 0 customer(s)');
        Notification::assertNothingSent();

        // Back in stock at the lower price: now the customer hears about it
        $soldOut->update(['stock_quantity' => 3]);
        $this->runAlerts();
        Notification::assertSentTo($customer, WishlistPriceDrop::class, fn (WishlistPriceDrop $alert) => $alert->productIds() === [$soldOut->id]);
        Notification::assertSentTimes(WishlistPriceDrop::class, 1);
    }

    public function test_customers_who_switch_the_alert_or_marketing_off_get_none(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $kit = $this->product();
        $this->save($customer, $kit);

        $this->actingAs($customer)->get(route('account.notifications'))->assertOk()
            ->assertSee('Price drops on your wishlist')
            ->assertSee('name="wishlist_price_drops" value="off"', false);
        $this->put(route('account.notifications.update'), ['order_updates' => 'email', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email', 'wishlist_price_drops' => 'off'])
            ->assertSessionHasNoErrors()->assertRedirect(route('account.notifications'));
        $this->assertSame('off', $customer->fresh()->emailPreference('wishlist_price_drops'));
        $this->assertSame('email', $customer->fresh()->emailPreference('brand_updates'), 'the other optional email is untouched');

        $kit->update(['price' => 150]);
        $this->runAlerts();
        Notification::assertNothingSent();

        // Marketing emails off: none either, and the settings page says why
        $this->put(route('account.notifications.update'), ['order_updates' => 'email', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email', 'wishlist_price_drops' => 'email']);
        $customer->forceFill(['marketing_opt_out_at' => now()])->save();
        $this->runAlerts();
        Notification::assertNothingSent();
        $this->get(route('account.notifications'))->assertSee('Marketing emails are off, so these are not sent until you turn them back on in My Account.');

        // Both back on: the drop was never told, so it is told now
        $customer->forceFill(['marketing_opt_out_at' => null])->save();
        $this->runAlerts();
        Notification::assertSentTo($customer, WishlistPriceDrop::class, fn (WishlistPriceDrop $alert) => $alert->productIds() === [$kit->id]);

        // Nonsense is refused
        $this->put(route('account.notifications.update'), ['order_updates' => 'email', 'delivery_updates' => 'email', 'marketing' => 'email', 'security' => 'email', 'wishlist_price_drops' => 'sms'])
            ->assertSessionHasErrors('wishlist_price_drops');
    }

    public function test_a_queued_alert_is_dropped_when_the_customer_switched_it_off_meanwhile(): void
    {
        $customer = User::factory()->create();
        $alert = new WishlistPriceDrop([['wishlist' => 1, 'product' => 1, 'variant' => null, 'was' => 200.0, 'now' => 150.0]]);
        $this->assertSame(['mail'], $alert->via($customer));

        $customer->forceFill(['notification_preferences' => ['customer' => ['wishlist_price_drops' => 'off']]])->save();
        $this->assertSame([], $alert->via($customer->fresh()));
    }

    public function test_customers_with_push_subscriptions_also_get_a_push(): void
    {
        config(['webpush.public_key' => 'BPublicKeyForTests', 'webpush.private_key' => 'PrivateKeyForTests', 'webpush.subject' => 'mailto:test@iruali.mv']);
        $customer = User::factory()->create();
        $kit = $this->product(['name' => ['en' => 'Hand line kit']]);
        $this->save($customer, $kit);
        $withoutPush = User::factory()->create();
        $this->save($withoutPush, $kit);
        PushSubscription::create(['user_id' => $customer->id, 'endpoint' => 'https://push.example.com/live', 'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm']);

        $alert = new WishlistPriceDrop([['wishlist' => 1, 'product' => $kit->id, 'variant' => null, 'was' => 200.0, 'now' => 150.0]]);
        $this->assertSame(['mail', WebPushChannel::class], $alert->via($customer));
        $this->assertSame(['mail'], $alert->via($withoutPush));

        $sent = [];
        $client = $this->mock(WebPush::class);
        $client->shouldReceive('sendOneNotification')->once()->andReturnUsing(function (SubscriptionInterface $subscription, string $payload) use (&$sent) {
            $sent[] = json_decode($payload, true);

            return new MessageSentReport(new PsrRequest('POST', $subscription->getEndpoint()), new PsrResponse(201), true);
        });

        $kit->update(['price' => 150]);
        $this->runAlerts();

        $this->assertCount(1, $sent);
        $this->assertSame('Price drop on your wishlist', $sent[0]['title']);
        $this->assertSame('Hand line kit is now MVR 150.00 (was MVR 200.00).', $sent[0]['body']);
        $this->assertSame(route('wishlist'), $sent[0]['url']);
        $this->assertSame('wishlist-price-drop', $sent[0]['tag']);
    }

    public function test_the_email_shows_before_and_after_in_the_customers_language(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['name' => 'Aisha']);
        $kit = $this->product(['name' => ['en' => 'Hand line kit', 'dv' => 'ބޮޑުވަޅި ކިޓް']]);
        $bag = $this->product(['price' => 100, 'name' => ['en' => 'Dry bag']]);
        $this->save($customer, $kit);
        $this->save($customer, $bag);
        $kit->update(['price' => 150]);
        $bag->update(['price' => 80]);
        $this->runAlerts();

        $alert = Notification::sent($customer, WishlistPriceDrop::class)->first();
        $mail = $alert->toMail($customer);
        $html = (string) $mail->render();
        $this->assertSame('Prices dropped on your wishlist', $mail->subject);
        $this->assertStringContainsString('Hello Aisha,', $html);
        $this->assertStringContainsString('Good news: 2 items on your wishlist are cheaper now.', $html);
        $this->assertStringContainsString('Hand line kit', $html);
        $this->assertStringContainsString('MVR 150.00', $html);
        $this->assertMatchesRegularExpression('/MVR 150\.00<br><s style="[^"]*">MVR 200\.00<\/s>/', $html);
        $this->assertStringContainsString('You save MVR 50.00 (25%)', $html);
        $this->assertStringContainsString(route('wishlist'), $html);
        $this->assertStringContainsString(route('account.notifications'), $html);

        // A single item names it in the subject; Dhivehi customers get Dhivehi
        $dv = User::factory()->create(['preferred_language' => 'dv']);
        $this->save($dv, $kit);
        $kit->update(['price' => 100]);
        $this->runAlerts();
        Notification::assertSentTo($dv, WishlistPriceDrop::class, fn (WishlistPriceDrop $n, array $channels, User $notifiable, ?string $locale) => $locale === 'dv');

        app()->setLocale('dv');
        $dvMail = Notification::sent($dv, WishlistPriceDrop::class)->first()->toMail($dv);
        $dvHtml = (string) $dvMail->render();
        app()->setLocale('en');
        $this->assertSame('ބޮޑުވަޅި ކިޓް ގެ އަގު ދަށްވެއްޖެ', $dvMail->subject);
        $this->assertStringContainsString('dir="rtl"', $dvHtml);
        $this->assertStringContainsString('ބޮޑުވަޅި ކިޓް', $dvHtml);
        $this->assertStringContainsString('href="'.url('/dv/products/'.$kit->slug).'"', $dvHtml);
    }

    public function test_items_saved_before_tracking_start_from_the_first_runs_price(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $kit = $this->product(['price' => 200]);
        $item = $this->save($customer, $kit);
        $item->forceFill(['saved_price' => null])->save(); // as rows saved before this feature

        $kit->update(['price' => 150]);
        $this->runAlerts();
        Notification::assertNothingSent();
        $this->assertSame('150.00', $item->fresh()->saved_price);

        $kit->update(['price' => 120]);
        $this->runAlerts();
        Notification::assertSentTo($customer, WishlistPriceDrop::class, fn (WishlistPriceDrop $alert) => $alert->drops[0]['was'] === 150.0 && $alert->drops[0]['now'] === 120.0);
    }

    public function test_a_long_list_is_capped_and_the_rest_wait_for_the_next_day(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $products = collect(range(1, WishlistPriceDropService::MAX_ITEMS + 2))->map(fn (int $n) => $this->product(['price' => 100 + $n]));
        $products->each(fn (Product $product) => $this->save($customer, $product));
        $products->each(fn (Product $product) => $product->update(['price' => 50]));

        $this->runAlerts();
        $first = Notification::sent($customer, WishlistPriceDrop::class)->first();
        $this->assertCount(WishlistPriceDropService::MAX_ITEMS, $first->drops);
        $this->assertSame($products->last()->id, $first->drops[0]['product'], 'the biggest drop comes first');

        $this->travel(1)->days();
        $this->runAlerts();
        $second = Notification::sent($customer, WishlistPriceDrop::class)->last();
        $this->assertCount(2, $second->drops);
        Notification::assertSentTimes(WishlistPriceDrop::class, 2);
    }

    public function test_the_wishlist_page_shows_how_much_less_it_costs_now(): void
    {
        $customer = User::factory()->create();
        $kit = $this->product(['price' => 200, 'name' => ['en' => 'Hand line kit']]);
        $bag = $this->product(['price' => 100, 'name' => ['en' => 'Dry bag']]);
        $this->save($customer, $kit);
        $this->save($customer, $bag);

        $this->actingAs($customer)->get(route('wishlist'))->assertOk()->assertDontSee('less than when you saved it');

        $kit->update(['price' => 150]);
        $bag->update(['price' => 120]);
        $this->get(route('wishlist'))->assertOk()
            ->assertSee('MVR 50.00 less than when you saved it')
            ->assertDontSee('MVR 20.00 less');

        $this->withSession(['locale' => 'dv'])->get(route('wishlist'))->assertOk()->assertSee('ސޭވްކުރެއްވިއިރަށްވުރެ');
    }

    public function test_the_alert_is_scheduled_daily_at_ten_maldives_time(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'wishlist:price-drops'));

        $this->assertNotNull($event);
        $this->assertSame('0 10 * * *', $event->expression);
        $this->assertSame('Indian/Maldives', $event->timezone);
    }
}
