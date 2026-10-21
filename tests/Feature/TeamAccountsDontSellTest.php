<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\CurrentShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * iruali's own team (admin, support, finance, catalogue) runs the website and never sells: a team
 * account can't apply for a shop, be approved as one or open the Seller Centre, and iruali sells
 * through a shop account of its own. (The other way round, a shop account is never made staff:
 * StaffRoleController, staff:create.)
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

    /** An account that became a shop before team accounts were kept from selling. */
    protected function teamMemberWithAShop(string $role, bool $approved = true): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => $approved, 'business_name' => 'Old Desk Shop', 'onboarding_completed_at' => now()]);
        $user->forceFill(['seller_applied_at' => now()])->save();

        return $this->withRole($this->withRole($user, $role), 'seller');
    }

    public function test_a_team_account_cannot_apply_for_a_shop(): void
    {
        foreach (['admin', 'support', 'finance', 'catalogue'] as $role) {
            $member = $this->teamMember($role);
            $this->actingAs($member)->get(route('seller.apply'))->assertRedirect(route('home'))
                ->assertSessionHas('error', 'This is an iruali team account, for running the website, so it cannot open a shop. To sell on iruali, open the shop with a separate account (its own email and mobile number).');
            $this->post(route('seller.apply.store'), ['business_name' => 'Desk Shop'])->assertRedirect(route('home'));

            $member->refresh();
            $this->assertFalse($member->hasRole('seller'));
            $this->assertFalse((bool) $member->is_seller);
            $this->assertFalse(CurrentShop::opensSellerCentre($member));
            $this->assertFalse(CurrentShop::offersSelling($member, true));
        }
        $this->assertTrue(CurrentShop::offersSelling(User::factory()->create(), true), 'everyone else may');
    }

    public function test_an_admin_cannot_approve_a_team_account_as_a_shop(): void
    {
        $admin = $this->teamMember('admin');
        $desk = $this->teamMemberWithAShop('support', approved: false);

        $this->actingAs($admin)->get(route('admin.sellers'))->assertOk()
            ->assertSee('data-seller-team', false)->assertSee('iruali team account: cannot sell');
        $this->post(route('admin.sellers.approve', $desk))->assertSessionHas('error');
        $this->assertFalse((bool) $desk->fresh()->seller_approved);
    }

    public function test_a_team_account_with_a_shop_from_before_cannot_open_the_seller_centre(): void
    {
        $desk = $this->teamMemberWithAShop('admin');

        $this->actingAs($desk)->get(route('seller.dashboard'))->assertForbidden();
        $this->get(route('seller.products.create'))->assertForbidden();
        $this->post(route('seller.products.store'), ['name_en' => 'Desk lamp'])->assertForbidden();
        $this->assertFalse(CurrentShop::opensSellerCentre($desk));
    }

    public function test_a_shop_of_its_own_sells_as_before_and_is_never_made_staff(): void
    {
        $direct = $this->withRole(User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'iruali Direct', 'onboarding_completed_at' => now()]), 'seller');

        $this->actingAs($direct)->get(route('seller.dashboard'))->assertOk();
        $this->get(route('seller.products.create'))->assertOk();
        $this->assertTrue(CurrentShop::opensSellerCentre($direct));

        $this->actingAs($this->teamMember('admin'))->post(route('admin.users.role', $direct), ['role' => 'support'])->assertSessionHas('error');
        $this->assertFalse($direct->fresh()->isStaff());
    }
}
