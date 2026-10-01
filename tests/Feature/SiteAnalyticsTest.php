<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\FunnelEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\FunnelService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Site analytics: the Plausible / GA4 tag rules and the first-party shopping funnel.
 */
class SiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $user;
    }

    public function test_no_snippet_without_a_provider(): void
    {
        $this->get('/')->assertOk()->assertDontSee('plausible.io', false)->assertDontSee('googletagmanager', false);
    }

    public function test_plausible_snippet_on_storefront_pages_only(): void
    {
        Setting::set(['analytics_provider' => 'plausible', 'analytics_domain' => 'iruali.mv']);

        $this->get('/')->assertOk()->assertSee('data-domain="iruali.mv"', false)->assertSee('plausible.io/js/script.js', false);
        $this->get('/', ['DNT' => '1'])->assertOk()->assertDontSee('plausible.io', false);
        $this->get('/', ['Sec-GPC' => '1'])->assertOk()->assertDontSee('plausible.io', false);
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk()->assertDontSee('plausible.io', false);

        // Plausible needs the domain; without it nothing is output
        Setting::set(['analytics_domain' => '']);
        $this->get('/')->assertOk()->assertDontSee('plausible.io', false);
    }

    public function test_ga4_snippet_anonymises_ip(): void
    {
        Setting::set(['analytics_provider' => 'ga4', 'analytics_id' => 'G-ABC123']);

        $this->get('/')->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-ABC123', false)
            ->assertSee("'anonymize_ip': true", false)
            ->assertDontSee('plausible.io', false);
        $this->get('/', ['DNT' => '1'])->assertOk()->assertDontSee('googletagmanager', false);

        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->actingAs($seller)->get('/seller/dashboard')->assertOk()->assertDontSee('googletagmanager', false);
    }

    public function test_admin_saves_analytics_settings_and_bad_ids_are_rejected(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', $this->settingsPayload(['analytics_provider' => 'ga4', 'analytics_id' => 'G-XYZ789']))
            ->assertRedirect(route('admin.settings'));
        $this->assertSame('ga4', Setting::get('analytics_provider'));
        $this->assertSame('G-XYZ789', Setting::get('analytics_id'));

        $this->actingAs($this->admin())->put('/admin/settings', $this->settingsPayload(['analytics_provider' => 'ga4', 'analytics_id' => '<script>']))
            ->assertSessionHasErrors('analytics_id');
        $this->actingAs($this->admin())->put('/admin/settings', $this->settingsPayload(['analytics_provider' => 'matomo']))
            ->assertSessionHasErrors('analytics_provider');

        $this->actingAs($this->admin())->get('/admin/settings')->assertOk()->assertSee('Site analytics')->assertSee('G-XYZ789');
    }

    public function test_funnel_events_are_recorded_server_side(): void
    {
        $this->enableBml();
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

        $this->get(route('products.show', $product))->assertOk();
        $this->assertDatabaseHas('funnel_events', ['event' => 'view_product', 'product_id' => $product->id, 'user_id' => null]);
        $hash = FunnelEvent::where('event', 'view_product')->value('session_hash');
        $this->assertSame(64, strlen($hash));
        $this->assertStringNotContainsString(session()->getId(), $hash);

        $this->actingAs($customer)->post('/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect(route('cart'));
        $this->assertDatabaseHas('funnel_events', ['event' => 'add_to_cart', 'product_id' => $product->id, 'user_id' => $customer->id]);

        $this->actingAs($customer)->get('/checkout')->assertOk();
        $this->assertDatabaseHas('funnel_events', ['event' => 'begin_checkout', 'user_id' => $customer->id]);

        Notification::fake();
        $order = Order::factory()->create(['user_id' => $customer->id, 'payment_method' => 'bml', 'payment_status' => 'unpaid', 'status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
        app(PaymentService::class)->confirm($order);
        $this->assertDatabaseHas('funnel_events', ['event' => 'order_paid', 'product_id' => $product->id, 'order_id' => $order->id]);
    }

    public function test_funnel_numbers_and_product_conversion(): void
    {
        $hot = Product::factory()->create(['name' => 'Hot kettle']);
        $cold = Product::factory()->create(['name' => 'Cold kettle']);

        // 4 visitors viewed, 2 added to cart, 1 checked out, 1 paid (an old row is outside the window)
        foreach (['a', 'b', 'c', 'd'] as $visitor) {
            FunnelEvent::create(['session_hash' => hash('sha256', $visitor), 'event' => 'view_product', 'product_id' => $hot->id, 'created_at' => now()->subDay()]);
        }
        FunnelEvent::create(['session_hash' => hash('sha256', 'a'), 'event' => 'view_product', 'product_id' => $cold->id, 'created_at' => now()->subDay()]); // same visitor twice
        FunnelEvent::create(['session_hash' => hash('sha256', 'a'), 'event' => 'add_to_cart', 'product_id' => $hot->id, 'created_at' => now()->subDay()]);
        FunnelEvent::create(['session_hash' => hash('sha256', 'b'), 'event' => 'add_to_cart', 'product_id' => $hot->id, 'created_at' => now()->subDay()]);
        FunnelEvent::create(['session_hash' => hash('sha256', 'b'), 'event' => 'add_to_cart', 'product_id' => $cold->id, 'created_at' => now()->subDay()]);
        FunnelEvent::create(['session_hash' => hash('sha256', 'a'), 'event' => 'begin_checkout', 'created_at' => now()->subDay()]);
        $order = Order::factory()->create();
        FunnelEvent::create(['session_hash' => hash('sha256', 'order:1'), 'event' => 'order_paid', 'product_id' => $hot->id, 'order_id' => $order->id, 'created_at' => now()->subDay()]);
        FunnelEvent::create(['session_hash' => hash('sha256', 'z'), 'event' => 'view_product', 'product_id' => $hot->id, 'created_at' => now()->subDays(10)]);
        FunnelEvent::create(['session_hash' => hash('sha256', 'old'), 'event' => 'view_product', 'product_id' => $hot->id, 'created_at' => now()->subDays(100)]);

        $week = FunnelService::funnel(7);
        $this->assertSame(4, $week['view_product']['count']);
        $this->assertNull($week['view_product']['rate']);
        $this->assertSame(2, $week['add_to_cart']['count']);
        $this->assertSame(50.0, $week['add_to_cart']['rate']);
        $this->assertSame(1, $week['begin_checkout']['count']);
        $this->assertSame(50.0, $week['begin_checkout']['rate']);
        $this->assertSame(1, $week['order_paid']['count']);
        $this->assertSame(100.0, $week['order_paid']['rate']);
        $this->assertSame(5, FunnelService::funnel(30)['view_product']['count']);

        $top = FunnelService::topProducts(30);
        $this->assertSame([$hot->id, $cold->id], $top->pluck('product_id')->all());
        $this->assertSame(50.0, $top[0]->rate);
        $this->assertSame(0.0, $top[1]->rate);

        $this->actingAs($this->admin())->get('/admin/analytics')->assertOk()
            ->assertSee('Funnel')
            ->assertSee('data-funnel="view_product-7">4<', false)
            ->assertSee('data-funnel="view_product-30">5<', false)
            ->assertSee('data-funnel="add_to_cart-rate">40.0%<', false)
            ->assertSeeInOrder(['Hot kettle', 'Cold kettle']);

        // Pruning keeps the window and drops the rest
        $this->assertSame(1, FunnelService::prune());
        $this->assertSame(0, FunnelEvent::where('created_at', '<', now()->subDays(90))->count());
    }

    protected function settingsPayload(array $overrides): array
    {
        return array_merge([
            'loyalty_spend_per_point' => 100,
            'referral_referrer_points' => 100,
            'referral_referee_points' => 50,
        ], $overrides);
    }
}
