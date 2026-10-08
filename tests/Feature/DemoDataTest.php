<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SavedItem;
use App\Models\StockAlert;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\DemoDataService;
use App\Support\Audit;
use App\Support\DemoData;
use App\Support\FeedToken;
use Database\Seeders\MarketplaceDemoSeeder;
use Database\Seeders\RemoveGenericDemoProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Admin → Settings → Sample data, and php artisan demo:remove / demo:restore: only the demo shops and
 * products in App\Support\DemoData are touched, Restore undoes exactly what Remove did, and no page
 * breaks once the sample products are in the bin.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    /** The site owner's own admin account */
    protected User $owner;

    /** A real shop: nothing of it may ever change */
    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = $this->withRole('admin');
        $this->shop = $this->withRole('seller', ['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Real Reef Shop', 'onboarding_completed_at' => now()]);
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function withRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function demo(): DemoDataService
    {
        return app(DemoDataService::class);
    }

    /** The demo marketplace (6 shops, 18 products) and two old generic demo products under admin@example.com */
    protected function seedSampleData(): void
    {
        $this->seed(MarketplaceDemoSeeder::class);
        $demoAdmin = $this->withRole('admin', ['email' => DemoData::ADMIN_EMAIL]);
        Product::factory()->create(['seller_id' => $demoAdmin->id, 'category_id' => $this->category->id, 'sku' => 'IPHONE15PRO', 'name' => ['en' => 'iPhone 15 Pro']]);
        Product::factory()->create(['seller_id' => $demoAdmin->id, 'category_id' => $this->category->id, 'name' => ['en' => 'Modern Coffee Table']]);
    }

    protected function sample(string $sku): Product
    {
        return Product::withTrashed()->where('sku', $sku)->firstOrFail();
    }

    protected function demoShop(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function realProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'seller_id' => $this->shop->id, 'category_id' => $this->category->id, 'is_active' => true, 'stock_quantity' => 10,
        ], $attributes));
    }

    /** Sample products not in the bin */
    protected function liveSampleCount(): int
    {
        return $this->demo()->status()['products_live'];
    }

    public function test_the_seeders_and_the_control_share_one_list(): void
    {
        $this->seedSampleData();

        $this->assertSame(DemoData::GENERIC_SKUS, RemoveGenericDemoProductsSeeder::SKUS);
        $this->assertSame(DemoData::GENERIC_NAMES, RemoveGenericDemoProductsSeeder::NAMES);
        $this->assertCount(6, DemoData::SHOPS);
        foreach (DemoData::SHOPS as $shop) {
            $account = $this->demoShop($shop['email']);
            $this->assertSame($shop['shop'], $account->business_name);
            $this->assertEqualsCanonicalizing(DemoData::shopSkus()[$shop['email']], Product::where('seller_id', $account->id)->pluck('sku')->all());
        }
    }

    public function test_status_counts_the_sample_data(): void
    {
        $empty = $this->demo()->status();
        $this->assertSame(0, $empty['shops']);
        $this->assertSame(0, $empty['products_live']);
        $this->assertFalse($empty['can_remove']);
        $this->assertFalse($empty['can_restore']);

        $this->seedSampleData();
        $this->realProduct();
        $status = $this->demo()->status();
        $this->assertSame(6, $status['shops']);
        $this->assertSame(6, $status['shops_open']);
        $this->assertSame(20, $status['products_live']);
        $this->assertSame(0, $status['products_removed']);
        $this->assertSame(0, $status['other_products_live']);
        $this->assertContains('Island Crafts', $status['shop_names']);
        $this->assertTrue($status['can_remove']);
        $this->assertFalse($status['can_restore']);

        $this->demo()->remove();

        $status = $this->demo()->status();
        $this->assertSame(6, $status['shops']);
        $this->assertSame(0, $status['shops_open']);
        $this->assertSame(0, $status['products_live']);
        $this->assertSame(20, $status['products_removed']);
        $this->assertSame(20, $status['restorable_products']);
        $this->assertNotNull($status['removed_at']);
        $this->assertFalse($status['can_remove']);
        $this->assertTrue($status['can_restore']);
    }

    public function test_only_the_exact_sample_rows_are_touched(): void
    {
        // A demo shop with one of its own SKUs, and a SKU that belongs to another demo shop
        $islandCrafts = $this->withRole('seller', ['email' => 'islandcrafts@example.com', 'is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $ownSample = Product::factory()->create(['seller_id' => $islandCrafts->id, 'category_id' => $this->category->id, 'sku' => 'IC-MAT-SM']);
        $notItsSku = Product::factory()->create(['seller_id' => $islandCrafts->id, 'category_id' => $this->category->id, 'sku' => 'TL-VASE']);

        // The generic demo products count only under admin@example.com, by exact SKU or exact English name
        $demoAdmin = $this->withRole('admin', ['email' => 'admin@example.com']);
        $genericSku = Product::factory()->create(['seller_id' => $demoAdmin->id, 'category_id' => $this->category->id, 'sku' => 'COFFEEMAKER']);
        $genericName = Product::factory()->create(['seller_id' => $demoAdmin->id, 'category_id' => $this->category->id, 'name' => ['en' => 'Smart LED TV 55"']]);
        $lowerCaseSku = Product::factory()->create(['seller_id' => $demoAdmin->id, 'category_id' => $this->category->id, 'sku' => 'yogamat']);
        $lowerCaseName = Product::factory()->create(['seller_id' => $demoAdmin->id, 'category_id' => $this->category->id, 'name' => ['en' => 'modern coffee table']]);
        $adminsOwn = Product::factory()->create(['seller_id' => $demoAdmin->id, 'category_id' => $this->category->id, 'sku' => 'GIFT-WRAP', 'name' => ['en' => 'Gift wrap']]);

        // Look-alikes that are not sample data
        $caseVariantShop = $this->withRole('seller', ['email' => 'SolarSouth@Example.com', 'is_seller' => true, 'seller_approved' => true]);
        $caseVariantProduct = Product::factory()->create(['seller_id' => $caseVariantShop->id, 'category_id' => $this->category->id, 'sku' => 'SS-FAN-RECH']);
        $otherAdmin = $this->withRole('admin', ['email' => 'admin@example.org']);
        $otherAdminProduct = Product::factory()->create(['seller_id' => $otherAdmin->id, 'category_id' => $this->category->id, 'sku' => 'IPHONE15PRO']);
        $demoSkuElsewhere = $this->realProduct(['sku' => 'IC-MAT-LG']);
        $genericSkuElsewhere = $this->realProduct(['sku' => 'MACBOOKAIRM2']);
        $genericNameElsewhere = $this->realProduct(['name' => ['en' => 'Modern Coffee Table']]);
        $demoNameElsewhere = $this->realProduct(['name' => ['en' => 'Thundu kunaa mat, small']]);

        $removed = $this->demo()->remove();

        $this->assertSame(3, $removed['products']);
        $this->assertSame(1, $removed['shops']);
        foreach ([$ownSample, $genericSku, $genericName] as $sample) {
            $this->assertSoftDeleted($sample);
        }
        // Another shop's SKU under a demo shop is not a sample product: it only goes off sale with the shop
        $this->assertNotSoftDeleted($notItsSku);
        $this->assertFalse($notItsSku->fresh()->is_active);
        foreach ([$lowerCaseSku, $lowerCaseName, $adminsOwn, $caseVariantProduct, $otherAdminProduct, $demoSkuElsewhere, $genericSkuElsewhere, $genericNameElsewhere, $demoNameElsewhere] as $untouched) {
            $this->assertNotSoftDeleted($untouched);
            $this->assertTrue($untouched->fresh()->is_active, $untouched->sku.' must stay on sale');
        }
        $this->assertSame('suspended', $islandCrafts->fresh()->status);
        foreach ([$demoAdmin, $caseVariantShop, $otherAdmin, $this->shop, $this->owner] as $account) {
            $this->assertSame('active', $account->fresh()->status);
        }

        $this->demo()->restore();
        $this->assertNotSoftDeleted($ownSample);
        $this->assertTrue($notItsSku->fresh()->is_active);
        $this->assertSame('active', $islandCrafts->fresh()->status);
    }

    public function test_restore_puts_back_exactly_what_remove_took_off(): void
    {
        $this->seedSampleData();
        $islandCrafts = $this->demoShop('islandcrafts@example.com');
        $reefline = $this->demoShop('reefline@example.com');

        // Before: Reefline was already suspended (Suspend switched its products off), one sample product was
        // already in the bin, one was switched off, Island Crafts lists something of its own and is in a campaign
        $this->actingAs($this->owner)->post(route('admin.sellers.suspend', $reefline))->assertRedirect();
        $alreadyBinned = $this->sample('SS-POWERBANK');
        $alreadyBinned->delete();
        $this->sample('MF-CURRY-MIX')->update(['is_active' => false]);
        $extra = Product::factory()->create(['seller_id' => $islandCrafts->id, 'category_id' => $this->category->id, 'sku' => 'IC-EXTRA', 'is_active' => true]);
        $campaign = Campaign::factory()->create();
        $approvedAt = now()->subDays(2)->startOfSecond();
        CampaignProduct::create(['campaign_id' => $campaign->id, 'product_id' => $this->sample('IC-MAT-SM')->id, 'seller_id' => $islandCrafts->id, 'discount_percent' => 15, 'approved_at' => $approvedAt]);

        $removed = $this->demo()->remove();

        $this->assertSame(19, $removed['products']); // 20 less the one already in the bin
        $this->assertSame(5, $removed['shops']);     // Reefline was suspended already
        $this->assertSame(1, $removed['other_products']);
        $this->assertSame(1, $removed['campaign_entries']);
        $this->assertSame(0, $this->liveSampleCount());
        $this->assertFalse($extra->fresh()->is_active);
        $this->assertSame(0, CampaignProduct::count());
        $this->assertSame(6, User::whereIn('email', DemoData::shopEmails())->where('status', 'suspended')->count());
        $this->assertFalse($this->sample('IC-MAT-SM')->is_active, 'switched off in the bin, so nothing offers it again');

        $restored = $this->demo()->restore();

        $this->assertSame(['products' => 19, 'shops' => 5, 'other_products' => 1, 'campaign_entries' => 1], $restored);
        $this->assertSame('active', $islandCrafts->fresh()->status);
        $this->assertSame('suspended', $reefline->fresh()->status, 'it was suspended before, so it stays suspended');
        $this->assertSoftDeleted($alreadyBinned);
        $this->assertNotSoftDeleted($this->sample('IC-MAT-SM'));
        $this->assertTrue($this->sample('IC-MAT-SM')->is_active);
        $this->assertFalse($this->sample('MF-CURRY-MIX')->is_active, 'it was switched off before');
        $this->assertFalse($this->sample('RM-SNORKEL')->is_active, 'Suspend had switched it off before');
        $this->assertNotSoftDeleted($this->sample('RM-SNORKEL'));
        $this->assertTrue($extra->fresh()->is_active);
        $entry = CampaignProduct::sole();
        $this->assertSame($this->sample('IC-MAT-SM')->id, $entry->product_id);
        $this->assertEquals(15, $entry->discount_percent);
        $this->assertTrue($approvedAt->equalTo($entry->approved_at));

        // Pressing Restore again is harmless, and the whole round trip can be done again
        $this->assertSame(['products' => 0, 'shops' => 0, 'other_products' => 0, 'campaign_entries' => 0], $this->demo()->restore());
        $this->assertFalse($this->demo()->status()['can_restore']);
        $this->assertSame(19, $this->demo()->remove()['products']);
        $this->assertSame(19, $this->demo()->restore()['products']);
        $this->assertSoftDeleted($alreadyBinned);
    }

    public function test_removing_twice_is_harmless_and_restore_still_reopens_the_shops(): void
    {
        $this->seedSampleData();

        $first = $this->demo()->remove();
        $second = $this->demo()->remove();

        $this->assertSame(20, $first['products']);
        $this->assertSame(6, $first['shops']);
        $this->assertSame(0, array_sum($second));
        $this->assertSame(1, AuditLog::where('action', 'demo.removed')->count(), 'a run that changes nothing is not logged');

        $this->demo()->restore();

        $this->assertSame(6, User::whereIn('email', DemoData::shopEmails())->where('status', 'active')->count());
        $this->assertSame(20, $this->liveSampleCount());
    }

    public function test_customers_lose_the_sample_products_and_no_page_breaks(): void
    {
        $this->enableBml();
        $this->seedSampleData();
        $sample = $this->sample('TL-BOX-SM'); // "Lacquer box, small" from Thulhaadhoo Lacquer
        $thulhaadhoo = $this->demoShop('thulhaadhoo@example.com');
        $real = $this->realProduct(['name' => ['en' => 'Reef-safe sunscreen'], 'brand' => 'Reef Kind', 'price' => 150]);
        $deletedBySeller = $this->realProduct(['name' => ['en' => 'Old snorkel strap']]);

        $customer = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $sample->id, 'quantity' => 1, 'price' => $sample->price]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $real->id, 'quantity' => 1, 'price' => $real->price]);
        SavedItem::create(['user_id' => $customer->id, 'product_id' => $this->sample('TL-VASE')->id, 'quantity' => 1]);
        Wishlist::create(['user_id' => $customer->id, 'product_id' => $sample->id]);
        Wishlist::create(['user_id' => $customer->id, 'product_id' => $real->id]);
        Wishlist::create(['user_id' => $customer->id, 'product_id' => $deletedBySeller->id]);
        StockAlert::create(['product_id' => $sample->id, 'email' => 'shopper@example.test', 'locale' => 'en']);
        $deletedBySeller->delete(); // its wishlist row stays behind, as it always has

        // The sitemap and the product feed are cached with the sample data in them
        $feed = FeedToken::url('feeds.google-merchant');
        $this->get('/sitemap.xml')->assertOk()->assertSee($sample->slug)->assertSee('/shops/'.$thulhaadhoo->id.'</loc>', false);
        $this->get($feed)->assertOk()->assertSee('Lacquer box, small');

        $removed = $this->demo()->remove();

        $this->assertSame(1, $removed['cart_items']);
        $this->assertSame(1, $removed['saved_items']);
        $this->assertSame(1, $removed['wishlist_items']);
        $this->assertSame(1, $removed['stock_alerts']);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $sample->id]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $real->id]);
        $this->assertSame(0, SavedItem::count());
        $this->assertDatabaseMissing('wishlists', ['product_id' => $sample->id]);
        $this->assertDatabaseHas('wishlists', ['product_id' => $real->id]);
        $this->assertSame(0, StockAlert::count());

        $this->actingAs($customer)->withSession(['compare' => [$sample->id, $real->id], 'recently_viewed' => [$sample->id]]);
        $this->get(route('cart'))->assertOk()->assertSee('Reef-safe sunscreen')->assertDontSee('Lacquer box');
        $this->get(route('checkout'))->assertOk()->assertSee('Reef-safe sunscreen')->assertDontSee('Lacquer box');
        $this->get(route('wishlist'))->assertOk()->assertSee('Reef-safe sunscreen')->assertDontSee('Lacquer box')->assertDontSee('Old snorkel strap');
        $this->get(route('compare'))->assertOk()->assertSee('Reef-safe sunscreen')->assertDontSee('Lacquer box');
        $this->assertSame([$real->id], session('compare'));
        $this->get(route('products.show', $real))->assertOk()->assertDontSee('Lacquer box');
        $this->get(route('home'))->assertOk()->assertDontSee('Lacquer box')->assertDontSee('Thulhaadhoo Lacquer');
        $this->get(route('shop'))->assertOk()->assertSee('Reef-safe sunscreen')->assertDontSee('Lacquer box');
        $this->get(route('products.index'))->assertOk()->assertDontSee('Lacquer box');
        $this->get(route('deals'))->assertOk()->assertDontSee('Lacquer box');
        $this->get(route('categories.show', $sample->category))->assertOk()->assertDontSee('Lacquer box');
        $this->get(route('search', ['q' => 'lacquer']))->assertOk()->assertDontSee('Lacquer box');
        $this->get(route('search.suggest', ['q' => 'lacquer']))->assertOk()->assertJsonCount(0, 'products')->assertJsonCount(0, 'brands');
        $this->get(route('brands.index'))->assertOk()->assertSee('Reef Kind')->assertDontSee('Thulhaadhoo Lacquer');
        $this->get(route('brands.show', 'thulhaadhoo-lacquer'))->assertNotFound();
        $this->get(route('brands.show', 'reef-kind'))->assertOk()->assertSee('Reef-safe sunscreen');
        $this->get(route('products.show', $sample))->assertNotFound();
        $this->get(route('sellers.show', $thulhaadhoo))->assertNotFound();

        // Rebuilt without the sample data, not served from the cache
        $this->get('/sitemap.xml')->assertOk()->assertSee($real->slug)->assertDontSee($sample->slug)->assertDontSee('/shops/'.$thulhaadhoo->id.'</loc>', false);
        $this->get($feed)->assertOk()->assertSee('Reef-safe sunscreen')->assertDontSee('Lacquer box');

        // A product page left open from before: adding it now is refused, not an error
        $this->post(route('cart.add'), ['product_id' => $sample->id, 'quantity' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('wishlist.add', $sample), ['product_id' => $sample->id])->assertRedirect(route('wishlist'));
        $this->assertDatabaseMissing('cart_items', ['product_id' => $sample->id]);
        $this->assertDatabaseMissing('wishlists', ['product_id' => $sample->id]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/wishlist/add', ['product_id' => $sample->id])->assertStatus(422);
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('data.total_items', 1)->assertJsonPath('data.items.0.product.id', $real->id);
    }

    public function test_sample_products_in_the_compare_list_do_not_block_new_ones(): void
    {
        $this->seedSampleData();
        $samples = Product::whereIn('sku', ['IC-MAT-SM', 'IC-MAT-LG', 'TL-VASE', 'RM-DRYBAG'])->pluck('id')->all();
        $real = $this->realProduct();

        $this->demo()->remove();

        $this->withSession(['compare' => $samples])->post(route('compare.toggle', $real))->assertRedirect();
        $this->assertSame([$real->id], session('compare'));
    }

    public function test_orders_with_sample_products_stay_open_for_customers_shops_and_admins(): void
    {
        $this->seedSampleData();
        $sample = $this->sample('IC-MAT-SM'); // "Thundu kunaa mat, small" from Island Crafts
        $islandCrafts = $this->demoShop('islandcrafts@example.com');
        $real = $this->realProduct(['name' => ['en' => 'Reef-safe sunscreen'], 'price' => 150]);
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'delivered', 'payment_status' => 'paid', 'voucher_code' => null]);
        $order->items()->create(['product_id' => $sample->id, 'quantity' => 1, 'price' => $sample->price]);
        $order->items()->create(['product_id' => $real->id, 'quantity' => 2, 'price' => $real->price]);

        $this->demo()->remove();

        $this->actingAs($customer);
        $this->get(route('orders'))->assertOk();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Thundu kunaa mat, small')->assertSee('Island Crafts');
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Thundu kunaa mat, small');
        // "Buy again" puts back what is still for sale and skips the sample product
        $this->post(route('orders.buyAgain', $order))->assertRedirect(route('cart'));
        $this->assertDatabaseHas('cart_items', ['product_id' => $real->id]);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $sample->id]);

        $this->actingAs($this->owner);
        $this->get(route('admin.orders'))->assertOk();
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Thundu kunaa mat, small')->assertSee('Island Crafts');
        $this->get(route('admin.payouts'))->assertOk()->assertSee('Island Crafts');
        $this->get(route('admin.payouts.create', $islandCrafts))->assertOk();

        $this->actingAs($this->shop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Reef-safe sunscreen');
        $this->actingAs($islandCrafts->fresh())->get(route('seller.dashboard'))->assertForbidden();
    }

    public function test_only_full_admins_can_open_the_page(): void
    {
        $this->seedSampleData();
        $this->get(route('admin.sample-data'))->assertRedirect(route('login'));

        foreach (['support', 'finance', 'seller', 'customer'] as $role) {
            $user = $role === 'customer' ? User::factory()->create() : $this->withRole($role);
            $this->actingAs($user)->get(route('admin.sample-data'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.sample-data.remove'), ['confirm' => 'REMOVE'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.sample-data.restore'))->assertForbidden();
        }
        $this->assertSame(20, $this->liveSampleCount());

        $this->actingAs($this->owner)->get(route('admin.sample-data'))->assertOk();
    }

    public function test_removing_needs_remove_typed_and_both_actions_are_audited(): void
    {
        $this->seedSampleData();
        $this->actingAs($this->owner);

        $this->from(route('admin.sample-data'))->post(route('admin.sample-data.remove'))->assertRedirect(route('admin.sample-data'))->assertSessionHasErrors('confirm');
        $this->post(route('admin.sample-data.remove'), ['confirm' => 'remove'])->assertSessionHasErrors('confirm');
        $this->post(route('admin.sample-data.remove'), ['confirm' => 'DELETE'])->assertSessionHasErrors('confirm');
        $this->assertSame(20, $this->liveSampleCount());
        $this->assertSame(0, AuditLog::where('action', 'demo.removed')->count());

        $this->post(route('admin.sample-data.remove'), ['confirm' => 'REMOVE'])
            ->assertRedirect(route('admin.sample-data'))
            ->assertSessionHas('success', 'Sample data removed. Products taken off the site: 20. Shops closed: 6.');
        $this->assertSame(0, $this->liveSampleCount());
        $log = AuditLog::where('action', 'demo.removed')->sole();
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame(20, $log->changes['products']);
        $this->assertSame(6, $log->changes['shops']);

        $this->post(route('admin.sample-data.remove'), ['confirm' => 'REMOVE'])->assertSessionHas('success', 'There was no sample data left to remove.');

        $this->post(route('admin.sample-data.restore'))
            ->assertRedirect(route('admin.sample-data'))
            ->assertSessionHas('success', 'Sample data restored. Products back on the site: 20. Shops reopened: 6.');
        $this->assertSame(20, $this->liveSampleCount());
        $log = AuditLog::where('action', 'demo.restored')->sole();
        $this->assertSame(20, $log->changes['products']);
        $this->post(route('admin.sample-data.restore'))->assertSessionHas('success', 'There was nothing to restore.');

        $this->assertSame('Sample data removed', Audit::ACTIONS['demo.removed']);
        $this->assertSame('Sample data restored', Audit::ACTIONS['demo.restored']);
        $this->get(route('admin.audit', ['action' => 'demo.removed']))->assertOk()->assertSee('Sample data removed');
    }

    public function test_the_page_shows_the_counts_and_hides_remove_when_there_is_nothing_to_remove(): void
    {
        $this->actingAs($this->owner);

        // A site the demo seeders never ran on (production)
        $this->get(route('admin.sample-data'))->assertOk()
            ->assertSee('There is no sample data on this site, so there is nothing to remove.')
            ->assertDontSee('name="confirm"', false)
            ->assertDontSee(route('admin.sample-data.restore'));
        $this->get(route('admin.settings'))->assertOk()->assertSee(route('admin.sample-data'));

        $this->seedSampleData();
        $this->get(route('admin.sample-data'))->assertOk()
            ->assertSee('data-count="shops">6</dd>', false)
            ->assertSee('data-count="products_live">20</dd>', false)
            ->assertSee('Island Crafts')
            ->assertSee('name="confirm"', false)
            ->assertDontSee(route('admin.sample-data.restore'));

        $this->demo()->remove();
        $this->get(route('admin.sample-data'))->assertOk()
            ->assertSee('data-count="products_removed">20</dd>', false)
            ->assertSee('The sample data is already off the site.')
            ->assertSee('The sample data was removed on')
            ->assertSee('20 products can be restored.')
            ->assertSee(route('admin.sample-data.restore'))
            ->assertDontSee('name="confirm"', false);

        $this->withSession(['locale' => 'dv'])->get(route('admin.sample-data'))->assertOk()->assertSee('ނަމޫނާ ޑޭޓާ އަނބުރާ ގެންނަ');
    }

    public function test_the_artisan_commands_remove_and_restore(): void
    {
        $this->artisan('demo:remove --force')->expectsOutput('There is no sample data to remove.')->assertExitCode(0);
        $this->artisan('demo:restore --force')->expectsOutput('There is no sample data to restore.')->assertExitCode(0);

        $this->seedSampleData();

        $this->artisan('demo:remove')->expectsConfirmation('Remove the sample shops and products now?', 'no')->expectsOutput('Nothing was changed.')->assertExitCode(1);
        $this->assertSame(20, $this->liveSampleCount());

        $this->artisan('demo:remove --force')->expectsOutputToContain('Sample data removed.')->assertExitCode(0);
        $this->assertSame(0, $this->liveSampleCount());
        $this->assertNull(AuditLog::where('action', 'demo.removed')->sole()->user_id);
        $this->artisan('demo:remove --force')->expectsOutput('There is no sample data to remove.')->assertExitCode(0);

        $this->artisan('demo:restore')->expectsConfirmation('Put the removed sample shops and products back now?', 'yes')->expectsOutput('Sample data restored.')->assertExitCode(0);
        $this->assertSame(20, $this->liveSampleCount());
        $this->assertSame(6, User::whereIn('email', DemoData::shopEmails())->where('status', 'active')->count());
        $this->artisan('demo:restore --force')->expectsOutput('There is no sample data to restore.')->assertExitCode(0);
    }

    public function test_the_sitemap_leaves_out_suspended_shops(): void
    {
        $this->realProduct();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/shops/'.$this->shop->id.'</loc>', false);

        $this->actingAs($this->owner)->post(route('admin.sellers.suspend', $this->shop))->assertRedirect();
        \Illuminate\Support\Facades\Cache::forget('sitemap.xml');

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/shops/'.$this->shop->id.'</loc>', false);
    }
}
