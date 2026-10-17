<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Island;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ShopCoverage;
use App\Services\ShoppingIsland;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * "Delivering to <island>": the customer's island choice (picker, location), listings narrowed to
 * shops that deliver there (or only its own shops, or everything with labels), shop coverage
 * (home island, three types, extra islands, whole atoll), checkout's server-side check and the
 * seller and admin settings. Never a gate: no island chosen shows everything.
 */
class ShoppingIslandTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected User $maleShop;        // Malé, delivers to all islands

    protected User $addushop;        // Hithadhoo (Seenu), home island and Greater Malé

    protected User $fuvahShop;       // Fuvahmulah, home island only

    protected Product $maleProduct;

    protected Product $adduProduct;

    protected Product $fuvahProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();

        $this->maleShop = $this->coverageShop('Malé Mart', 'kaafu-male', User::COVERAGE_ALL);
        $this->addushop = $this->coverageShop('Addu Greens', 'seenu-hithadhoo', User::COVERAGE_HOME_AND_MALE);
        $this->fuvahShop = $this->coverageShop('Fuvah Fish', 'gnaviyani-fuvahmulah', User::COVERAGE_HOME_ONLY);

        $this->maleProduct = $this->product($this->maleShop, 100, 0, ['name' => ['en' => 'Coral Kettle', 'dv' => 'ކެޓްލް']]);
        $this->adduProduct = $this->product($this->addushop, 50, 0, ['name' => ['en' => 'Addu Bananas', 'dv' => 'ދޮންކެޔޮ']]);
        $this->fuvahProduct = $this->product($this->fuvahShop, 80, 0, ['name' => ['en' => 'Fuvah Tuna', 'dv' => 'ކަނޑުމަސް']]);
    }

    protected function isl(string $code): Island
    {
        return Island::where('code', $code)->sole();
    }

    protected function coverageShop(string $name, string $home, string $coverage, ?Island $pickup = null): User
    {
        $shop = $this->shop($name, $pickup);
        $shop->forceFill(['home_island_id' => $this->isl($home)->id, 'delivery_coverage' => $coverage, 'onboarding_completed_at' => now()])->save();

        return $shop;
    }

    /** Browse as a guest who chose this island (and view). */
    protected function shoppingFor(string $code, string $view = ShoppingIsland::VIEW_DELIVERS): static
    {
        return $this->withSession([ShoppingIsland::SESSION_ISLAND => $this->isl($code)->id, ShoppingIsland::SESSION_VIEW => $view]);
    }

    public function test_without_an_island_everything_shows_for_people_and_search_engines(): void
    {
        $this->get('/products')->assertOk()->assertSee('Coral Kettle')->assertSee('Addu Bananas')->assertSee('Fuvah Tuna')
            ->assertSee('data-island-banner', false)->assertSee('data-auto-open', false)
            ->assertDontSee('data-island-label="no"', false);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->get('/')->assertOk()->assertSee('Coral Kettle')->assertSee('Fuvah Tuna')
            ->assertDontSee('data-auto-open', false);

        // Closing "Where should we deliver?" keeps everything and stops asking
        $this->postJson(route('delivery.island.dismiss'))->assertOk()->assertJsonPath('island', null)
            ->assertCookie(ShoppingIsland::COOKIE_PROMPT);
        $this->get('/products')->assertSee('Fuvah Tuna')->assertDontSee('data-auto-open', false);
    }

    public function test_choosing_an_island_with_the_picker_endpoint(): void
    {
        $hithadhoo = $this->isl('seenu-hithadhoo');

        $this->getJson(route('delivery.island.show'))->assertOk()->assertJsonPath('island', null)
            ->assertJsonFragment(['name_en' => 'Hithadhoo', 'atoll' => 'Seenu', 'atoll_dv' => 'ސ']);

        $this->postJson(route('delivery.island'), ['island_id' => $hithadhoo->id])->assertOk()
            ->assertJsonPath('island.id', $hithadhoo->id)->assertJsonPath('island.name_dv', 'ހިތަދޫ')
            ->assertJsonPath('view', 'delivers')->assertJsonPath('has_local_shops', true)
            ->assertSessionHas(ShoppingIsland::SESSION_ISLAND, $hithadhoo->id)
            ->assertCookie(ShoppingIsland::COOKIE_ISLAND, (string) $hithadhoo->id);

        $this->get('/products')->assertSee('data-current-island="'.$hithadhoo->id.'"', false)->assertDontSee('data-auto-open', false);

        // Switched-off islands can't be chosen
        $this->isl('kaafu-gulhi')->forceFill(['is_active' => false])->save();
        $this->postJson(route('delivery.island'), ['island_id' => $this->isl('kaafu-gulhi')->id])->assertStatus(422);
    }

    public function test_a_returning_browser_keeps_its_island_by_cookie(): void
    {
        $fuvah = $this->isl('gnaviyani-fuvahmulah');

        $this->withCookie(ShoppingIsland::COOKIE_ISLAND, (string) $fuvah->id)->get('/products')
            ->assertSee('data-current-island="'.$fuvah->id.'"', false)
            ->assertSee('Fuvah Tuna')->assertDontSee('Addu Bananas');
    }

    public function test_choosing_an_island_by_location_and_saving_it_on_the_account(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->postJson(route('delivery.island.locate'), ['lat' => 4.2118, 'lng' => 73.5402])->assertOk()
            ->assertJsonPath('island.name_en', 'Hulhumalé')->assertJsonPath('island.greater_male', true);
        $this->assertSame($this->isl('kaafu-hulhumale')->id, $customer->fresh()->shopping_island_id);

        $this->actingAs($customer)->postJson(route('delivery.island.locate'), ['lat' => 51.5, 'lng' => -0.12])->assertStatus(422)->assertJsonValidationErrors('lat');
        $this->postJson(route('delivery.island.locate'), ['lat' => 'x'])->assertStatus(422);
    }

    public function test_a_signed_in_customer_starts_at_their_default_address_island(): void
    {
        $customer = User::factory()->create();
        $customer->addresses()->create(['recipient_name' => 'Aisha', 'island_id' => $this->isl('gnaviyani-fuvahmulah')->id, 'island' => 'Fuvahmulah', 'atoll' => 'Gnaviyani', 'house_name_or_street' => 'Blue House']);

        $this->actingAs($customer)->get('/products')->assertSee('Fuvah Tuna')->assertSee('Coral Kettle')->assertDontSee('Addu Bananas');

        // The account's own choice wins over the address
        $customer->forceFill(['shopping_island_id' => $this->isl('kaafu-male')->id])->save();
        $this->actingAs($customer)->get('/products')->assertSee('Addu Bananas')->assertDontSee('Fuvah Tuna');
    }

    public function test_outer_islands_see_shops_that_deliver_everywhere_and_not_other_islands_local_shops(): void
    {
        // Hithadhoo: the Malé shop (all islands) and the local shop, not Fuvahmulah's home-only shop
        $this->shoppingFor('seenu-hithadhoo')->get('/products')->assertOk()
            ->assertSee('Coral Kettle')->assertSee('Addu Bananas')->assertDontSee('Fuvah Tuna');

        // Fuvahmulah: its own shop and the Malé shop
        $this->shoppingFor('gnaviyani-fuvahmulah')->get('/products')->assertOk()
            ->assertSee('Coral Kettle')->assertSee('Fuvah Tuna')->assertDontSee('Addu Bananas');

        // Search, departments, deals, suggestions and the home page alike
        $this->shoppingFor('seenu-hithadhoo')->get('/search?q=tuna')->assertOk()->assertDontSee('Fuvah Tuna');
        $this->shoppingFor('seenu-hithadhoo')->getJson(route('search.suggest', ['q' => 'fuvah']))->assertOk()->assertJsonCount(0, 'products');
        $this->shoppingFor('gnaviyani-fuvahmulah')->getJson(route('search.suggest', ['q' => 'fuvah']))->assertOk()->assertJsonCount(1, 'products');
        $this->shoppingFor('seenu-hithadhoo')->get(route('categories.show', $this->fuvahProduct->category))->assertOk()->assertDontSee('Fuvah Tuna');
        $this->shoppingFor('seenu-hithadhoo')->get('/')->assertOk()->assertSee('Coral Kettle')->assertDontSee('Fuvah Tuna');
    }

    public function test_home_and_male_shops_reach_greater_male_but_not_other_atolls(): void
    {
        foreach (['kaafu-male', 'kaafu-hulhumale', 'kaafu-villimale', 'seenu-hithadhoo'] as $code) {
            $this->shoppingFor($code)->get('/products')->assertSee('Addu Bananas');
        }
        foreach (['gnaviyani-fuvahmulah', 'laamu-gan', 'kaafu-maafushi', 'seenu-feydhoo'] as $code) {
            $this->shoppingFor($code)->get('/products')->assertDontSee('Addu Bananas');
        }
    }

    public function test_extra_islands_and_the_whole_atoll(): void
    {
        $coverage = app(ShopCoverage::class);
        $shop = $this->fuvahShop;
        $this->assertFalse($coverage->canDeliverTo($shop, $this->isl('seenu-hithadhoo')));

        $coverage->save($shop, ['home_island_id' => $this->isl('seenu-hithadhoo')->id, 'delivery_coverage' => 'home_only', 'delivery_island_ids' => [$this->isl('gnaviyani-fuvahmulah')->id]]);
        $shop->refresh();
        $this->assertTrue($coverage->canDeliverTo($shop, $this->isl('seenu-hithadhoo')));
        $this->assertTrue($coverage->canDeliverTo($shop, $this->isl('gnaviyani-fuvahmulah')));
        $this->assertFalse($coverage->canDeliverTo($shop, $this->isl('seenu-feydhoo')));
        $this->assertFalse($coverage->canDeliverTo($shop, $this->isl('kaafu-male')), 'home only: not Malé');

        $coverage->save($shop, ['home_island_id' => $this->isl('seenu-hithadhoo')->id, 'delivery_coverage' => 'home_only', 'delivers_to_home_atoll' => '1']);
        $shop->refresh();
        $this->assertTrue($coverage->canDeliverTo($shop, $this->isl('seenu-feydhoo')));
        $this->assertTrue($coverage->canDeliverTo($shop, $this->isl('seenu-meedhoo')));
        $this->assertFalse($coverage->canDeliverTo($shop, $this->isl('shaviyani-feydhoo')), 'the other Feydhoo is in another atoll');
        $this->assertFalse($coverage->canDeliverTo($shop, $this->isl('gnaviyani-fuvahmulah')), 'the extra island was taken off');
        $this->assertSame('Hithadhoo, the whole of Seenu Atoll', $coverage->summary($shop));

        $this->shoppingFor('seenu-meedhoo')->get('/products')->assertSee('Fuvah Tuna');

        // Choosing "all islands" clears the extras
        $coverage->save($shop, ['home_island_id' => $this->isl('seenu-hithadhoo')->id, 'delivery_coverage' => 'all', 'delivers_to_home_atoll' => '1', 'delivery_island_ids' => [$this->isl('kaafu-male')->id]]);
        $this->assertFalse($shop->fresh()->delivers_to_home_atoll);
        $this->assertSame(0, $shop->deliveryIslands()->count());
        $this->assertSame('All islands', $coverage->summary($shop->fresh()));
    }

    public function test_show_everything_labels_what_cannot_be_delivered(): void
    {
        $this->shoppingFor('seenu-hithadhoo', ShoppingIsland::VIEW_ALL)->get('/products')->assertOk()
            ->assertSee('Fuvah Tuna')->assertSee('Addu Bananas')
            ->assertSee('Doesn&#039;t deliver to Hithadhoo', false);

        // The product page says so too, and the shop's own page lists its products with the label
        $this->shoppingFor('seenu-hithadhoo')->get(route('products.show', $this->fuvahProduct))->assertOk()
            ->assertSee('data-island-label="no"', false);
        $this->shoppingFor('seenu-hithadhoo')->get(route('products.show', $this->maleProduct))->assertOk()
            ->assertSee('Delivers to Hithadhoo')->assertSee('Delivery to Hithadhoo');
        $this->shoppingFor('seenu-hithadhoo')->get(route('sellers.show', $this->fuvahShop))->assertOk()
            ->assertSee('Fuvah Tuna')->assertSee('data-island-label="no"', false)
            ->assertSee('Based in Fuvahmulah, Gnaviyani')->assertSee('Delivers to: Fuvahmulah');

        // Switching the view by form
        $this->shoppingFor('seenu-hithadhoo')->from('/products')->post(route('delivery.island.view'), ['view' => 'all'])
            ->assertRedirect('/products')->assertSessionHas(ShoppingIsland::SESSION_VIEW, 'all');
    }

    public function test_only_shops_on_the_island(): void
    {
        $this->shoppingFor('seenu-hithadhoo', ShoppingIsland::VIEW_LOCAL)->get('/products')->assertOk()
            ->assertSee('Addu Bananas')->assertDontSee('Coral Kettle')->assertDontSee('Fuvah Tuna');
        $this->shoppingFor('seenu-hithadhoo', ShoppingIsland::VIEW_LOCAL)->get('/')->assertOk()
            ->assertSee('Addu Bananas')->assertDontSee('Coral Kettle');

        // An island with no shops: the friendly empty state, one click back to what delivers there
        $this->shoppingFor('laamu-gan', ShoppingIsland::VIEW_LOCAL)->get('/products')->assertOk()
            ->assertSee('data-island-notice="local-empty"', false)->assertSee('No shops on Gan yet.')
            ->assertSee('Show what delivers to Gan')->assertSee('name="view" value="delivers"', false);

        // Remembered like the island: session, cookie and the account
        $customer = User::factory()->create();
        $this->actingAs($customer)->postJson(route('delivery.island'), ['island_id' => $this->isl('seenu-hithadhoo')->id, 'view' => 'local'])
            ->assertOk()->assertJsonPath('view', 'local')->assertCookie(ShoppingIsland::COOKIE_VIEW, 'local');
        $this->assertSame('local', $customer->fresh()->shopping_view);
    }

    public function test_home_page_shops_on_the_island_and_the_no_shops_note(): void
    {
        $this->shoppingFor('seenu-hithadhoo')->get('/')->assertOk()
            ->assertSee('data-local-shops', false)->assertSee('Shops on Hithadhoo')->assertSee('Addu Greens')
            ->assertDontSee('data-island-notice', false);

        $this->shoppingFor('laamu-gan')->get('/')->assertOk()
            ->assertDontSee('data-local-shops', false)
            ->assertSee('No shops on Gan yet. These shops deliver to you.')
            ->assertSee('Coral Kettle');
    }

    public function test_answers_never_leak_between_islands_in_one_process(): void
    {
        // The per-request memo is keyed by island: back-to-back requests for different islands differ
        $this->shoppingFor('gnaviyani-fuvahmulah')->get('/products')->assertSee('Fuvah Tuna');
        $this->shoppingFor('seenu-hithadhoo')->get('/products')->assertDontSee('Fuvah Tuna');
        $this->shoppingFor('gnaviyani-fuvahmulah')->get('/products')->assertSee('Fuvah Tuna');

        $coverage = app(ShopCoverage::class);
        $this->assertContains($this->fuvahShop->id, $coverage->blockedSellerIds($this->isl('seenu-hithadhoo')));
        $this->assertNotContains($this->fuvahShop->id, $coverage->blockedSellerIds($this->isl('gnaviyani-fuvahmulah')));

        // The phone app passes the island with each request
        $this->getJson('/api/v1/products?island_id='.$this->isl('seenu-hithadhoo')->id)->assertOk()->assertJsonMissing(['name' => 'Fuvah Tuna'])->assertJsonFragment(['name' => 'Coral Kettle']);
        $this->getJson('/api/v1/products?island_id='.$this->isl('seenu-hithadhoo')->id.'&view=local')->assertOk()
            ->assertJsonFragment(['name' => 'Addu Bananas'])->assertJsonMissing(['name' => 'Coral Kettle']);
    }

    public function test_checkout_refuses_delivery_from_a_shop_that_does_not_reach_the_island(): void
    {
        $customer = User::factory()->create(['phone' => '7771234']);
        $this->cartFor($customer, [[$this->fuvahProduct, 1], [$this->maleProduct, 1]]);

        $this->actingAs($customer)->get('/checkout')->assertOk()->assertSee('data-shop-blocked', false);

        // Typed Malé, and Malé picked from the list: both refused on the server
        $this->placeOrder($customer)->assertRedirect(route('cart'))
            ->assertSessionHas('notification', fn ($n) => str_contains($n['message'], 'Fuvah Fish doesn\'t deliver to Malé'));
        $this->placeOrder($customer, ['island_id' => $this->isl('kaafu-male')->id])->assertRedirect(route('cart'));
        $this->assertSame(0, Order::count());

        // Delivered to Fuvahmulah it goes through (the Malé shop delivers everywhere)
        $this->placeOrder($customer, ['island_id' => $this->isl('gnaviyani-fuvahmulah')->id, 'shipping_city' => 'Fuvahmulah', 'shipping_state' => 'Gnaviyani'])->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_checkout_allows_collecting_from_a_shop_that_does_not_deliver_there(): void
    {
        $fuvah = $this->isl('gnaviyani-fuvahmulah');
        $pickupShop = $this->coverageShop('Fuvah Bakery', 'gnaviyani-fuvahmulah', User::COVERAGE_HOME_ONLY, $fuvah);
        $bread = $this->product($pickupShop, 30);
        $customer = User::factory()->create(['phone' => '7771234']);
        $this->cartFor($customer, [[$bread, 1]]);

        $this->placeOrder($customer)->assertRedirect(route('cart'))
            ->assertSessionHas('notification', fn ($n) => str_contains($n['message'], 'Pick up from Fuvah Bakery'));

        $this->placeOrder($customer, ['fulfilment' => [$pickupShop->id => 'pickup']])->assertRedirect();
        $this->assertSame('pickup', Order::sole()->sellerOrders()->sole()->delivery_method);
    }

    public function test_a_saved_address_on_another_island_decides_for_a_gift(): void
    {
        $customer = User::factory()->create(['phone' => '7771234', 'shopping_island_id' => null]);
        $address = $customer->addresses()->create(['recipient_name' => 'Grandma', 'phone' => '7779999', 'island_id' => $this->isl('gnaviyani-fuvahmulah')->id, 'island' => 'Fuvahmulah', 'atoll' => 'Gnaviyani', 'house_name_or_street' => 'Green House']);
        $this->cartFor($customer, [[$this->fuvahProduct, 1]]);

        // Shopping from Malé, sending a gift to grandma on Fuvahmulah: the gift's island counts
        $this->withSession([ShoppingIsland::SESSION_ISLAND => $this->isl('kaafu-male')->id]);
        $this->placeOrder($customer, ['address_id' => $address->id, 'is_gift' => '1', 'gift_receiver_name' => 'Grandma', 'gift_receiver_phone' => '7779999'])->assertRedirect();
        $this->assertTrue(Order::sole()->isGift());
    }

    public function test_sellers_save_their_home_island_and_coverage(): void
    {
        $shop = $this->shop('Lagoon Crafts');
        $shop->forceFill(['onboarding_completed_at' => now()])->save();

        $this->actingAs($shop)->get('/seller/dashboard')->assertOk()->assertSee('data-home-island-notice', false);
        $this->actingAs($shop)->get('/seller/profile')->assertOk()->assertSee('name="delivery_coverage"', false)->assertSee('My island and Greater Malé');

        $base = ['name' => 'Aisha', 'business_name' => 'Lagoon Crafts'];
        $this->put('/seller/profile', $base + ['delivery_coverage' => 'home_only'])->assertSessionHasErrors('home_island_id');

        $this->put('/seller/profile', $base + [
            'home_island_id' => $this->isl('laamu-gan')->id, 'delivery_coverage' => 'home_only',
            'delivers_to_home_atoll' => '0', 'delivery_island_ids' => [$this->isl('laamu-fonadhoo')->id],
        ])->assertRedirect(route('seller.profile'));

        $shop->refresh();
        $this->assertSame($this->isl('laamu-gan')->id, $shop->home_island_id);
        $this->assertSame('home_only', $shop->delivery_coverage);
        $this->assertSame([$this->isl('laamu-fonadhoo')->id], $shop->deliveryIslands()->pluck('islands.id')->all());
        $this->actingAs($shop)->get('/seller/dashboard')->assertDontSee('data-home-island-notice', false);

        // Not mass-assignable on the user
        $this->assertNotContains('home_island_id', $shop->getFillable());
        $this->assertNotContains('delivery_coverage', $shop->getFillable());
        $this->assertNotContains('shopping_island_id', $shop->getFillable());
    }

    public function test_new_shops_choose_a_home_island_when_they_apply(): void
    {
        $applicant = User::factory()->create();
        $application = ['business_name' => 'Gan Garden', 'business_description' => 'Fresh vegetables and fruit grown in Laamu Gan.', 'phone' => '7775555', 'address' => 'Gan', 'agree_seller_terms' => '1', 'delivery_coverage' => 'home_and_male'];

        $this->actingAs($applicant)->get(route('seller.apply'))->assertOk()->assertSee('name="home_island_id"', false);
        $this->post(route('seller.apply.store'), $application)->assertSessionHasErrors('home_island_id');

        $this->post(route('seller.apply.store'), $application + ['home_island_id' => $this->isl('laamu-gan')->id])->assertRedirect(route('seller.dashboard'));
        $applicant->refresh();
        $this->assertSame('home_and_male', $applicant->delivery_coverage);
        $this->assertSame('Gan', $applicant->city);
        $this->assertSame('Laamu', $applicant->state);
    }

    public function test_admins_see_islands_switch_them_and_edit_a_shops_coverage(): void
    {
        $admin = $this->admin();
        $gan = $this->isl('laamu-gan');

        $this->actingAs($admin)->get(route('admin.islands', ['atoll' => 'Gnaviyani']))->assertOk()
            ->assertSee('Fuvahmulah')->assertSee('data-island-row="'.$this->isl('gnaviyani-fuvahmulah')->id.'"', false);

        $this->post(route('admin.islands.toggle', $gan), ['active' => 0])->assertRedirect();
        $this->assertFalse($gan->fresh()->is_active);
        $this->assertSame(1, AuditLog::where('action', 'island.deactivated')->count());
        $this->getJson(route('delivery.island.show'))->assertJsonMissing(['name_en' => 'Gan']);
        $this->post(route('admin.islands.toggle', $gan), ['active' => 1]);
        $this->assertTrue($gan->fresh()->is_active);

        $this->get(route('admin.sellers.performance', $this->fuvahShop))->assertOk()->assertSee('data-admin-coverage', false);
        $this->put(route('admin.sellers.coverage', $this->fuvahShop), ['home_island_id' => $this->isl('gnaviyani-fuvahmulah')->id, 'delivery_coverage' => 'all'])->assertRedirect();
        $this->assertSame('all', $this->fuvahShop->fresh()->delivery_coverage);
        $this->assertSame(1, AuditLog::where('action', 'seller.coverage')->count());

        // Shop staff and customers can't
        $this->actingAs($this->fuvahShop)->put(route('admin.sellers.coverage', $this->fuvahShop), ['delivery_coverage' => 'home_only'])->assertForbidden();
    }

    public function test_the_phone_app_reads_and_saves_the_island(): void
    {
        $this->getJson('/api/v1/islands')->assertOk()->assertJsonPath('success', true)->assertJsonFragment(['name_en' => 'Malé']);
        $this->getJson('/api/v1/islands/nearest?lat=-0.29&lng=73.42')->assertOk()->assertJsonPath('data.island.name_en', 'Fuvahmulah');

        $customer = User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($customer, ['*']);
        $this->putJson('/api/v1/user/shopping-island', ['island_id' => $this->isl('laamu-gan')->id, 'view' => 'local'])->assertOk()
            ->assertJsonPath('data.island.name_en', 'Gan')->assertJsonPath('data.view', 'local')->assertJsonPath('data.has_local_shops', false);
        $this->getJson('/api/v1/user/shopping-island')->assertOk()->assertJsonPath('data.island.name_en', 'Gan');
    }

    public function test_dhivehi_pages_show_the_island_control_in_dhivehi(): void
    {
        $this->shoppingFor('seenu-hithadhoo')->withSession(['locale' => 'dv'])->get('/dv/products')->assertOk()
            ->assertSee('ޑެލިވަރީ ކުރާނީ')->assertSee('ހިތަދޫ');
    }

    public function test_pages_let_the_site_itself_ask_for_the_location(): void
    {
        // "Use my location" needs geolocation for this origin; frames from elsewhere still get none
        $policy = (string) $this->get('/')->headers->get('Permissions-Policy');
        $this->assertStringContainsString('geolocation=(self)', $policy);
        $this->assertStringContainsString('camera=()', $policy);
    }

    public function test_the_island_question_opens_by_itself_only_on_shopping_pages(): void
    {
        $this->get('/')->assertSee('data-auto-open', false);
        // Never over the sign-in form, checkout or payment: the person came to do something else
        $this->get('/login')->assertDontSee('data-auto-open', false);
        $this->get('/register')->assertDontSee('data-auto-open', false);
    }
}
