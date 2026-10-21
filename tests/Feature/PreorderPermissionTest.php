<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\NowOnSale;
use App\Services\PreorderService;
use App\Services\ProductReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Pre-orders and "coming soon" are for the shops an admin allows, each on its own
 * (User::allowsPreorders(), allowsComingSoon(); Admin → Sellers → the shop's page): a shop sees only
 * what it is allowed, the switches, turning them off, and what shoppers can order.
 *
 * The clock is 20 Oct 2026.
 */
class PreorderPermissionTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected User $shop;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));

        // A trusted shop (its edits go live without review) that no admin has allowed either yet
        $this->shop = $this->seller('Coral Mart');
        $this->shop->forceFill(['preorders_allowed' => false, 'auto_approve_listings' => true, 'onboarding_completed_at' => now()])->save();
        $this->admin = $this->staffMember('admin');
    }

    /**
     * The product form with pre-orders and "coming soon" switched on.
     *
     * @return array<string, mixed>
     */
    protected function form(Product $product, array $changes = []): array
    {
        return array_merge([
            'name_en' => 'Reef Lamp', 'sku' => $product->sku, 'category_id' => $product->category_id, 'price' => 300,
            'stock_quantity' => 0, 'has_variants' => 0, 'reorder_point' => 0,
            'preorder_form' => 1, 'preorder_enabled' => 1, 'preorder_ships' => 'date', 'preorder_ship_date' => '2026-11-03', 'preorder_limit' => 5,
            'coming_soon_form' => 1, 'coming_soon' => 1, 'available_on' => '2026-12-01',
        ], $changes);
    }

    /** An admin turns one of the shop's switches on or off. */
    protected function allow(string $route, bool $on): void
    {
        $this->actingAs($this->admin)->post(route($route, $this->shop), ['enabled' => $on ? 1 : 0])->assertRedirect()->assertSessionHas('success');
        $this->shop->refresh();
    }

    public function test_a_shop_not_allowed_sees_nothing_of_pre_orders_or_coming_soon(): void
    {
        $lamp = $this->stockedProduct($this->shop, 300, 0);

        foreach ([route('seller.products.edit', $lamp), route('seller.products.create'), route('seller.dashboard')] as $page) {
            $this->actingAs($this->shop)->get($page)->assertOk()
                ->assertDontSee('data-preorder-fieldset', false)
                ->assertDontSee('data-coming-soon-fieldset', false)
                ->assertDontSee('data-seller-tab="seller.preorders"', false)
                ->assertDontSee('Pre-order')
                ->assertDontSee('Coming soon');
        }
        $this->get(route('seller.preorders'))->assertNotFound();

        // Sent anyway, the fieldsets are ignored: nothing is switched on, and nothing is refused either
        $this->put(route('seller.products.update', $lamp), $this->form($lamp))->assertSessionHasNoErrors();
        $lamp->refresh();
        $this->assertFalse($lamp->preorder_enabled);
        $this->assertFalse($lamp->coming_soon);
        $this->assertNull($lamp->preorder_ship_date);

        // Nor on a new product
        $this->post(route('seller.products.store'), $this->form($lamp, ['sku' => 'LAMP-NEW']))->assertSessionHasNoErrors();
        $new = Product::where('sku', 'LAMP-NEW')->sole();
        $this->assertFalse($new->preorder_enabled);
        $this->assertFalse($new->coming_soon);
    }

    public function test_each_is_allowed_on_its_own_and_the_shop_sees_only_that(): void
    {
        foreach (['catalogue', 'support', 'finance'] as $role) {
            $this->actingAs($this->staffMember($role))->post(route('admin.sellers.preorders', $this->shop), ['enabled' => 1])->assertForbidden();
            $this->post(route('admin.sellers.coming-soon', $this->shop), ['enabled' => 1])->assertForbidden();
        }
        $this->assertFalse($this->shop->fresh()->allowsPreorders(), 'off for every shop by default');
        $this->assertFalse($this->shop->fresh()->allowsComingSoon());

        $this->actingAs($this->admin)->get(route('admin.sellers.performance', $this->shop))->assertOk()
            ->assertSee('data-preorders-allowed="off"', false)->assertSee('data-coming-soon-allowed="off"', false)
            ->assertSee('Allow pre-orders')->assertSee('Allow &quot;coming soon&quot;', false);

        // "Coming soon" only: the shop sees that, not pre-orders
        $this->allow('admin.sellers.coming-soon', true);
        $this->assertTrue($this->shop->allowsComingSoon());
        $this->assertFalse($this->shop->allowsPreorders());
        $this->assertSame($this->admin->id, (int) $this->shop->coming_soon_allowed_by);
        $this->assertTrue(AuditLog::where('action', 'seller.coming_soon_on')->where('subject_id', $this->shop->id)->where('user_id', $this->admin->id)->exists());
        $this->get(route('admin.sellers'))->assertOk()->assertSee('data-seller-coming-soon', false)->assertDontSee('data-seller-preorders', false);

        $lamp = $this->stockedProduct($this->shop, 300, 0);
        $this->actingAs($this->shop)->get(route('seller.products.edit', $lamp))->assertOk()
            ->assertSee('data-coming-soon-fieldset', false)
            ->assertDontSee('data-preorder-fieldset', false)
            ->assertDontSee('data-seller-tab="seller.preorders"', false)
            ->assertDontSee('To take payment before it arrives, use Pre-order below instead.');
        $this->get(route('seller.preorders'))->assertNotFound();
        $this->put(route('seller.products.update', $lamp), $this->form($lamp))->assertSessionHasNoErrors();
        $lamp->refresh();
        $this->assertTrue($lamp->coming_soon);
        $this->assertSame('2026-12-01', $lamp->available_on->toDateString());
        $this->assertFalse($lamp->preorder_enabled, 'pre-orders are not allowed');

        // Pre-orders too: now it sees both, and the Pre-orders tab
        $this->allow('admin.sellers.preorders', true);
        $this->assertTrue($this->shop->allowsPreorders());
        $this->assertTrue(AuditLog::where('action', 'seller.preorders_on')->where('subject_id', $this->shop->id)->exists());
        $this->get(route('admin.sellers'))->assertOk()->assertSee('data-seller-preorders', false);
        $this->actingAs($this->shop)->get(route('seller.products.edit', $lamp))->assertOk()
            ->assertSee('data-coming-soon-fieldset', false)->assertSee('data-preorder-fieldset', false)
            ->assertSee('data-seller-tab="seller.preorders"', false);
        $this->get(route('seller.preorders'))->assertOk();
        $this->put(route('seller.products.update', $lamp), $this->form($lamp, ['coming_soon' => 0]))->assertSessionHasNoErrors();
        $this->assertTrue($lamp->fresh()->preorder_enabled);
        $this->get(route('products.show', $lamp->fresh()))->assertOk()->assertSee('data-preorder-notice', false); // the form renamed it: a new address

        // A shop cannot allow itself through its profile form
        $this->actingAs($this->shop)->put(route('seller.profile.update'), ['name' => $this->shop->name, 'email' => $this->shop->email, 'preorders_allowed' => 0, 'coming_soon_allowed' => 1]);
        $this->assertTrue($this->shop->fresh()->allowsPreorders());
    }

    public function test_turning_them_off_switches_them_off_on_the_shops_products_and_orders_carry_on(): void
    {
        $this->allow('admin.sellers.preorders', true);
        $this->allow('admin.sellers.coming-soon', true);
        $vase = $this->preorderProduct($this->shop, ['name' => 'Coral Vase']);
        $lamp = $this->stockedProduct($this->shop, 300, 4);
        $lamp->forceFill(['coming_soon' => true, 'available_on' => '2026-12-01'])->save();
        StockAlert::create(['product_id' => $lamp->id, 'email' => 'fan@example.test', 'locale' => 'en', 'for_launch' => true]);
        $customer = User::factory()->create();
        $order = $this->paidOrder($customer, [[$vase, 1]]);

        // Pre-orders off: no new ones, its products' pre-orders switched off (their settings kept)
        $this->allow('admin.sellers.preorders', false);
        $this->assertFalse($this->shop->allowsPreorders());
        $this->assertTrue(AuditLog::where('action', 'seller.preorders_off')->exists());
        $vase->refresh();
        $this->assertFalse($vase->preorder_enabled);
        $this->assertSame(5, $vase->preorder_limit);
        $this->assertFalse(app(PreorderService::class)->isPreorderable($vase));
        $this->assertFalse($this->order(User::factory()->create(), [[$vase, 1]])['success']);

        // The order already placed carries on: the shop still has its Pre-orders page for it
        $this->actingAs($this->shop)->get(route('seller.preorders'))->assertOk()->assertSee('#'.$order->order_number)
            ->assertSee('data-seller-tab="seller.preorders"', false);
        $this->get(route('seller.products.edit', $vase))->assertOk()->assertDontSee('data-preorder-fieldset', false);
        $this->post(route('seller.preorders.arrived', $vase->id), ['received' => [0 => 1]])->assertSessionHas('success');
        $this->assertNotNull($order->items()->sole()->preorder_allocated_at);
        $this->get(route('seller.preorders'))->assertNotFound(); // nothing left to finish
        $this->get(route('seller.dashboard'))->assertOk()->assertDontSee('data-seller-tab="seller.preorders"', false);

        // "Coming soon" off: those products are ordinary listings, and whoever asked is told it is on sale
        $this->allow('admin.sellers.coming-soon', false);
        $this->assertFalse($this->shop->allowsComingSoon());
        $this->assertTrue(AuditLog::where('action', 'seller.coming_soon_off')->exists());
        $lamp->refresh();
        $this->assertFalse($lamp->coming_soon);
        $this->assertNull($lamp->available_on);
        Notification::assertSentOnDemand(NowOnSale::class);
    }

    public function test_an_edit_approved_after_the_shop_lost_pre_orders_keeps_them_off(): void
    {
        // A shop whose edits wait for review asks for pre-orders on a live product while it may
        $this->shop->forceFill(['auto_approve_listings' => false])->save();
        $this->allow('admin.sellers.preorders', true);
        $lamp = $this->stockedProduct($this->shop, 300, 0);
        $lamp->forceFill(['approved_at' => now()])->save();
        $this->actingAs($this->shop)->put(route('seller.products.update', $lamp), $this->form($lamp, ['name_en' => $lamp->getTranslation('name', 'en'), 'coming_soon' => 0]))
            ->assertSessionHasNoErrors();
        $change = ProductChangeRequest::sole();
        $this->assertFalse($lamp->fresh()->preorder_enabled, 'waits for review');

        // ...then loses them before the change is approved
        $this->allow('admin.sellers.preorders', false);
        app(ProductReviewService::class)->approve($change, $this->admin);
        $this->assertFalse($lamp->fresh()->preorder_enabled);
    }

    public function test_only_an_allowed_shop_takes_pre_orders(): void
    {
        // Set up behind the switch's back: still none taken
        $jar = $this->preorderProduct($this->shop, ['name' => 'Shell Jar']);
        $this->assertFalse(app(PreorderService::class)->accepting($jar));
        $this->assertSame(0, Product::query()->preorderable()->count());
        $this->get(route('products.show', $jar))->assertOk()->assertDontSee('data-preorder-notice', false)->assertSee('Out of stock');
        $this->assertFalse($this->order(User::factory()->create(), [[$jar, 1]])['success']);

        $this->allow('admin.sellers.preorders', true);
        $vase = $this->preorderProduct($this->shop, ['name' => 'Coral Vase']);
        $this->assertTrue(app(PreorderService::class)->isPreorderable($vase));
        $this->assertSame(2, Product::query()->preorderable()->count(), 'the jar too, now its shop is allowed');
        $this->get(route('shop', ['preorder' => 1]))->assertOk()->assertSee('Coral Vase');
        $this->assertTrue($this->order(User::factory()->create(), [[$vase, 1]])['success']);
    }
}
