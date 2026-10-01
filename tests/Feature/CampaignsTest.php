<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Campaigns: date windows, the campaign price through the cart and the order snapshot,
 * shops joining and admins approving, and who may do what.
 */
class CampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Goods']);
        $this->seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => Category::factory()->create(['status' => 'active'])->id,
            'seller_id' => $this->seller->id,
            'is_active' => true,
            'stock_quantity' => 10,
            'price' => 200,
            'compare_price' => null,
        ], $attributes));
    }

    protected function join(Campaign $campaign, Product $product, ?float $discount = null, bool $approved = true): CampaignProduct
    {
        return CampaignProduct::create([
            'campaign_id' => $campaign->id, 'product_id' => $product->id, 'seller_id' => $product->seller_id,
            'discount_percent' => $discount, 'approved_at' => $approved ? now() : null,
        ]);
    }

    // ---- Windows and pages ------------------------------------------------------------------

    public function test_only_campaigns_within_their_dates_and_switched_on_are_live(): void
    {
        $live = Campaign::factory()->create(['name' => 'Live sale', 'headline' => ['en' => 'Live sale']]);
        $past = Campaign::factory()->create(['name' => 'Past sale', 'headline' => ['en' => 'Past sale'], 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeek()]);
        $future = Campaign::factory()->create(['name' => 'Future sale', 'headline' => ['en' => 'Future sale'], 'starts_at' => now()->addDay(), 'ends_at' => now()->addWeek()]);
        $off = Campaign::factory()->create(['name' => 'Off sale', 'is_active' => false]);

        $this->assertSame([$live->id], Campaign::live()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$live->id, $future->id], Campaign::joinable()->pluck('id')->all());

        $this->get(route('campaigns.show', $live))->assertOk()->assertSee($live->headline)->assertSee('data-countdown', false);
        $this->get(route('campaigns.show', $future))->assertOk()->assertSee('Starts');
        $this->get(route('campaigns.show', $past))->assertNotFound();
        $this->get(route('campaigns.show', $off))->assertNotFound();

        $this->get(route('campaigns.index'))->assertOk()->assertSee('Live sale')->assertDontSee('Future sale')->assertDontSee('Past sale');
    }

    public function test_home_page_shows_the_hero_and_strip_campaigns_only_while_they_run(): void
    {
        Campaign::factory()->create(['headline' => ['en' => 'Big Eid Sale'], 'placement' => 'home_hero']);
        Campaign::factory()->create(['headline' => ['en' => 'Back to school strip'], 'placement' => 'home_strip']);
        Campaign::factory()->create(['headline' => ['en' => 'Finished sale'], 'placement' => 'home_hero', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeek()]);

        $this->get('/')->assertOk()->assertSee('Big Eid Sale')->assertSee('Back to school strip')->assertDontSee('Finished sale')->assertDontSee('Local shops. Island delivery.');

        $this->travel(2)->weeks();
        $this->get('/')->assertOk()->assertDontSee('Big Eid Sale')->assertSee('Local shops. Island delivery.');
    }

    // ---- Price pipeline ---------------------------------------------------------------------

    public function test_campaign_price_applies_only_for_approved_participation_in_a_live_campaign(): void
    {
        $product = $this->product(['price' => 200]);
        $campaign = Campaign::factory()->create(['discount_percent' => 10]);

        $this->assertNull($product->campaignPrice());
        $this->assertEquals(200, (float) $product->final_price);

        $row = $this->join($campaign, $product, null, approved: false);
        $this->assertNull($product->fresh()->campaignPrice(), 'pending participation gives no discount');

        $row->update(['approved_at' => now()]);
        $product = $product->fresh();
        $this->assertEquals(180, $product->campaignPrice());
        $this->assertEquals(180, (float) $product->final_price);
        $this->assertEquals(200, $product->was_price, 'the list price is shown struck through');
        $this->assertSame(10, $product->discount_percentage);

        // The shop's own bigger discount wins over the campaign minimum
        $row->update(['discount_percent' => 25]);
        $this->assertEquals(150, $product->fresh()->campaignPrice());

        // Out of the window: back to the list price
        $this->travel(2)->weeks();
        $this->assertNull($product->fresh()->campaignPrice());
        $this->assertEquals(200, (float) $product->fresh()->final_price);
    }

    public function test_the_lowest_price_across_several_live_campaigns_wins_and_variants_follow(): void
    {
        $product = $this->product(['price' => 100, 'has_variants' => true]);
        $variant = ProductVariant::factory()->for($product)->attributes(['Size' => 'M'])->stock(5)->priced(80)->create(['sku' => 'V-1']);
        $this->join(Campaign::factory()->create(['discount_percent' => 10]), $product);
        $this->join(Campaign::factory()->create(['discount_percent' => 30]), $product);

        $this->assertEquals(70, $product->fresh()->campaignPrice());
        $this->assertEquals(56, $variant->fresh()->setRelation('product', $product->fresh())->effectivePrice());
    }

    public function test_the_campaign_price_is_charged_in_the_cart_and_snapshotted_on_the_order(): void
    {
        $product = $this->product(['price' => 200]);
        $this->join(Campaign::factory()->create(['discount_percent' => 20]), $product);
        $user = User::factory()->create();

        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 200]);

        $this->assertEquals(160, $cart->items()->first()->unit_price);
        $this->assertEquals(320, $cart->fresh()->total);

        $result = app(OrderService::class)->createOrderFromCart($user, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Male', 'shipping_state' => 'Kaafu',
            'shipping_zip' => '20026', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml', 'delivery_zone' => 'greater_male',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $order = $result['order'];

        $this->assertEquals(160, (float) $order->items()->first()->price);
        $this->assertEquals(320 + (float) $order->shipping_amount, (float) $order->total_amount);

        // The campaign ending later does not change what was charged
        $this->travel(2)->weeks();
        $this->assertEquals(160, (float) $order->fresh()->items()->first()->price);
    }

    // ---- Shops joining, admins approving ------------------------------------------------------

    public function test_a_shop_joins_with_its_own_products_at_or_above_the_minimum_and_an_admin_approves(): void
    {
        $campaign = Campaign::factory()->create(['discount_percent' => 15]);
        $mine = $this->product(['name' => ['en' => 'Reef lamp XQ']]);
        $other = $this->product(['name' => ['en' => 'Other shop kettle ZQ'], 'seller_id' => User::factory()->create(['is_seller' => true, 'seller_approved' => true])->id]);

        $this->actingAs($this->seller)->get(route('seller.campaigns'))->assertOk()->assertSee($campaign->name)->assertSee('Join campaign');
        $this->actingAs($this->seller)->get(route('seller.campaigns.show', $campaign))->assertOk()->assertSee($mine->name)->assertDontSee($other->name);

        // Below the minimum
        $this->actingAs($this->seller)->post(route('seller.campaigns.store', $campaign), ['products' => [$mine->id], 'discount_percent' => 10])
            ->assertSessionHasErrors('discount_percent');

        // Someone else's product
        $this->actingAs($this->seller)->post(route('seller.campaigns.store', $campaign), ['products' => [$other->id], 'discount_percent' => 20])->assertForbidden();

        $this->actingAs($this->seller)->post(route('seller.campaigns.store', $campaign), ['products' => [$mine->id], 'discount_percent' => 20])
            ->assertRedirect(route('seller.campaigns.show', $campaign));
        $row = CampaignProduct::where('campaign_id', $campaign->id)->where('product_id', $mine->id)->firstOrFail();
        $this->assertNull($row->approved_at);
        $this->assertSame($this->seller->id, $row->seller_id);
        $this->assertEquals(20, (float) $row->discount_percent);
        $this->assertNull($mine->fresh()->campaignPrice(), 'no price change until approved');

        $this->actingAs($this->admin)->get(route('admin.campaigns.edit', $campaign))->assertOk()->assertSee($mine->name)->assertSee('Pending');
        $this->actingAs($this->admin)->post(route('admin.campaigns.approve', [$campaign, $row]))->assertRedirect();
        $this->assertNotNull($row->fresh()->approved_at);
        $this->assertEquals(160, $mine->fresh()->campaignPrice());

        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee($mine->name);

        // Changing the discount sends it back for approval; leaving removes it
        $this->actingAs($this->seller)->post(route('seller.campaigns.store', $campaign), ['products' => [$mine->id], 'discount_percent' => 30]);
        $this->assertNull($row->fresh()->approved_at);
        $this->actingAs($this->seller)->delete(route('seller.campaigns.leave', [$campaign, $row]))->assertRedirect();
        $this->assertDatabaseMissing('campaign_products', ['id' => $row->id]);
    }

    public function test_only_sellers_and_admins_reach_their_campaign_pages(): void
    {
        $campaign = Campaign::factory()->create();
        $customer = User::factory()->create();
        $row = $this->join($campaign, $this->product(), approved: false);

        $this->get(route('admin.campaigns.index'))->assertRedirect(route('login'));
        $this->get(route('seller.campaigns'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('seller.campaigns'))->assertForbidden();
        $this->actingAs($customer)->post(route('seller.campaigns.store', $campaign), ['products' => [1], 'discount_percent' => 20])->assertForbidden();
        $this->actingAs($customer)->get(route('admin.campaigns.index'))->assertForbidden();
        $this->actingAs($this->seller)->get(route('admin.campaigns.index'))->assertForbidden();
        $this->actingAs($this->seller)->post(route('admin.campaigns.approve', [$campaign, $row]))->assertForbidden();

        // A shop can't remove another shop's participation
        $otherSeller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $otherSeller->roles()->attach(Role::where('name', 'seller')->first()->id);
        $this->actingAs($otherSeller)->delete(route('seller.campaigns.leave', [$campaign, $row]))->assertForbidden();
        $this->assertDatabaseHas('campaign_products', ['id' => $row->id]);
    }

    public function test_admin_creates_schedules_and_uploads_a_banner(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->get(route('admin.campaigns.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('admin.campaigns.store'), [
            'name' => 'Eid Sale', 'type' => 'sale', 'placement' => 'home_hero',
            'starts_at' => now()->addDay()->format('Y-m-d H:i'), 'ends_at' => now()->addDays(5)->format('Y-m-d H:i'),
            'headline_en' => 'Eid Sale: up to 30% off', 'headline_dv' => 'ޢީދު ސޭލް',
            'subheadline_en' => 'From shops across the islands', 'cta_text_en' => 'Shop now',
            'theme_colour' => '#AA3366', 'is_active' => 1, 'discount_percent' => 15,
            'banner_image' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
        ])->assertRedirect();

        $campaign = Campaign::where('name', 'Eid Sale')->firstOrFail();
        $this->assertSame('eid-sale', $campaign->slug);
        $this->assertSame('ޢީދު ސޭލް', $campaign->getTranslation('headline', 'dv'));
        $this->assertStringStartsWith('/storage/campaigns/', $campaign->banner_image);
        $this->assertTrue($campaign->isUpcoming());

        $this->actingAs($this->admin)->get(route('admin.campaigns.index'))->assertOk()->assertSee('Eid Sale')->assertSee('Scheduled');

        // Ends before it starts is refused
        $this->actingAs($this->admin)->put(route('admin.campaigns.update', $campaign), [
            'name' => 'Eid Sale', 'type' => 'sale', 'placement' => 'home_hero', 'theme_colour' => '#AA3366',
            'starts_at' => now()->addDays(5)->format('Y-m-d H:i'), 'ends_at' => now()->addDay()->format('Y-m-d H:i'), 'headline_en' => 'x',
        ])->assertSessionHasErrors('ends_at');

        $this->actingAs($this->admin)->delete(route('admin.campaigns.destroy', $campaign))->assertRedirect(route('admin.campaigns.index'));
        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }
}
