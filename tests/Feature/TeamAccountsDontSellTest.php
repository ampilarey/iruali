<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\CurrentShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * iruali's team (support, finance, catalogue) runs the website and never sells: a team account can't
 * apply for a shop, be approved as one or open the Seller Centre. An admin account may also be
 * iruali's own shop, so the owner signs in once and switches between Admin and the Seller Centre
 * (User::maySell()). The other way round, a shop account is never made staff (StaffRoleController,
 * staff:create).
 */
class TeamAccountsDontSellTest extends TestCase
{
    use RefreshDatabase;

    protected function withRole(User $user, string $role): User
    {
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user->fresh();
    }

    protected function teamMember(string $role): User
    {
        return $this->withRole(User::factory()->create(), $role);
    }

    /** An account that also has a shop. */
    protected function teamMemberWithAShop(string $role, bool $approved = true, string $name = 'Old Desk Shop'): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => $approved, 'business_name' => $name, 'onboarding_completed_at' => now()]);
        $user->forceFill(['seller_applied_at' => now()])->save();

        return $this->withRole($this->withRole($user, $role), 'seller');
    }

    public function test_support_finance_and_catalogue_accounts_cannot_apply_for_a_shop(): void
    {
        foreach (['support', 'finance', 'catalogue'] as $role) {
            $member = $this->teamMember($role);
            $this->actingAs($member)->get(route('seller.apply'))->assertRedirect(route('home'))
                ->assertSessionHas('error', 'This is an iruali team account, for running the website, so it cannot open a shop. To sell on iruali, open the shop with a separate account (its own email and mobile number).');
            $this->post(route('seller.apply.store'), ['business_name' => 'Desk Shop'])->assertRedirect(route('home'));

            $member->refresh();
            $this->assertFalse($member->maySell());
            $this->assertFalse($member->hasRole('seller'));
            $this->assertFalse((bool) $member->is_seller);
            $this->assertFalse(CurrentShop::opensSellerCentre($member));
            $this->assertFalse(CurrentShop::offersSelling($member, true));
        }
        $this->assertTrue(CurrentShop::offersSelling(User::factory()->create(), true), 'customers may');
        $this->assertTrue(CurrentShop::offersSelling(null, true), 'and visitors');
    }

    public function test_an_admin_account_may_be_iruali_s_own_shop_and_switches_from_the_menu(): void
    {
        $owner = $this->teamMember('admin');
        $this->assertTrue($owner->maySell());
        $this->assertTrue(CurrentShop::offersSelling($owner, true));
        $this->actingAs($owner)->get(route('seller.apply'))->assertOk();

        // Its shop, approved: the same sign-in opens both, each with a link to the other
        $direct = $this->teamMemberWithAShop('admin', name: 'iruali Direct');
        $this->actingAs($direct)->get(route('admin.sellers'))->assertOk()
            ->assertSee('data-seller-admin-shop', false)->assertSee('iruali&#039;s own shop (admin account)', false);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('data-switch-to-seller', false)->assertSee('iruali Direct');
        $this->get(route('seller.dashboard'))->assertOk()->assertSee('data-switch-to-admin', false)->assertSee(route('admin.dashboard'), false);
        $this->get(route('seller.products.create'))->assertOk();
        $this->assertTrue(CurrentShop::opensSellerCentre($direct));

        // An admin approves an admin account's application like any shop's
        $pending = $this->teamMemberWithAShop('admin', approved: false, name: 'iruali Imports');
        $this->post(route('admin.sellers.approve', $pending))->assertSessionHas('success');
        $this->assertTrue((bool) $pending->fresh()->seller_approved);
    }

    public function test_an_admin_cannot_approve_a_support_finance_or_catalogue_account_as_a_shop(): void
    {
        $admin = $this->teamMember('admin');
        $desk = $this->teamMemberWithAShop('support', approved: false);

        $this->actingAs($admin)->get(route('admin.sellers'))->assertOk()
            ->assertSee('data-seller-team', false)->assertSee('iruali team account: cannot sell')
            ->assertDontSee('data-switch-to-seller', false);
        $this->post(route('admin.sellers.approve', $desk))->assertSessionHas('error');
        $this->assertFalse((bool) $desk->fresh()->seller_approved);
    }

    public function test_a_support_account_with_a_shop_from_before_cannot_open_the_seller_centre(): void
    {
        $desk = $this->teamMemberWithAShop('support');

        $this->actingAs($desk)->get(route('seller.dashboard'))->assertForbidden();
        $this->get(route('seller.products.create'))->assertForbidden();
        $this->post(route('seller.products.store'), ['name_en' => 'Desk lamp'])->assertForbidden();
        $this->assertFalse(CurrentShop::opensSellerCentre($desk));
    }

    public function test_a_shop_of_its_own_sells_as_before_and_is_never_made_staff(): void
    {
        $shop = $this->withRole(User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Goods', 'onboarding_completed_at' => now()]), 'seller');

        $this->actingAs($shop)->get(route('seller.dashboard'))->assertOk()->assertDontSee('data-switch-to-admin', false);
        $this->get(route('seller.products.create'))->assertOk();
        $this->assertTrue(CurrentShop::opensSellerCentre($shop));

        $this->actingAs($this->teamMember('admin'))->post(route('admin.users.role', $shop), ['role' => 'support'])->assertSessionHas('error');
        $this->assertFalse($shop->fresh()->isStaff());
    }
}
