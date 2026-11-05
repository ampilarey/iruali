<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OTP;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fixes from the November 2026 security review: codes that can't be guessed through many doors, accounts set up
 * with someone else's email, sessions after a password change, staff on the app sign-in, refund duties, and
 * cancels that run twice.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function withRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    public function test_a_code_is_spent_after_five_wrong_tries_and_compared_safely(): void
    {
        $otp = OTP::createForPhone('+9607771234', 'login');
        $wrong = $otp->code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < OTP::MAX_ATTEMPTS; $i++) {
            $this->assertNull(OTP::verify('+9607771234', $wrong, 'login'));
        }
        // Even the right code no longer works: it was spent by the wrong tries
        $this->assertNull(OTP::verify('+9607771234', $otp->code, 'login'));
        $this->assertTrue($otp->fresh()->is_used);

        $fresh = OTP::createForPhone('+9607771234', 'login');
        $this->assertNotNull(OTP::verify('+9607771234', $fresh->code, 'login'));
        $this->assertNull(OTP::verify('+9607771234', $fresh->code, 'login'), 'a right code works once');
    }

    public function test_proving_the_email_of_a_premade_account_throws_away_its_password_and_sessions(): void
    {
        config(['session.driver' => 'database']);
        // Someone opened an account with the victim's email and a password of their own, never confirmed
        $account = User::factory()->create(['email' => 'victim@example.com', 'email_verified_at' => null, 'password' => Hash::make('Attacker-pass-1!'), 'remember_token' => 'attacker-cookie']);
        $account->createToken('attacker');
        DB::table('sessions')->insert(['id' => 'attacker-session', 'user_id' => $account->id, 'payload' => '', 'last_activity' => time()]);
        $guestOrder = Order::factory()->create(['user_id' => null, 'guest_email' => 'victim@example.com']);

        // The real owner proves the email (a code that reached the inbox, or Google vouching for it)
        $account->claimByEmailProof();
        $account->refresh();

        $this->assertNotNull($account->email_verified_at);
        $this->assertFalse(Hash::check('Attacker-pass-1!', $account->password));
        $this->assertFalse($account->hasPassword());
        $this->assertNotSame('attacker-cookie', $account->remember_token);
        $this->assertSame(0, $account->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $account->id)->count());
        $this->assertSame($account->id, $guestOrder->fresh()->user_id);

        // An account whose email was already confirmed keeps its password
        $owner = User::factory()->create(['password' => Hash::make('Owner-pass-1!')]);
        $owner->claimByEmailProof();
        $this->assertTrue(Hash::check('Owner-pass-1!', $owner->fresh()->password));
    }

    public function test_changing_the_password_signs_out_every_other_device(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => Hash::make('Old-pass-123!'), 'remember_token' => 'old-cookie']);
        $user->createToken('phone');
        DB::table('sessions')->insert(['id' => 'stolen-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'Old-pass-123!', 'password' => 'New-pass-456!', 'password_confirmation' => 'New-pass-456!',
        ])->assertRedirect(route('account'));

        $this->assertSame(0, DB::table('sessions')->where('id', 'stolen-session')->count());
        $this->assertNotSame('old-cookie', $user->fresh()->remember_token);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_staff_get_no_app_token_and_the_same_answer_as_a_wrong_password(): void
    {
        $admin = $this->withRole('admin', ['password' => Hash::make('Right-pass-123!')]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->postJson('/api/v1/login', ['email' => $admin->email, 'password' => 'Right-pass-123!'])->assertStatus(401);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_support_decides_returns_but_refunds_are_finance(): void
    {
        $support = $this->withRole('support');
        $this->assertTrue(\App\Support\StaffAccess::can('admin.returns.approve', $support));
        $this->assertFalse(\App\Support\StaffAccess::can('admin.returns.refunded', $support));
        $this->assertFalse(\App\Support\StaffAccess::can('admin.returns.refund-approve', $support));
        $this->assertFalse(\App\Support\StaffAccess::can('admin.returns.refund-wallet', $support));

        $finance = $this->withRole('finance');
        $this->assertTrue(\App\Support\StaffAccess::can('admin.returns.refunded', $finance));
        $this->assertTrue(\App\Support\StaffAccess::can('admin.returns.refund-wallet', $finance));
    }

    public function test_payout_pages_need_two_step_sign_in_like_the_rest_of_admin(): void
    {
        config(['staff.require_two_factor' => true]);
        $admin = $this->withRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.payout-batches.index'));
        $this->assertTrue($response->isRedirect(), 'an admin without two-step sign-in is sent to set it up');
    }

    public function test_a_paid_gift_card_order_cannot_be_cancelled_for_a_refund(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'pending', 'payment_status' => 'paid', 'payment_method' => 'bml']);
        \App\Models\GiftCard::create(['code' => 'GIFT-TEST-1234', 'amount' => 500, 'balance' => 0, 'purchaser_id' => $customer->id, 'order_id' => $order->id,
            'recipient_email' => $customer->email, 'status' => 'redeemed']);
        $this->assertFalse($customer->can('cancel', $order->fresh()));
        $this->actingAs($customer)->post(route('orders.cancel', $order))->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);

        // An unpaid gift-card order can still be cancelled
        $order->update(['payment_status' => 'unpaid']);
        $this->assertTrue($customer->can('cancel', $order->fresh()));
    }

    public function test_cancelling_twice_reverses_once(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'pending', 'payment_status' => 'unpaid']);
        $stale = Order::find($order->id); // a second request that loaded the order before the first cancel

        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order, 'cancelled'));
        $this->assertFalse(app(OrderService::class)->updateOrderStatus($stale, 'cancelled'), 'the second cancel finds it already cancelled');
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'order.cancelled')->count());
    }
}
