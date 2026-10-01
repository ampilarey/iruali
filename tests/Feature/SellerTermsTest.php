<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\SellerTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerTermsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_admin_sets_the_marketplace_terms_and_they_are_validated(): void
    {
        $this->actingAs($this->admin())->get('/admin/settings')->assertOk()
            ->assertSee('Marketplace / seller terms')->assertSee('name="payout_schedule"', false)->assertSee('name="payout_day"', false)->assertSee('name="late_shipment_days"', false);

        $this->put('/admin/settings', [
            'loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50,
            'payout_schedule' => 'fortnightly', 'payout_day' => 'Thursday', 'late_shipment_days' => 5, 'return_window_days' => 10, 'default_commission_rate' => 12.5,
        ])->assertRedirect('/admin/settings');

        $this->assertSame('fortnightly', Setting::get('payout_schedule'));
        $this->assertSame('Thursday', Setting::get('payout_day'));
        $this->assertEquals(5, Setting::get('late_shipment_days'));
        $this->assertSame(['{commission_rate}' => '12.5%', '{payout_schedule}' => 'every two weeks', '{payout_day}' => 'Thursday', '{late_shipment_days}' => '5', '{return_window_days}' => '10'],
            array_intersect_key(SellerTerms::placeholders(), array_flip(['{commission_rate}', '{payout_schedule}', '{payout_day}', '{late_shipment_days}', '{return_window_days}'])));

        $this->put('/admin/settings', ['loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50, 'payout_schedule' => 'daily'])
            ->assertSessionHasErrors('payout_schedule');
        $this->put('/admin/settings', ['loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50, 'late_shipment_days' => 99])
            ->assertSessionHasErrors('late_shipment_days');
    }

    public function test_seller_terms_page_fills_placeholders_in_both_languages(): void
    {
        Setting::set(['default_commission_rate' => 15, 'payout_schedule' => 'monthly', 'payout_day' => 'the 5th', 'late_shipment_days' => 2, 'return_window_days' => 14, 'company_trading_name' => 'iruali']);

        $en = $this->withSession(['locale' => 'en'])->get('/seller-terms')->assertOk()
            ->assertSee('Seller Terms')->assertSee('commission of 15%')->assertSee('every month, on the 5th')->assertSee('within 2 days of the customer')->assertSee('within 14 days of delivery')
            ->assertDontSee('{commission_rate}')->assertDontSee('{trading_name}')->assertSee(route('policies.terms'), false)->assertSee('keep a copy');
        $this->assertDoesNotMatchRegularExpression('/@(if|endif|foreach|endforeach|else)\b/', $en->getContent());

        $dv = $this->withSession(['locale' => 'dv'])->get('/seller-terms')->assertOk()->assertSee('dir="rtl"', false)
            ->assertSee('15%')->assertSee('14')->assertDontSee('{commission_rate}')->assertDontSee('{return_window_days}')->getContent();
        $this->assertGreaterThan(800, preg_match_all('/[\x{0780}-\x{07BF}]/u', $dv), 'The seller terms are translated');

        // The other policy pages link to the seller terms, and the seller application does too
        $this->withSession(['locale' => 'en'])->get('/terms')->assertSee(route('policies.seller_terms'), false);
        $this->actingAs(User::factory()->create())->get('/seller/apply')->assertOk()->assertSee(route('policies.seller_terms'), false);
    }

    public function test_owner_text_also_gets_placeholders_and_the_editor_explains_them(): void
    {
        Setting::set(['default_commission_rate' => 8, 'late_shipment_days' => 4]);

        $this->actingAs($this->admin())->get('/admin/legal')->assertOk()
            ->assertSee('Seller Terms')->assertSee('{commission_rate}')->assertSee('{payout_schedule}')->assertSee('{late_shipment_days}')->assertSee('Placeholders')->assertSee('now: 8%');

        $this->put('/admin/legal', ['legal_seller_terms_body' => "Our own terms.\n\nCommission is {commission_rate} and you ship within {late_shipment_days} days."])
            ->assertRedirect('/admin/legal');

        $this->get('/seller-terms')->assertOk()->assertSee('Our own terms.')->assertSee('Commission is 8% and you ship within 4 days.')->assertDontSee('These terms apply to every shop');
    }
}
