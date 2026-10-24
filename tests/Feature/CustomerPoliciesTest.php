<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer policy pages describe what the site does today: pre-orders (booking fee kept when the
 * customer cancels), the wallet, gift cards and points, and the personal data the Privacy Policy
 * lists, with the analytics paragraph following Admin → Settings.
 */
class CustomerPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_terms_explain_pre_orders_the_wallet_gift_cards_and_points(): void
    {
        Setting::set(['points_expire_months' => 12]);

        $page = $this->get('/terms')->assertOk()
            ->assertSee('5. Pre-orders')
            ->assertSee('If you cancel a booking-fee order, the booking fee is not refunded;')
            ->assertSee('Nothing until the shop confirms:')
            ->assertSee('Pre-orders cannot be paid in cash on delivery.')
            ->assertSee('3. Wallet, gift cards and loyalty points')
            ->assertSee('A gift card must be redeemed within 12 months of being issued.')
            ->assertSee('expire 12 months after they were earned')
            ->assertSee('Pre-orders follow section 5')
            ->assertSee('If a translation differs, the English version applies.')
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/@(if|endif|elseif)\b/', $page);

        Setting::set(['points_expire_months' => 0]);
        $this->get('/terms')->assertSee('They have no cash value.')->assertDontSee('months after they were earned');
    }

    public function test_the_refund_and_delivery_policies_cover_pre_orders(): void
    {
        $this->get('/refund-policy')->assertOk()
            ->assertSee('2. Pre-orders')
            ->assertSee('<strong>the booking fee is not refunded</strong>', false)
            ->assertSee('If an expected date moves, we email you with a link to cancel.')
            ->assertSee('a booking fee is not refunded if you cancel (section 2)')
            ->assertSee('otherwise you can return it under section 3.');

        $this->get('/delivery-policy')->assertOk()->assertSee('are sent once the item has reached the shop');
    }

    public function test_the_prohibited_items_page_is_linked_where_shops_look(): void
    {
        $url = route('policies.prohibited');
        $shop = \App\Models\User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Goods', 'onboarding_completed_at' => now()]);
        $shop->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        $page = $this->actingAs($shop)->get('/prohibited-items')->assertOk()
            ->assertSee('Prohibited &amp; Restricted Items', false)
            ->assertSee('1. Not allowed on')->assertSee('Alcohol')->assertSee('Pork')->assertSee('Tobacco, e-cigarettes and vapes')
            ->assertSee('Prescription medicines')->assertSee('Protected wildlife')->assertSee('Fake or copied goods')
            ->assertSee('2. Allowed only with a permit or extra care')->assertSee('registered with the MFDA')
            ->assertSee('3. Pre-orders from abroad')->assertSee('is the importer')
            ->assertSee(route('policies.seller_terms'), false)
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/@(if|endif|foreach)\b/', $page);

        // In the Seller Terms and on the product form; the application form links it too
        $this->get('/seller-terms')->assertSee('data-prohibited-link', false)->assertSee($url, false);
        $this->get(route('seller.products.create'))->assertOk()->assertSee('data-prohibited-note', false)->assertSee($url, false);
        $this->actingAs(\App\Models\User::factory()->create())->get('/seller/apply')->assertSee($url, false);

        // The owner can replace it in Admin → Legal pages, like the other policies
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get(route('admin.legal'))->assertSee('name="legal_prohibited_body"', false);
        $this->put(route('admin.legal.update'), ['legal_prohibited_body' => 'Our own list of banned items.'])->assertSessionHas('success');
        $this->get('/prohibited-items')->assertSee('Our own list of banned items.')->assertDontSee('1. Not allowed on');
    }

    public function test_the_privacy_policy_lists_the_data_kept_and_follows_the_analytics_setting(): void
    {
        Setting::set(['analytics_provider' => 'none']);
        $this->get('/privacy-policy')->assertOk()
            ->assertSee('Use my location')->assertSee('We keep the island, not the position.')
            ->assertSee('Date of birth, if you add it')
            ->assertSee('GST (TIN) number')
            ->assertSee('private group of iruali staff on Telegram')
            ->assertSee('Push notifications, if you allow them')
            ->assertSee("the owner's ID card number and photo", false)
            ->assertSee('We do not use advertising cookies.')
            ->assertDontSee('Google Analytics')->assertDontSee('Plausible');

        Setting::set(['analytics_provider' => 'ga4']);
        $this->get('/privacy-policy')->assertSee('Google Analytics</strong>, which sets cookies', false)->assertSee('Do Not Track');

        Setting::set(['analytics_provider' => 'plausible']);
        $this->get('/privacy-policy')->assertSee('use no cookies and no personal data')->assertDontSee('Google Analytics');
    }
}
