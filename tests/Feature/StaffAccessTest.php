<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopStaff;
use App\Models\User;
use App\Support\AdminInbox;
use App\Support\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    public function test_is_staff_covers_admin_support_and_finance(): void
    {
        $this->assertTrue($this->staff('admin')->isStaff());
        $this->assertTrue($this->staff('support')->isStaff());
        $this->assertTrue($this->staff('finance')->isStaff());
        $this->assertFalse($this->staff('seller')->isStaff());
        $this->assertFalse(User::factory()->create()->isStaff());
    }

    public function test_route_patterns_match_exactly_or_by_prefix(): void
    {
        $this->assertTrue(StaffAccess::matches('admin.orders.*', 'admin.orders.status'));
        $this->assertTrue(StaffAccess::matches('admin.users', 'admin.users'));
        $this->assertFalse(StaffAccess::matches('admin.users', 'admin.users.role'));
        $this->assertTrue(StaffAccess::can('admin.settings', $this->staff('admin')));
        $this->assertFalse(StaffAccess::can('admin.settings', $this->staff('support')));
        $this->assertFalse(StaffAccess::can('admin.users.role', $this->staff('support')));
    }

    public function test_support_opens_orders_returns_moderation_and_users_but_not_settings_or_payouts(): void
    {
        $support = $this->staff('support');
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAs($support)->get('/admin/dashboard')->assertOk()->assertSee('data-nav="admin.orders"', false)->assertDontSee('data-nav="admin.settings"', false)->assertDontSee('System status');
        $this->actingAs($support)->get('/admin/orders')->assertOk();
        $this->actingAs($support)->get('/admin/orders/'.$order->id)->assertOk();
        $this->actingAs($support)->get('/admin/returns')->assertOk();
        $this->actingAs($support)->get('/admin/reviews')->assertOk();
        $this->actingAs($support)->get('/admin/users')->assertOk()->assertDontSee('Not staff');

        $this->actingAs($support)->get('/admin/settings')->assertForbidden();
        $this->actingAs($support)->get('/admin/payouts')->assertForbidden();
        $this->actingAs($support)->get('/admin/vouchers')->assertForbidden();
        $this->actingAs($support)->get('/admin/audit')->assertForbidden();
        $this->actingAs($support)->post('/admin/users/'.$order->user_id.'/role', ['role' => 'finance'])->assertForbidden();
    }

    public function test_finance_opens_payouts_analytics_errors_and_audit_but_cannot_resolve_errors(): void
    {
        $finance = $this->staff('finance');
        $event = \App\Models\ErrorEvent::create(['fingerprint' => sha1('x'), 'exception_class' => 'RuntimeException', 'message' => 'm', 'first_seen_at' => now(), 'last_seen_at' => now()]);

        $this->actingAs($finance)->get('/admin/dashboard')->assertOk()->assertSee('data-nav="admin.payouts"', false)->assertDontSee('data-nav="admin.users"', false);
        $this->actingAs($finance)->get('/admin/payouts')->assertOk();
        $this->actingAs($finance)->get('/admin/analytics')->assertOk();
        $this->actingAs($finance)->get('/admin/errors')->assertOk();
        $this->actingAs($finance)->get('/admin/errors/'.$event->id)->assertOk()->assertDontSee('Mark resolved');
        $this->actingAs($finance)->get('/admin/audit')->assertOk();

        $this->actingAs($finance)->post('/admin/errors/'.$event->id.'/resolve')->assertForbidden();
        $this->actingAs($finance)->get('/admin/users')->assertForbidden();
        $this->actingAs($finance)->get('/admin/settings')->assertForbidden();
    }

    public function test_catalogue_staff_look_after_brands_product_approvals_and_campaigns_only(): void
    {
        $catalogue = $this->staff('catalogue');
        $this->assertTrue($catalogue->isStaff());
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'onboarding_completed_at' => now()]);
        $product = Product::factory()->create(['seller_id' => $shop->id, 'brand' => 'Reefline', 'is_active' => false]);
        $brand = $product->brandModel;
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAs($catalogue)->get('/admin/dashboard')->assertOk()
            ->assertSee('data-nav="admin.brands"', false)->assertSee('data-nav="admin.products"', false)->assertSee('data-nav="admin.campaigns.index"', false)
            ->assertDontSee('data-nav="admin.orders"', false)->assertDontSee('data-nav="admin.users"', false)->assertDontSee('data-nav="admin.settings"', false);

        // Their inbox lists what they can act on: new products and new brands, not refunds or shops
        $rows = collect(AdminInbox::items($catalogue))->pluck('label');
        $this->assertContains('Products pending review', $rows);
        $this->assertContains('Brands to review', $rows);
        $this->assertNotContains('Refunds due', $rows);
        $this->assertNotContains('Shops awaiting approval', $rows);

        // Brand pages, product approvals and campaigns
        $this->actingAs($catalogue)->get('/admin/brands')->assertOk();
        $this->actingAs($catalogue)->get(route('admin.brands.edit', $brand))->assertOk();
        $this->actingAs($catalogue)->put(route('admin.brands.update', $brand), [
            'name' => 'Reefline', 'slug' => 'reefline', 'description_en' => 'Marine gear for island life.', 'description_dv' => '', 'reviewed' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.brands.edit', $brand));
        $this->assertSame('Marine gear for island life.', $brand->fresh()->getTranslation('description', 'en'));
        $this->actingAs($catalogue)->get('/admin/products')->assertOk();
        $this->actingAs($catalogue)->post(route('admin.products.approve', $product->id))->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($product->fresh()->is_active);
        $this->actingAs($catalogue)->get(route('admin.campaigns.index'))->assertOk();
        $this->actingAs($catalogue)->get(route('admin.campaigns.create'))->assertOk();

        // Nothing about orders, money, people or settings
        foreach (['/admin/orders', '/admin/orders/'.$order->id, '/admin/returns', '/admin/payouts', '/admin/tax', '/admin/analytics', '/admin/users', '/admin/sellers', '/admin/settings', '/admin/audit', '/admin/sample-data'] as $page) {
            $this->actingAs($catalogue)->get($page)->assertForbidden();
        }
        $this->actingAs($catalogue)->post('/admin/users/'.$order->user_id.'/role', ['role' => 'catalogue'])->assertForbidden();
    }

    public function test_admins_make_a_user_catalogue_staff_but_never_someone_from_a_shop(): void
    {
        $admin = $this->staff('admin');
        $user = User::factory()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('<option value="catalogue"', false);
        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/role', ['role' => 'catalogue'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(['catalogue'], StaffAccess::staffRoles($user->fresh()));
        $this->assertSame(['from' => [], 'to' => ['catalogue']], AuditLog::where('action', 'user.role')->sole()->changes);

        // A shop's owner, or someone on its staff, would be approving their own shop's products
        $owner = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $owner->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $member = User::factory()->create();
        (new ShopStaff(['role' => 'manager']))->forceFill(['shop_id' => $owner->id, 'user_id' => $member->id, 'invited_by' => $owner->id])->save();
        foreach ([$owner, $member] as $shopPerson) {
            foreach (['catalogue', 'support', 'finance'] as $role) {
                $this->actingAs($admin)->post('/admin/users/'.$shopPerson->id.'/role', ['role' => $role])
                    ->assertSessionHas('error', $shopPerson->name.' has a shop or works for one, so cannot be iruali staff. Use a separate account for staff work.');
            }
            $this->assertFalse($shopPerson->fresh()->isStaff());
        }

        // Taking a role away always works
        $member->roles()->attach(Role::firstOrCreate(['name' => 'support'], ['display_name' => 'Support'])->id);
        $this->actingAs($admin)->post('/admin/users/'.$member->id.'/role', ['role' => 'none'])->assertSessionHas('success');
        $this->assertFalse($member->fresh()->isStaff());
    }

    public function test_support_and_finance_open_every_page_their_role_lists_but_never_refund_to_the_wallet(): void
    {
        $support = $this->staff('support');
        $finance = $this->staff('finance');
        $order = Order::factory()->create(['status' => 'pending']);

        // Disputes, order messages, SMS, rewards and gift cards used to be admin-only despite config/staff.php
        foreach (['/admin/disputes', '/admin/messages', '/admin/sms'] as $page) {
            $this->actingAs($support)->get($page)->assertOk();
        }
        foreach (['/admin/disputes', '/admin/rewards', '/admin/gift-cards'] as $page) {
            $this->actingAs($finance)->get($page)->assertOk();
        }
        $this->actingAs($support)->get('/admin/rewards')->assertForbidden();
        $this->actingAs($finance)->get('/admin/messages')->assertForbidden();
        $this->actingAs($support)->get(route('admin.campaigns.index'))->assertForbidden();
        $this->actingAs($this->staff('admin'))->get(route('admin.campaigns.index'))->assertOk();

        // Store credit is money: only admins refund to the wallet, and staff are not offered the button
        foreach ([$support, $finance] as $staff) {
            $this->actingAs($staff)->post(route('admin.orders.refund-wallet', $order))->assertForbidden();
        }
        $this->actingAs($support)->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Refund to wallet');

        // Confirming a payment and recording a card refund are not support work. Finance records the refund.
        $payable = Order::factory()->create([
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'refund_status' => 'due',
            'refund_amount' => 10,
            'refund_reason' => 'Cancelled',
        ]);
        $this->actingAs($support)->post(route('admin.orders.payment', $payable), ['action' => 'confirm'])->assertForbidden();
        $this->actingAs($support)->post(route('admin.orders.refunded', $payable), ['refund_reference' => 'R1'])->assertForbidden();
        $this->actingAs($finance)->post(route('admin.orders.payment', $payable), ['action' => 'confirm'])->assertForbidden();
        $this->actingAs($support)->get(route('admin.orders.show', $payable))->assertOk()
            ->assertDontSee('Confirm payment')
            ->assertDontSee('Mark as refunded');
        $this->actingAs($finance)->get(route('admin.orders.show', $payable))->assertOk()
            ->assertDontSee('Confirm payment')
            ->assertSee('Mark as refunded');
    }

    public function test_admin_sees_everything_and_customers_nothing(): void
    {
        $this->actingAs($this->staff('admin'))->get('/admin/settings')->assertOk();
        $this->actingAs($this->staff('admin'))->get('/admin/audit')->assertOk();
        $this->actingAs(User::factory()->create())->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->staff('seller'))->get('/admin/dashboard')->assertForbidden();
    }

    public function test_staff_without_two_factor_are_sent_to_set_it_up(): void
    {
        config(['staff.require_two_factor' => true]);
        $support = $this->staff('support');

        $this->actingAs($support)->get('/admin/dashboard')
            ->assertRedirect(route('profile.2fa.setup'))
            ->assertSessionHas('warning');
        $this->actingAs($support)->get('/profile/2fa/setup')->assertOk();
        $this->actingAs($support)->post('/logout')->assertRedirect();

        $support->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('secret')])->save();
        $this->actingAs($support->fresh())->get('/admin/dashboard')->assertOk();

        // Customers are never asked
        $this->actingAs(User::factory()->create())->get('/account')->assertOk();
    }

    public function test_admins_can_make_two_factor_optional_for_staff(): void
    {
        config(['staff.require_two_factor' => true]);
        $admin = $this->staff('admin');
        $admin->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('secret')])->save();
        $support = $this->staff('support');
        $this->actingAs($support)->get('/admin/dashboard')->assertRedirect(route('profile.2fa.setup'));

        $fields = ['loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50];
        $this->actingAs($admin->fresh())->get('/admin/settings')->assertOk()->assertSee('data-staff-2fa-setting', false);
        $this->put('/admin/settings', $fields + ['staff_require_two_factor' => 0])->assertRedirect('/admin/settings');
        $this->assertTrue(\App\Models\AuditLog::where('action', 'settings.staff_two_factor')->exists());

        $this->actingAs($support)->get('/admin/dashboard')->assertOk();

        // Back on: required again
        $this->actingAs($admin->fresh())->put('/admin/settings', $fields + ['staff_require_two_factor' => 1]);
        $this->actingAs($support)->get('/admin/dashboard')->assertRedirect(route('profile.2fa.setup'));
    }

    public function test_staff_cannot_turn_their_code_off_while_it_is_required_and_the_server_command_switches_it(): void
    {
        config(['staff.require_two_factor' => true]);
        $admin = User::factory()->create(['password' => bcrypt('secret-pass-123')]);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $admin->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('secret')])->save();

        // Turning it off now would lock them out of /admin, where the switch is
        $this->actingAs($admin)->post(route('profile.2fa.disable'), ['current_password' => 'secret-pass-123'])->assertSessionHasErrors('current_password');
        $this->assertTrue($admin->fresh()->isTwoFactorEnabled());

        // An admin locked out already switches the rule from the server
        $this->artisan('staff:two-factor off')->expectsOutputToContain('optional for staff')->assertSuccessful();
        $this->assertFalse(\App\Http\Middleware\RequireTwoFactor::required());
        $this->assertTrue(\App\Models\AuditLog::where('action', 'settings.staff_two_factor')->exists());

        $this->actingAs($admin->fresh())->post(route('profile.2fa.disable'), ['current_password' => 'secret-pass-123'])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->isTwoFactorEnabled());
        $this->actingAs($admin->fresh())->get('/admin/dashboard')->assertOk();

        $this->artisan('staff:two-factor on')->expectsOutputToContain('must use')->assertSuccessful();
        $this->artisan('staff:two-factor maybe')->assertFailed();
    }

    public function test_admin_can_make_a_user_support_or_finance_and_it_is_audited(): void
    {
        $admin = $this->staff('admin');
        $user = User::factory()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Not staff');

        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/role', ['role' => 'support'])->assertRedirect();
        $this->assertTrue($user->fresh()->hasRole('support'));
        $this->assertSame(['support'], StaffAccess::staffRoles($user->fresh()));

        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/role', ['role' => 'finance'])->assertRedirect();
        $this->assertSame(['finance'], StaffAccess::staffRoles($user->fresh()));

        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/role', ['role' => 'none'])->assertRedirect();
        $this->assertFalse($user->fresh()->isStaff());

        $this->actingAs($admin)->post('/admin/users/'.$admin->id.'/role', ['role' => 'support'])->assertSessionHas('error');
        $this->actingAs($admin)->post('/admin/users/'.$user->id.'/role', ['role' => 'admin'])->assertSessionHasErrors('role');

        $logs = AuditLog::where('action', 'user.role')->orderBy('id')->get();
        $this->assertCount(3, $logs);
        $this->assertSame(['from' => [], 'to' => ['support']], $logs[0]->changes);
        $this->assertSame($admin->id, $logs[0]->user_id);
        $this->assertSame(User::class, $logs[0]->subject_type);
    }

    public function test_staff_logins_are_audited_and_land_on_the_dashboard(): void
    {
        $finance = $this->staff('finance');
        $finance->forceFill(['password' => bcrypt('secret-pass'), 'two_factor_enabled' => true, 'two_factor_secret' => encrypt('s')])->save();

        $this->post('/login', ['email' => $finance->email, 'password' => 'secret-pass'])->assertRedirect(route('2fa.show'));
        $this->assertSame(0, AuditLog::where('action', 'staff.login')->count());

        $customer = User::factory()->create(['password' => bcrypt('secret-pass')]);
        $this->post('/login', ['email' => $customer->email, 'password' => 'secret-pass'])->assertRedirect(route('home'));
        $this->assertSame(0, AuditLog::where('action', 'staff.login')->count());

        auth()->logout();
        $support = $this->staff('support');
        $support->forceFill(['password' => bcrypt('secret-pass')])->save();
        $this->post('/login', ['email' => $support->email, 'password' => 'secret-pass'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame(1, AuditLog::where('action', 'staff.login')->where('user_id', $support->id)->count());
    }
}
