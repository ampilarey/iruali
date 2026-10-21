<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Services\PreorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Pre-orders and "coming soon" are for the shops an admin allows (User::allowsPreorders(), Admin →
 * Sellers → the shop's page): the switch, the product form, and what shoppers can order.
 *
 * The clock is 20 Oct 2026.
 */
class PreorderPermissionTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected User $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));

        // A trusted shop (its edits go live without review) that no admin has allowed pre-orders yet
        $this->shop = $this->seller('Coral Mart');
        $this->shop->forceFill(['preorders_allowed' => false, 'auto_approve_listings' => true, 'onboarding_completed_at' => now()])->save();
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

    public function test_a_shop_not_allowed_cannot_switch_on_pre_orders_or_coming_soon(): void
    {
        $lamp = $this->stockedProduct($this->shop, 300, 0);

        $this->actingAs($this->shop)->get(route('seller.products.edit', $lamp))->assertOk()
            ->assertDontSee('data-preorder-fieldset', false)
            ->assertDontSee('data-coming-soon-fieldset', false)
            ->assertSee('data-presales-off', false)
            ->assertSee('Pre-orders and "coming soon" are for shops iruali allows. If your shop needs them, ask us.');

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

    public function test_only_admins_allow_a_shop_and_it_is_audited(): void
    {
        foreach (['catalogue', 'support', 'finance'] as $role) {
            $this->actingAs($this->staffMember($role))->post(route('admin.sellers.preorders', $this->shop), ['enabled' => 1])->assertForbidden();
        }
        $this->assertFalse($this->shop->fresh()->allowsPreorders(), 'off for every shop by default');

        $admin = $this->staffMember('admin');
        $this->actingAs($admin)->get(route('admin.sellers.performance', $this->shop))->assertOk()
            ->assertSee('data-preorders-allowed="off"', false)->assertSee('Allow pre-orders');
        $this->post(route('admin.sellers.preorders', $this->shop), ['enabled' => 1])->assertRedirect()
            ->assertSessionHas('success', 'Coral Mart can now take pre-orders and list products as "coming soon".');
        $shop = $this->shop->fresh();
        $this->assertTrue($shop->allowsPreorders());
        $this->assertSame($admin->id, (int) $shop->preorders_allowed_by);
        $this->assertNotNull($shop->preorders_allowed_at);
        $this->assertTrue(AuditLog::where('action', 'seller.preorders_on')->where('subject_id', $shop->id)->where('user_id', $admin->id)->exists());
        $this->get(route('admin.sellers.performance', $shop))->assertSee('data-preorders-allowed="on"', false);
        $this->get(route('admin.sellers'))->assertOk()->assertSee('data-seller-preorders', false);

        // The shop can now switch them on
        $lamp = $this->stockedProduct($shop, 300, 0);
        $this->actingAs($shop)->get(route('seller.products.edit', $lamp))->assertOk()
            ->assertSee('data-preorder-fieldset', false)->assertSee('data-coming-soon-fieldset', false)->assertDontSee('data-presales-off', false);
        $this->put(route('seller.products.update', $lamp), $this->form($lamp, ['coming_soon' => 0]))->assertSessionHasNoErrors();
        $this->assertTrue($lamp->fresh()->preorder_enabled);
        $this->get(route('products.show', $lamp->fresh()))->assertOk()->assertSee('data-preorder-notice', false); // the form renamed it: a new address

        // Off again: no new pre-orders, though the product keeps its settings
        $this->actingAs($admin)->post(route('admin.sellers.preorders', $shop), ['enabled' => 0])->assertSessionHas('success');
        $this->assertFalse($shop->fresh()->allowsPreorders());
        $this->assertTrue(AuditLog::where('action', 'seller.preorders_off')->exists());
        $this->assertTrue($lamp->fresh()->preorder_enabled);

        // A shop cannot allow itself through its profile form
        $this->actingAs($shop)->put(route('seller.profile.update'), ['name' => $shop->name, 'email' => $shop->email, 'preorders_allowed' => 1]);
        $this->assertFalse($shop->fresh()->allowsPreorders());
    }

    public function test_a_shop_not_allowed_takes_no_new_pre_orders_and_can_still_switch_old_ones_off(): void
    {
        // Set up before the switch existed (or before an admin turned it off)
        $lamp = $this->preorderProduct($this->shop, ['name' => 'Reef Lamp']);
        $this->assertFalse(app(PreorderService::class)->accepting($lamp));
        $this->assertFalse(app(PreorderService::class)->isPreorderable($lamp));
        $this->assertSame(0, Product::query()->preorderable()->count());

        $this->get(route('products.show', $lamp))->assertOk()->assertDontSee('data-preorder-notice', false)->assertSee('Out of stock');
        $this->get(route('shop', ['preorder' => 1]))->assertOk()->assertDontSee('Reef Lamp');

        $customer = User::factory()->create();
        $result = $this->order($customer, [[$lamp, 1]]);
        $this->assertFalse($result['success']);

        // The shop sees why, and can switch them off (not on again)
        $this->actingAs($this->shop)->get(route('seller.products.edit', $lamp))->assertOk()
            ->assertSee('data-preorder-fieldset', false)->assertSee('data-preorders-not-allowed', false)
            ->assertDontSee('data-coming-soon-fieldset', false);
        $this->get(route('seller.preorders'))->assertOk()->assertSee('Not taking new pre-orders');
        $this->put(route('seller.products.update', $lamp), $this->form($lamp, ['preorder_enabled' => 0, 'coming_soon' => 0]))->assertSessionHasNoErrors();
        $this->assertFalse($lamp->fresh()->preorder_enabled);
        $this->get(route('seller.products.edit', $lamp))->assertOk()->assertDontSee('data-preorder-fieldset', false);

        // Allowed again, the same kind of product takes pre-orders
        $this->shop->forceFill(['preorders_allowed' => true])->save();
        $vase = $this->preorderProduct($this->shop->fresh(), ['name' => 'Coral Vase']);
        $this->assertTrue(app(PreorderService::class)->isPreorderable($vase));
        $this->assertSame(1, Product::query()->preorderable()->count());
        $this->assertTrue($this->order($customer, [[$vase, 1]])['success']);
    }
}
