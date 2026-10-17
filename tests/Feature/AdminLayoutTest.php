<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin area's own layout: a grouped menu (sidebar and phone drawer) with only the pages the
 * staff member may open, the current page marked, and the phone's top and bottom bars; none of the
 * shop's header, island bar or footer.
 */
class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    public function test_admin_pages_use_the_admin_layout_with_a_grouped_menu(): void
    {
        $response = $this->actingAs($this->staff('admin'))->get(route('admin.orders'));
        $this->assertMatchesRegularExpression('/data-nav="admin\.orders"\s+aria-current="page"/', $response->getContent());
        $response->assertOk()
            ->assertSee('data-admin-layout', false)
            ->assertSee('Shops &amp; products', false)->assertSee('Customers &amp; marketing', false)
            ->assertSee('data-nav="admin.settings"', false)->assertSee('data-nav="admin.disputes"', false)
            // The current page is marked, in the menu and the phone's top bar
            ->assertSee('data-admin-current>Orders<', false)
            // The phone's bar and drawer
            ->assertSee('data-admin-menu-open', false)->assertSee('aria-label="Admin shortcuts"', false)
            // Not the shop's layout
            ->assertDontSee('data-shopping-island', false)->assertDontSee('Back to Dashboard');

        // A page under a menu item marks it too
        $this->get(route('admin.tax.report'))->assertOk()->assertSee('data-admin-current>Tax (GST)<', false);
    }

    public function test_staff_see_only_what_their_role_opens(): void
    {
        $this->actingAs($this->staff('catalogue'))->get(route('admin.products'))->assertOk()
            ->assertSee('data-nav="admin.products"', false)->assertSee('data-nav="admin.brands"', false)
            ->assertDontSee('data-nav="admin.orders"', false)->assertDontSee('data-nav="admin.settings"', false)
            ->assertDontSee('>Money<', false);
    }
}
