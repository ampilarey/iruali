<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderNotifier;
use App\Support\SmokeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeSmokeFetcher;
use Tests\TestCase;

class SmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = '<!DOCTYPE html><html><form><input type="hidden" name="_token" value="t" autocomplete="off"></form> agree_terms</html>';

    private function fakeSite(): FakeSmokeFetcher
    {
        $site = (new FakeSmokeFetcher([], 'http://smoke.test'))
            ->on('GET /', 200, self::HTML)
            ->on('GET /sitemap.xml', 200, '<urlset><url><loc>http://smoke.test/products/p</loc></url><url><loc>http://smoke.test/categories/c</loc></url></urlset>')
            ->on('GET /robots.txt', 200, 'User-agent: *')
            ->on('GET /products/p', 200, self::HTML)
            ->on('GET /categories/c', 200, self::HTML)
            ->on('GET /search', 200, self::HTML)
            ->on('GET /cart', 200, self::HTML)
            ->on('GET /login', 200, self::HTML)
            ->on('GET /up', 200, 'ok')
            ->on('GET /api/health', 200, '{"status":"healthy","commit":"abc1234"}');
        $this->app->instance(SmokeFetcher::class, $site);

        return $site;
    }

    public function test_setup_creates_the_flagged_smoke_customer(): void
    {
        config(['services.smoke.email' => 'smoke@iruali.test', 'services.smoke.password' => 'smoke-pass-123']);

        $this->artisan('iruali:smoke --setup')->assertExitCode(0)->expectsOutputToContain('Smoke customer ready');

        $user = User::where('email', 'smoke@iruali.test')->firstOrFail();
        $this->assertTrue($user->is_smoke_test);
        $this->assertTrue($user->isSmokeTest());
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('smoke-pass-123', $user->password));

        // Running it again only refreshes the password; no second account
        config(['services.smoke.password' => 'new-pass-456']);
        $this->artisan('iruali:smoke --setup')->assertExitCode(0);
        $this->assertSame(1, User::where('email', 'smoke@iruali.test')->count());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-pass-456', $user->fresh()->password));
    }

    public function test_setup_refuses_without_credentials(): void
    {
        config(['services.smoke.email' => null, 'services.smoke.password' => null]);

        $this->artisan('iruali:smoke --setup')->assertExitCode(1);
        $this->assertSame(0, User::where('is_smoke_test', true)->count());
    }

    public function test_command_prints_a_table_and_exits_0_when_everything_passes(): void
    {
        $this->fakeSite();

        $this->artisan('iruali:smoke --base-url=http://smoke.test/')
            ->expectsOutputToContain('Smoke test against http://smoke.test')
            ->expectsOutputToContain('API health')
            ->expectsOutputToContain('SMOKE OK')
            ->assertExitCode(0);
    }

    public function test_command_exits_1_and_names_the_failing_check(): void
    {
        $this->fakeSite()->on('GET /api/health', 503, 'down');

        $this->artisan('iruali:smoke --base-url=http://smoke.test')
            ->expectsOutputToContain('HTTP 503')
            ->expectsOutputToContain('SMOKE FAILED (1 failures)')
            ->assertExitCode(1);
    }

    public function test_place_order_needs_the_smoke_user_and_picks_the_cheapest_product_in_stock(): void
    {
        $site = $this->fakeSite();
        config(['services.smoke.email' => 'smoke@iruali.test', 'services.smoke.password' => 'pw']);

        $this->artisan('iruali:smoke --base-url=http://smoke.test --place-order')
            ->expectsOutputToContain('run php artisan iruali:smoke --setup first')
            ->assertExitCode(1);

        $this->artisan('iruali:smoke --setup')->assertExitCode(0);
        $seller = User::factory()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'price' => 5, 'stock_quantity' => 0, 'is_active' => true]);
        Product::factory()->create(['seller_id' => $seller->id, 'price' => 9, 'stock_quantity' => 3, 'is_active' => false]);
        $cheapest = Product::factory()->create(['seller_id' => $seller->id, 'price' => 12, 'stock_quantity' => 3, 'is_active' => true]);
        Product::factory()->create(['seller_id' => $seller->id, 'price' => 20, 'stock_quantity' => 3, 'is_active' => true]);

        $site->on('POST /login', 302, '', ['Location' => 'http://smoke.test/'])
            ->on('POST /cart/add', 302, '', ['Location' => 'http://smoke.test/'])
            ->on('GET /checkout', 200, self::HTML)
            ->on('POST /orders', 302, '', ['Location' => 'https://pay.bml.test/t1'])
            ->on('GET https://pay.bml.test/t1', 200, 'BML')
            ->on('GET /orders', 200, '<a href="http://smoke.test/orders/7">o</a>')
            ->on('POST /orders/7/cancel', 302, '', ['Location' => 'http://smoke.test/orders/7'])
            ->on('GET /orders/7', 200, 'Cancelled');

        $this->artisan('iruali:smoke --base-url=http://smoke.test --place-order')
            ->expectsOutputToContain('Stock restored')
            ->expectsOutputToContain('SMOKE OK')
            ->assertExitCode(0);

        $this->assertSame($cheapest->id, $site->sent('POST', '/cart/add')['data']['product_id']);
        $this->assertSame('smoke@iruali.test', $site->sent('POST', '/login')['data']['email']);
    }

    public function test_smoke_customer_gets_no_emails_rewards_or_analytics(): void
    {
        Notification::fake();
        $smoke = User::factory()->create(['is_smoke_test' => true, 'loyalty_points' => 0]);
        $order = Order::factory()->create(['user_id' => $smoke->id, 'status' => 'pending', 'payment_status' => 'paid', 'loyalty_points_earned' => 50, 'total_amount' => 999]);

        app(OrderNotifier::class)->orderPlaced($order);
        Notification::assertNothingSentTo($smoke);

        app(\App\Services\OrderService::class)->awardRewards($order->fresh());
        $this->assertSame(0, $smoke->fresh()->loyalty_points);
        $this->assertNull($order->fresh()->loyalty_points_awarded_at);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $response = $this->actingAs($admin)->get('/admin/analytics')->assertOk();
        $this->assertSame(0, $response->viewData('stats')['total_orders']);
        $this->assertSame(0.0, $response->viewData('stats')['revenue']);
        $this->assertSame(1, $response->viewData('stats')['total_users'], 'the smoke customer is not counted, the admin is');
        $this->assertSame(0, Order::withoutSmokeTests()->count());
        $this->assertSame(1, Order::count());
    }
}
