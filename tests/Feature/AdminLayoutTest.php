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

    public function test_page_sections_fold_to_their_headings_and_open_on_errors(): void
    {
        $admin = $this->staff('admin');

        $page = $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
        $page->assertSee('data-fold="payment-methods"', false);
        $this->assertDoesNotMatchRegularExpression('/<details[^>]*data-fold="payment-methods"[^>]*\bopen\b/', $page->getContent());

        // A form sent back with errors shows every section, so the error is in view
        $page = $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['contact_email' => 'Bad email']))])
            ->get(route('admin.settings'))->assertOk();
        $this->assertMatchesRegularExpression('/<details[^>]*data-fold="payment-methods"[^>]*\bopen\b/', $page->getContent());
    }

    public function test_the_menu_links_to_the_staff_member_s_own_two_step_sign_in(): void
    {
        $this->actingAs($this->staff('admin'))->get(route('admin.dashboard'))->assertOk()
            ->assertSee('data-admin-2fa-link', false)->assertSee(route('profile.2fa.setup'), false)
            ->assertSee('data-admin-account-link', false)->assertSee('My account');
        // ...which opens for staff, with the password change
        $this->get(route('account'))->assertOk()->assertSee('data-password-form', false);
    }
}
