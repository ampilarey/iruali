<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PointsTransaction;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\BirthdayOffer;
use App\Notifications\TierUpgraded;
use App\Services\DiscountService;
use App\Services\OrderService;
use App\Services\PointsService;
use App\Support\LoyaltyTiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Loyalty tiers by 12-month spend, points tools (hand adjustments, the nightly balance check) and the
 * birthday offer.
 */
class LoyaltyTiersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set(['loyalty_spend_per_point' => 100, 'loyalty_tier_lagoon_min' => 5000, 'loyalty_tier_lagoon_multiplier' => 1.25, 'loyalty_tier_atoll_min' => 15000, 'loyalty_tier_atoll_multiplier' => 1.5]);
    }

    protected function paidOrder(User $user, float $total, ?Carbon $at = null): Order
    {
        $order = Order::factory()->create(['user_id' => $user->id, 'total_amount' => $total, 'status' => 'delivered', 'payment_status' => 'paid', 'loyalty_points_earned' => 0]);
        if ($at) {
            $order->forceFill(['created_at' => $at])->save();
        }

        return $order;
    }

    public function test_tiers_follow_twelve_months_of_spending_and_earn_more_points(): void
    {
        $user = User::factory()->create();
        $this->assertSame('reef', LoyaltyTiers::current($user));
        $this->assertSame(1, app(DiscountService::class)->calculateLoyaltyPointsEarned(100, $user));

        $this->paidOrder($user, 6000);
        $this->paidOrder($user, 20000, now()->subMonths(13)); // too old to count
        $this->assertSame('lagoon', LoyaltyTiers::current($user));
        $this->assertSame(125, app(DiscountService::class)->calculateLoyaltyPointsEarned(10000, $user));
        $next = LoyaltyTiers::next($user);
        $this->assertSame('atoll', $next['key']);
        $this->assertEqualsWithDelta(9000, $next['needed'], 0.01);

        $this->actingAs($user)->get(route('account.rewards'))->assertOk()->assertSee('data-loyalty-tier="lagoon"', false)->assertSee('Your tier: Lagoon');

        Setting::set(['loyalty_tiers_enabled' => 0]);
        $this->assertSame(100, app(DiscountService::class)->calculateLoyaltyPointsEarned(10000, $user));
    }

    public function test_moving_up_is_announced_once_and_the_night_job_moves_down_quietly(): void
    {
        $user = User::factory()->create();
        $this->paidOrder($user, 4000);
        $order = $this->paidOrder($user, 2000);

        app(OrderService::class)->awardRewards($order);
        Notification::assertSentTo($user, TierUpgraded::class, fn ($n) => $n->tier === 'lagoon');
        $this->assertSame('lagoon', $user->fresh()->loyalty_tier);

        app(OrderService::class)->awardRewards($order->fresh());
        Notification::assertSentToTimes($user, TierUpgraded::class, 1);

        $this->travel(13)->months();
        $this->artisan('rewards:refresh-tiers')->expectsOutputToContain('1 customer tier(s) changed')->assertSuccessful();
        $this->assertSame('reef', $user->fresh()->loyalty_tier);
        Notification::assertSentToTimes($user, TierUpgraded::class, 1);
    }

    public function test_admins_adjust_points_and_drifted_balances_are_fixed(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $customer = User::factory()->create(['email' => 'mariyam@example.com']);

        // Top referrers: only people who referred someone (a plain WHERE, which SQLite accepts too)
        $referrer = User::factory()->create(['name' => 'Hawwa Referrer']);
        User::factory()->create()->forceFill(['referred_by' => $referrer->id])->save();
        User::factory()->create(['name' => 'Nobody Referred']);
        $this->actingAs($admin)->get(route('admin.rewards'))->assertOk()->assertSee('data-fold="points-adjust"', false)
            ->assertSee('Hawwa Referrer')->assertDontSee('Nobody Referred');
        $this->post(route('admin.rewards.adjust'), ['email' => 'mariyam@example.com', 'points' => -5, 'reason' => 'x'])->assertSessionHasErrors('points');
        $this->post(route('admin.rewards.adjust'), ['email' => 'mariyam@example.com', 'points' => 150, 'reason' => 'Sorry for the late delivery'])->assertSessionHas('success');
        $this->assertSame(150, (int) $customer->fresh()->loyalty_points);
        $this->assertSame('Sorry for the late delivery', PointsTransaction::sole()->note);

        // Finance staff see the report but can't give points
        $finance = User::factory()->create();
        $finance->roles()->attach(Role::firstOrCreate(['name' => 'finance'], ['display_name' => 'Finance'])->id);
        $this->actingAs($finance)->post(route('admin.rewards.adjust'), ['email' => 'mariyam@example.com', 'points' => 10, 'reason' => 'x'])->assertForbidden();

        // A balance that drifted from the ledger is set back to it
        $customer->forceFill(['loyalty_points' => 999])->save();
        $this->artisan('rewards:check-balances')->expectsOutputToContain('Fixed 1')->assertSuccessful();
        $this->assertSame(150, (int) $customer->fresh()->loyalty_points);
        $this->artisan('rewards:check-balances')->expectsOutputToContain('All points balances match')->assertSuccessful();
    }

    public function test_birthday_offers_go_once_a_year_to_customers_who_want_offers(): void
    {
        Setting::set(['birthday_offer_enabled' => 1, 'birthday_offer_percent' => 15, 'birthday_offer_days' => 7]);
        $this->travelTo(Carbon::parse('2026-11-05 09:30'));
        $birthday = User::factory()->create(['birth_date' => '1990-11-05']);
        $optedOut = User::factory()->create(['birth_date' => '1992-11-05']);
        $optedOut->forceFill(['marketing_opt_out_at' => now()])->save();
        $other = User::factory()->create(['birth_date' => '1990-11-06']);

        $this->artisan('marketing:birthday-offers')->expectsOutputToContain('Sent 1')->assertSuccessful();
        $voucher = Voucher::sole();
        $this->assertSame($birthday->id, $voucher->user_id);
        $this->assertSame('percent', $voucher->type);
        $this->assertSame('2026-11-12', $voucher->valid_until->toDateString());
        Notification::assertSentTo($birthday, BirthdayOffer::class);
        Notification::assertNotSentTo([$optedOut, $other], BirthdayOffer::class);

        $this->artisan('marketing:birthday-offers')->expectsOutputToContain('Sent 0')->assertSuccessful();

        // Customers give their birthday in My Account
        $this->actingAs($other)->put(route('account.update'), ['name' => $other->name, 'birth_date' => '2999-01-01'])->assertSessionHasErrors('birth_date');
        $this->put(route('account.update'), ['name' => $other->name, 'birth_date' => '1991-03-04']);
        $this->assertSame('1991-03-04', $other->fresh()->birth_date->toDateString());
    }

    public function test_points_redeemed_at_checkout_are_held_until_cancel_returns_them(): void
    {
        $user = User::factory()->create();
        app(PointsService::class)->record($user, 200, 'adjustment');
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'unpaid', 'points_redeemed' => 50]);
        app(PointsService::class)->record($user, -50, 'redeemed', $order);
        $this->assertSame(150, (int) $user->fresh()->loyalty_points);

        app(OrderService::class)->updateOrderStatus($order, 'cancelled');
        $this->assertSame(200, (int) $user->fresh()->loyalty_points);
    }
}
