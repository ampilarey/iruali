<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
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
