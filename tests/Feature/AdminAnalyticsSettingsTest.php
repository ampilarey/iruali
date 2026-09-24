<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $user;
    }

    public function test_non_admins_cannot_view_analytics_or_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/analytics')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings', [])->assertForbidden();
    }

    public function test_analytics_page_shows_sales_data(): void
    {
        $seller = User::factory()->create(['name' => 'Atoll Traders']);
        $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => 250]);

        $order = Order::factory()->create(['status' => 'delivered', 'total_amount' => 500]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 250]);
        Order::factory()->create(['status' => 'cancelled', 'total_amount' => 9999]);

        $this->actingAs($this->admin())
            ->get('/admin/analytics')
            ->assertOk()
            ->assertSee('500.00')
            ->assertDontSee('9,999.00')
            ->assertSee('Atoll Traders');
    }

    public function test_admin_can_update_settings_and_they_take_effect(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', [
                'announcement_text' => 'Eid sale this week',
                'contact_email' => 'help@iruali.mv',
                'contact_phone' => '+960 777 0000',
                'loyalty_spend_per_point' => 50,
                'referral_referrer_points' => 200,
                'referral_referee_points' => 75,
            ])
            ->assertRedirect('/admin/settings');

        $this->assertSame('Eid sale this week', Setting::get('announcement_text'));
        $this->assertSame(4, app(DiscountService::class)->calculateLoyaltyPointsEarned(200));

        $this->get('/')->assertSee('Eid sale this week')->assertSee('mailto:help@iruali.mv', false);

        $referrer = User::factory()->create(['loyalty_points' => 0]);
        $referee = User::factory()->create(['loyalty_points' => 0, 'referred_by' => $referrer->id]);
        Order::factory()->create(['user_id' => $referee->id]);

        app(DiscountService::class)->processReferralRewards($referee);

        $this->assertSame(200, $referrer->fresh()->loyalty_points);
        $this->assertSame(75, $referee->fresh()->loyalty_points);
    }

    public function test_blank_announcement_hides_the_bar(): void
    {
        $default = Setting::DEFAULTS['announcement_text'];
        $this->get('/')->assertSee($default);

        Setting::set(['announcement_text' => null]);

        $this->get('/')->assertDontSee($default);
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', ['loyalty_spend_per_point' => 0, 'contact_email' => 'not-an-email'])
            ->assertSessionHasErrors(['loyalty_spend_per_point', 'contact_email', 'referral_referrer_points']);
    }
}
