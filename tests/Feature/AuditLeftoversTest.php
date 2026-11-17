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
}
