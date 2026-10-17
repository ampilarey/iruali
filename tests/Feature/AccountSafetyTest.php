<?php

namespace Tests\Feature;

use App\Mail\StaffAccountLocked;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\StaffLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sign out everywhere, staff lockout after wrong tries (and the owner told), admins resetting a
 * staff member's two-step sign-in, and the staff commands.
 */
class AccountSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    public function test_sign_out_everywhere_ends_other_sessions_and_tokens(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $user->createToken('phone');
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user)->get(route('account'))->assertSee('data-sign-out-everywhere', false);
        $this->post(route('account.sign-out-everywhere'))->assertRedirect(route('account').'#security');

        $this->assertFalse(DB::table('sessions')->where('id', 'other-device')->exists());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->assertAuthenticated();
    }

    public function test_staff_accounts_lock_after_wrong_passwords_and_the_owner_is_told(): void
    {
        Mail::fake();
        config(['mail.alerts_to' => 'owner@iruali.mv']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // the per-IP limit; the lockout is per account
        $staff = $this->staff('support', ['email' => 'aisha@iruali.mv', 'password' => bcrypt('Right#Pass1')]);

        for ($i = 0; $i < StaffLockout::ATTEMPTS; $i++) {
            $this->post(route('login'), ['email' => 'aisha@iruali.mv', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        Mail::assertQueued(StaffAccountLocked::class, fn ($mail) => $mail->hasTo('owner@iruali.mv'));
        $this->assertTrue(AuditLog::where('action', 'staff.locked')->exists());

        // Even the right password is refused while locked
        $this->post(route('login'), ['email' => 'aisha@iruali.mv', 'password' => 'Right#Pass1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // An admin unlocks it
        $this->actingAs($this->staff('admin'))->get(route('admin.users'))->assertSee(route('admin.users.unlock', $staff));
        $this->post(route('admin.users.unlock', $staff))->assertSessionHas('success');
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'aisha@iruali.mv', 'password' => 'Right#Pass1']);
        $this->assertAuthenticatedAs($staff);
    }

    public function test_customers_are_not_locked_this_way(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['email' => 'shopper@example.com']);
        for ($i = 0; $i < StaffLockout::ATTEMPTS; $i++) {
            $this->post(route('login'), ['email' => 'shopper@example.com', 'password' => 'wrong']);
        }
        $this->assertNull(StaffLockout::lockedUntil($customer));
        Mail::assertNothingQueued();
    }

    public function test_admins_reset_another_staff_members_two_step_sign_in(): void
    {
        $staff = $this->staff('finance');
        $staff->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('SECRETKEY')])->save();
        $this->assertTrue($staff->fresh()->isTwoFactorEnabled());

        $this->actingAs($this->staff('support'))->delete(route('admin.users.two-factor', $staff))->assertForbidden();

        $admin = $this->staff('admin');
        $this->actingAs($admin)->delete(route('admin.users.two-factor', $staff))->assertSessionHas('success');
        $this->assertFalse($staff->fresh()->isTwoFactorEnabled());
        $this->assertTrue(AuditLog::where('action', 'staff.two_factor_reset')->exists());

        // Not for customers, nor your own account
        $customer = User::factory()->create();
        $this->delete(route('admin.users.two-factor', $customer))->assertSessionHas('error');
        $this->delete(route('admin.users.two-factor', $admin))->assertSessionHas('error');
    }

    public function test_staff_commands_create_list_and_unlock(): void
    {
        $this->artisan('staff:create', ['email' => 'new@iruali.mv', '--role' => 'support', '--name' => 'New Person', '--no-interaction' => true])
            ->expectsOutputToContain('is now support staff')->assertSuccessful();
        $user = User::where('email', 'new@iruali.mv')->sole();
        $this->assertTrue($user->isStaff());

        $this->artisan('staff:create', ['email' => 'x@iruali.mv', '--role' => 'owner', '--no-interaction' => true])->assertFailed();
        $this->artisan('staff:list')->expectsOutputToContain('new@iruali.mv')->assertSuccessful();

        for ($i = 0; $i < StaffLockout::ATTEMPTS; $i++) {
            StaffLockout::failed($user, '1.2.3.4');
        }
        $this->assertNotNull(StaffLockout::lockedUntil($user));
        $this->artisan('staff:unlock', ['email' => 'new@iruali.mv'])->assertSuccessful();
        $this->assertNull(StaffLockout::lockedUntil($user));
    }
}
