<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bank of Maldives' website requirements for card acceptance (see docs/BML_COMPLIANCE.md).
 */
class PolicyPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_policy_page_loads_and_links_the_others(): void
    {
        foreach (['policies.terms', 'policies.refunds', 'policies.delivery', 'policies.privacy', 'policies.security', 'policies.about'] as $route) {
            $response = $this->get(route($route))->assertOk();
            foreach (['policies.terms', 'policies.refunds', 'policies.privacy', 'policies.security'] as $link) {
                $response->assertSee(route($link), false);
            }
            $response->assertSee('keep a copy', false);
        }
    }

    public function test_business_details_from_settings_are_shown(): void
    {
        Setting::set([
            'company_legal_name' => 'Island Market Pvt Ltd',
            'company_registration_no' => 'C-1234/2026',
            'company_address' => 'H. Coral, Majeedhee Magu, Malé 20001',
            'contact_email' => 'hello@iruali.mv',
            'contact_phone' => '+960 777 0000',
        ]);

        $this->get(route('policies.about'))
            ->assertSee('Island Market Pvt Ltd')->assertSee('C-1234/2026')->assertSee('Majeedhee Magu')
            ->assertSee('hello@iruali.mv')->assertSee('+960 777 0000');

        $this->get('/')->assertSee('Island Market Pvt Ltd')->assertSee('Merchant outlet country')->assertSee('/images/card-brands.png', false);
    }

    public function test_policies_state_the_key_disclosures(): void
    {
        $this->get(route('policies.refunds'))->assertSee('cannot be returned')->assertSee('same card');
        $this->get(route('policies.terms'))->assertSee('MVR')->assertSee('customs duties')->assertSee('Merchant outlet', false);
        $this->get(route('policies.privacy'))->assertSee('never receive or store your card number');
        $this->get(route('policies.security'))->assertSee('Bank of Maldives')->assertSee('3-D Secure');
        $this->get(route('policies.delivery'))->assertSee('within the Maldives');
    }

    public function test_checkout_shows_card_marks_currency_country_and_requires_accepting_the_policies(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 100]);

        $this->actingAs($user)->get('/checkout')
            ->assertOk()
            ->assertSee('/images/card-brands.png', false)
            ->assertSee('Transaction currency')
            ->assertSee('Merchant outlet country')
            ->assertSee(route('policies.terms'), false)
            ->assertSee(route('policies.refunds'), false)
            ->assertSee(route('policies.privacy'), false)
            ->assertSee('name="agree_terms"', false);

        $this->post('/orders', [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'payment_method' => 'cod',
        ])->assertSessionHasErrors('agree_terms');
    }

    public function test_admin_sees_what_bml_still_needs(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->actingAs($admin)->get('/admin/settings')->assertSee('Still needed for BML')->assertSee('Business registration number');

        $this->put('/admin/settings', [
            'contact_phone' => '7770000', 'loyalty_spend_per_point' => 100, 'referral_referrer_points' => 1, 'referral_referee_points' => 1,
        ])->assertSessionHasErrors('contact_phone');
    }
}
