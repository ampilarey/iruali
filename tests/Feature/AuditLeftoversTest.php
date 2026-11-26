<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Follow-ups the audit plan left for later (docs/LATER.md, "Left over from the audit").
 */
class AuditLeftoversTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_an_admin_sets_how_many_days_a_shop_has_to_confirm_a_pre_order(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()
            ->assertSee('Days a shop has to confirm a pre-order')->assertSee('name="preorder_confirm_days"', false)->assertSee('value="7"', false);

        $always = ['loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50];
        $this->put(route('admin.settings.update'), $always + ['preorder_confirm_days' => 0])->assertSessionHasErrors('preorder_confirm_days');
        $this->put(route('admin.settings.update'), $always + ['preorder_confirm_days' => 3])->assertSessionHasNoErrors()->assertRedirect(route('admin.settings'));
        $this->assertSame(3, (int) Setting::get('preorder_confirm_days'));
    }

    public function test_the_apps_voucher_box_has_the_same_guess_limit_as_the_websites(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/cart/apply-voucher', ['voucher_code' => 'GUESS-'.$i])->assertStatus(400);
        }
        $this->postJson('/api/v1/cart/apply-voucher', ['voucher_code' => 'GUESS-X'])->assertStatus(429);
        // Its own counter: the rest of the API still answers
        $this->getJson('/api/v1/cart')->assertOk();
    }

    public function test_a_page_reads_the_settings_from_the_cache_once(): void
    {
        \App\Models\Product::factory()->count(3)->create(['stock_quantity' => 5]);
        $this->get('/')->assertOk(); // warm

        $reads = 0;
        $count = function ($event) use (&$reads) {
            $reads += $event->key === 'app_settings' ? 1 : 0;
        };
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Cache\Events\CacheHit::class, $count);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Cache\Events\CacheMissed::class, $count);

        $this->get('/')->assertOk();
        // With the database cache store each read is a query; the home page asks for dozens of settings
        $this->assertLessThanOrEqual(1, $reads, 'settings were read from the cache '.$reads.' times in one page load');

        // A saved setting is seen at once, in the same request too
        Setting::set(['announcement_text' => 'Fresh news']);
        $this->assertSame('Fresh news', Setting::get('announcement_text'));
        $this->get('/')->assertOk()->assertSee('Fresh news');
    }

    public function test_a_long_running_command_sees_a_setting_changed_elsewhere_within_seconds(): void
    {
        Setting::set(['sms_enabled' => 1]);
        $this->assertTrue(\App\Services\Sms\SmsManager::enabled());

        // An admin switches SMS off in another process: the row and the shared cache change, not this process
        \Illuminate\Support\Facades\DB::table('settings')->where('key', 'sms_enabled')->update(['value' => '0']);
        \Illuminate\Support\Facades\Cache::forget('app_settings');

        $this->travel(6)->seconds();
        $this->assertFalse(\App\Services\Sms\SmsManager::enabled(), 'an SMS campaign going out stops at the switch');
    }

    public function test_a_language_given_as_a_list_is_ignored_not_an_error(): void
    {
        $this->get('/?locale[]=dv')->assertOk();
        $this->get('/?locale=en')->assertOk();
    }

    public function test_a_name_someone_typed_cannot_put_a_link_into_an_email(): void
    {
        $user = User::factory()->create(['name' => '[Claim your prize](https://evil.example)']);
        $cart = \App\Models\Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $html = (string) (new \App\Notifications\CartReminder($cart))->toMail($user)->render();
        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringContainsString('Claim your prize', $html);
    }
}
