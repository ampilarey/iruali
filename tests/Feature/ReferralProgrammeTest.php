<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PointsTransaction;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PointsExpiring;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PointsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Referral links and cookies, the points ledger staying equal to the balance, FIFO expiry,
 * and the customer and admin rewards pages.
 */
class ReferralProgrammeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set(['referral_referrer_points' => 100, 'referral_referee_points' => 50, 'loyalty_spend_per_point' => 100]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function placeOrder(User $user, float $price = 1000, int $redeem = 0): Order
    {
        $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $price]);
        if ($redeem > 0) {
            session(['points_redeemed' => $redeem]);
        }

        $result = app(OrderService::class)->createOrderFromCart($user, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Male', 'shipping_state' => 'Kaafu',
            'shipping_zip' => '20026', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order'];
    }

    protected function assertLedgerMatches(User $user, string $when = ''): void
    {
        $user = $user->fresh();
        $this->assertSame((int) $user->loyalty_points, (int) PointsTransaction::where('user_id', $user->id)->sum('points'), "ledger != balance {$when}");
    }

    // ---- Cookie flow ------------------------------------------------------------------------

    public function test_a_shared_link_sets_a_cookie_and_sign_up_uses_it_when_no_code_is_typed(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'FRIEND01']);

        $response = $this->get('/r/friend01')->assertRedirect(route('home'));
        $response->assertCookie('referral_code', 'FRIEND01');
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'referral_code');
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $cookie->getExpiresTime(), 120);

        // Unknown codes set nothing
        $this->get('/r/NOPE1234')->assertRedirect(route('home'))->assertCookieMissing('referral_code');

        // The register form is pre-filled from the cookie
        $this->withCookie('referral_code', 'FRIEND01')->get('/register')->assertOk()->assertSee('value="FRIEND01"', false);

        $this->withCookie('referral_code', 'FRIEND01')->post('/register', [
            'name' => 'Referee', 'email' => 'referee@example.com', 'password' => 'Str0ng!Pass#2024', 'password_confirmation' => 'Str0ng!Pass#2024', 'agree_terms' => '1',
        ])->assertRedirect();
        $this->assertSame($referrer->id, User::where('email', 'referee@example.com')->firstOrFail()->referred_by);
    }

    public function test_a_typed_code_wins_over_the_cookie_and_a_stale_cookie_is_ignored(): void
    {
        $byLink = User::factory()->create(['referral_code' => 'BYLINK01']);
        $typed = User::factory()->create(['referral_code' => 'TYPED001']);

        auth()->logout();
        $this->withCookie('referral_code', 'BYLINK01')->post('/register', [
            'name' => 'Typed', 'email' => 'typed@example.com', 'password' => 'Str0ng!Pass#2024', 'password_confirmation' => 'Str0ng!Pass#2024', 'agree_terms' => '1', 'referral_code' => 'TYPED001',
        ])->assertRedirect();
        $this->assertSame($typed->id, User::where('email', 'typed@example.com')->firstOrFail()->referred_by);

        auth()->logout();
        $this->flushSession();
        $this->withCookie('referral_code', 'GONE0000')->post('/register', [
            'name' => 'Stale', 'email' => 'stale@example.com', 'password' => 'Str0ng!Pass#2024', 'password_confirmation' => 'Str0ng!Pass#2024', 'agree_terms' => '1',
        ])->assertRedirect();
        $this->assertNull(User::where('email', 'stale@example.com')->firstOrFail()->referred_by);
    }

    // ---- Ledger consistency ---------------------------------------------------------------

    public function test_every_points_change_is_written_to_the_ledger_and_the_balance_always_matches(): void
    {
        $referrer = User::factory()->create(['loyalty_points' => 0, 'referral_code' => 'REF12345']);
        $user = User::factory()->create(['loyalty_points' => 0, 'referred_by' => $referrer->id]);
        app(PointsService::class)->record($user, 300, 'adjustment', null, 'Opening balance');
        $this->assertLedgerMatches($user, 'after adjustment');

        // Redeem 200 at checkout
        $order = $this->placeOrder($user, 1000, 200);
        $this->assertSame(100, $user->fresh()->loyalty_points);
        $this->assertDatabaseHas('points_transactions', ['user_id' => $user->id, 'type' => 'redeemed', 'points' => -200, 'order_id' => $order->id]);
        $this->assertLedgerMatches($user, 'after redeem');

        // Paid: earned points + referral rewards on both sides
        app(PaymentService::class)->confirm($order);
        $this->assertDatabaseHas('points_transactions', ['user_id' => $user->id, 'type' => 'earned', 'points' => $order->loyalty_points_earned, 'order_id' => $order->id]);
        $this->assertDatabaseHas('points_transactions', ['user_id' => $user->id, 'type' => 'referral', 'points' => 50]);
        $this->assertDatabaseHas('points_transactions', ['user_id' => $referrer->id, 'type' => 'referral', 'points' => 100, 'order_id' => $order->id]);
        $this->assertSame(100, $referrer->fresh()->loyalty_points);
        $this->assertLedgerMatches($user, 'after payment');
        $this->assertLedgerMatches($referrer, 'after payment');

        // Confirming twice adds nothing
        app(PaymentService::class)->confirm($order->fresh());
        $this->assertLedgerMatches($user, 'after second confirm');
        $this->assertSame(1, PointsTransaction::where('user_id', $user->id)->where('type', 'earned')->count());

        // Cancelled: redeemed points come back, earned points go
        app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled');
        $this->assertDatabaseHas('points_transactions', ['user_id' => $user->id, 'type' => 'refund', 'points' => 200, 'order_id' => $order->id]);
        $this->assertDatabaseHas('points_transactions', ['user_id' => $user->id, 'type' => 'refund', 'points' => -$order->loyalty_points_earned]);
        $this->assertSame(300 + 50, $user->fresh()->loyalty_points);
        $this->assertLedgerMatches($user, 'after cancel');
    }

    public function test_the_migration_backfills_an_opening_balance_row_per_user(): void
    {
        // RefreshDatabase already ran the migration on an empty table; run the backfill logic again on a fresh user
        $user = User::factory()->create(['loyalty_points' => 0]);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['loyalty_points' => 70]);
        PointsTransaction::where('user_id', $user->id)->delete();

        $migration = require database_path('migrations/2026_10_02_030000_marketing_points_ledger.php');
        \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('points_expiry_reminded_at'));
        \Illuminate\Support\Facades\Schema::dropIfExists('points_transactions');
        $migration->up();

        $this->assertDatabaseHas('points_transactions', ['user_id' => $user->id, 'points' => 70, 'type' => 'adjustment', 'note' => 'Opening balance']);
        $this->assertLedgerMatches($user, 'after backfill');
    }

    // ---- Expiry ------------------------------------------------------------------------------

    public function test_points_expire_oldest_first_and_customers_are_warned_a_month_ahead(): void
    {
        Setting::set(['points_expire_months' => 12]);
        $points = app(PointsService::class);
        $user = User::factory()->create(['loyalty_points' => 0]);

        Carbon::setTestNow('2025-01-10 10:00:00');
        $points->record($user, 100, 'earned', null, 'January');
        Carbon::setTestNow('2025-03-10 10:00:00');
        $points->record($user, 50, 'earned', null, 'March');
        Carbon::setTestNow('2025-06-10 10:00:00');
        $points->record($user, -30, 'redeemed'); // eats 30 of January's lot
        $this->assertSame(120, $user->fresh()->loyalty_points);
        $this->assertSame([70, 50], array_column($points->unspentLots($user), 'points'));

        // A month before January's remaining 70 turn 12 months old: warn once
        Carbon::setTestNow('2025-12-15 04:00:00');
        $this->artisan('rewards:expire-points')->assertSuccessful();
        Notification::assertSentTo($user, PointsExpiring::class, fn ($n) => $n->points === 70);
        $this->assertSame(120, $user->fresh()->loyalty_points, 'nothing expires yet');
        $this->artisan('rewards:expire-points');
        Notification::assertSentToTimes($user, PointsExpiring::class, 1);

        // 12 months after January: the 70 expire, March's 50 stay
        Carbon::setTestNow('2026-01-15 04:00:00');
        $this->artisan('rewards:expire-points');
        $this->assertSame(50, $user->fresh()->loyalty_points);
        $this->assertDatabaseHas('points_transactions', ['user_id' => $user->id, 'type' => 'expired', 'points' => -70]);
        $this->assertLedgerMatches($user, 'after expiry');

        // Running again the same month expires nothing more
        $this->artisan('rewards:expire-points');
        $this->assertSame(50, $user->fresh()->loyalty_points);

        // March's lot goes two months later
        Carbon::setTestNow('2026-03-15 04:00:00');
        $this->artisan('rewards:expire-points');
        $this->assertSame(0, $user->fresh()->loyalty_points);
        $this->assertLedgerMatches($user, 'after second expiry');
    }

    public function test_points_never_expire_when_the_setting_is_zero(): void
    {
        Setting::set(['points_expire_months' => 0]);
        $user = User::factory()->create(['loyalty_points' => 0]);
        Carbon::setTestNow('2020-01-01');
        app(PointsService::class)->record($user, 100, 'earned');
        Carbon::setTestNow('2026-01-01');

        $this->artisan('rewards:expire-points')->expectsOutputToContain('never expire');
        $this->assertSame(100, $user->fresh()->loyalty_points);
        Notification::assertNothingSent();
    }

    // ---- Pages ----------------------------------------------------------------------------------

    public function test_rewards_page_shows_the_code_link_masked_referrals_and_history(): void
    {
        $user = User::factory()->create(['referral_code' => 'SHARE123', 'loyalty_points' => 0]);
        $friend = User::factory()->create(['name' => 'Aishath Mohamed', 'referred_by' => $user->id]);
        $paid = User::factory()->create(['name' => 'Hassan Ali', 'referred_by' => $user->id]);
        $order = $this->placeOrder($paid, 500);
        app(PaymentService::class)->confirm($order);

        $this->actingAs($user)->get(route('account.rewards'))->assertOk()
            ->assertSee('SHARE123')
            ->assertSee(route('referral.visit', 'SHARE123'))
            ->assertSee('wa.me')
            ->assertSee('Ai***** M.')->assertDontSee('Aishath Mohamed')
            ->assertSee('Ha**** A.')->assertDontSee('Hassan Ali')
            ->assertSee('Reward given')->assertSee('Signed up')
            ->assertSee('Referral reward')->assertSee('+100');

        // A user without a code gets one on first visit
        $fresh = User::factory()->create(['referral_code' => null]);
        $this->actingAs($fresh)->get(route('account.rewards'))->assertOk();
        $this->assertNotEmpty($fresh->fresh()->referral_code);
    }

    public function test_rewards_page_needs_an_account(): void
    {
        $this->get(route('account.rewards'))->assertRedirect(route('login'));
    }

    public function test_admin_rewards_report_shows_totals_and_top_referrers(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $points = app(PointsService::class);

        $referrer = User::factory()->create(['name' => 'Top Referrer', 'referral_code' => 'TOPREF01']);
        User::factory()->count(2)->create(['referred_by' => $referrer->id, 'referral_rewarded_at' => now()]);
        $points->record($referrer, 200, 'referral');
        $customer = User::factory()->create();
        $points->record($customer, 120, 'earned');
        $points->record($customer, -40, 'redeemed');
        $points->record($customer, -10, 'expired');

        $this->actingAs($admin)->get(route('admin.rewards'))->assertOk()
            ->assertSee('Rewards report')
            ->assertSee('Top Referrer')->assertSee('TOPREF01')
            ->assertSee('320')  // issued: 120 earned + 200 referral
            ->assertSee('40')   // redeemed
            ->assertSee('10');  // expired

        $this->actingAs($customer)->get(route('admin.rewards'))->assertForbidden();
    }
}
