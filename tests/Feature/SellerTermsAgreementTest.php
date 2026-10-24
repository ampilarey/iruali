<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SellerTermsAgreement;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SellerTermsUpdated;
use App\Support\SellerTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\ActsAsShopStaff;
use Tests\TestCase;

/**
 * The Seller Terms have versions (App\Support\SellerTerms): a shop agrees when it applies, and each
 * time a new version is published it must agree again before it can use the Seller Centre
 * (App\Http\Middleware\ShopAccess). Every agreement is kept, with who agreed and from where.
 */
class SellerTermsAgreementTest extends TestCase
{
    use ActsAsShopStaff, RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    /** An approved shop that agreed to an older version (or never). */
    protected function shopBehind(?string $version = null): User
    {
        return $this->staffedShop('Reef Goods', ['seller_terms_version' => $version, 'onboarding_completed_at' => now()]);
    }

    public function test_a_new_shop_agrees_to_the_current_version_when_it_applies(): void
    {
        $user = User::factory()->create(['seller_terms_version' => null]);

        $this->actingAs($user)->post('/seller/apply', [
            'business_name' => 'Island Crafts',
            'business_description' => 'Handmade coir rope, woven mats and lacquer work from Thulhaadhoo.',
            'phone' => '7771234',
            'address' => 'M. Blue House',
            'home_island_id' => \App\Models\Island::where('code', 'kaafu-male')->value('id'),
            'delivery_coverage' => 'all',
            'agree_seller_terms' => '1',
        ], ['User-Agent' => 'Test browser'])->assertRedirect(route('seller.dashboard'));

        $user->refresh();
        $this->assertSame(SellerTerms::BUILT_IN_VERSION, $user->seller_terms_version);
        $this->assertNotNull($user->seller_terms_agreed_at);
        $agreement = SellerTermsAgreement::sole();
        $this->assertSame([$user->id, $user->id, SellerTerms::BUILT_IN_VERSION, '127.0.0.1', 'Test browser'],
            [$agreement->user_id, $agreement->agreed_by, $agreement->version, $agreement->ip, $agreement->user_agent]);
        $this->get(route('seller.dashboard'))->assertOk();
    }

    public function test_a_shop_that_has_not_agreed_is_sent_to_the_terms_until_the_owner_agrees(): void
    {
        $shop = $this->shopBehind();

        $this->actingAs($shop)->get(route('seller.orders'))->assertRedirect(route('seller.terms.show'));
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.terms.show'));
        $this->postJson(route('seller.products.store'), ['name_en' => 'Mat'])->assertStatus(409)
            ->assertJson(['message' => 'iruali\'s Seller Terms have changed. The shop owner must agree to the new terms before the Seller Centre can be used.']);

        $this->get(route('seller.terms.show'))->assertOk()
            ->assertSee('Please agree to iruali&#039;s Seller Terms', false)
            ->assertSee('Version of 24 October 2026.')
            ->assertSee('data-seller-terms-changes', false)->assertSee('Customer details: only for the customer&#039;s own order', false)
            ->assertSee(route('policies.seller_terms'), false)->assertSee('data-seller-terms-agree', false);

        // The box must be ticked
        $this->post(route('seller.terms.agree'))->assertSessionHasErrors('agree');
        $this->assertNull($shop->fresh()->seller_terms_version);

        // Agreeing opens the Seller Centre again, on the page the shop was going to
        $this->post(route('seller.terms.agree'), ['agree' => '1'])->assertRedirect(route('seller.dashboard'))
            ->assertSessionHas('success', 'Thank you. You agreed to the Seller Terms of 24 October 2026.');
        $this->assertTrue(SellerTerms::agreed($shop->fresh()));
        $this->assertSame(1, $shop->sellerTermsAgreements()->count());
        $this->get(route('seller.orders'))->assertOk();
        $this->get(route('seller.terms.show'))->assertOk()->assertSee('data-seller-terms-agreed', false);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'seller_terms.agreed')->count());
    }

    public function test_staff_see_the_terms_page_but_only_the_owner_can_agree(): void
    {
        $shop = $this->shopBehind('2026-01-01');
        $packer = $this->staffMember($shop, 'packer');

        $this->actingAs($packer)->get(route('seller.orders'))->assertRedirect(route('seller.terms.show'));
        $this->get(route('seller.terms.show'))->assertOk()
            ->assertSee('iruali&#039;s Seller Terms have changed', false)->assertSee('Your shop agreed to the version of 1 January 2026.')
            ->assertSee('data-seller-terms-staff', false)->assertDontSee('data-seller-terms-agree', false);
        $this->post(route('seller.terms.agree'), ['agree' => '1'])->assertForbidden();
        $this->assertFalse(SellerTerms::agreed($shop->fresh()));

        SellerTerms::recordAgreement($shop->fresh());
        $this->get(route('seller.orders'))->assertOk();
    }

    public function test_an_admin_publishes_a_new_version_and_every_shop_must_agree_again(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $shop = $this->staffedShop('Reef Goods', ['onboarding_completed_at' => now()]);
        $suspended = $this->staffedShop('Closed Shop', ['status' => 'suspended']);
        $this->assertTrue(SellerTerms::agreed($shop));

        $this->actingAs($admin)->get(route('admin.legal'))->assertOk()
            ->assertSee('Current version: 24 October 2026.')->assertSee('2 of 2 shops have agreed to it.')->assertSee('name="seller_terms_publish"', false);

        // Saving a typo fix is not a new version
        $this->put(route('admin.legal.update'), ['legal_last_updated_date' => '24 October 2026'])->assertSessionHas('success', 'Legal pages saved.');
        $this->assertSame(SellerTerms::BUILT_IN_VERSION, SellerTerms::version());

        // A new version needs a note of what changed
        $this->put(route('admin.legal.update'), ['seller_terms_publish' => '1'])->assertSessionHasErrors('seller_terms_changes');
        $this->assertSame(SellerTerms::BUILT_IN_VERSION, SellerTerms::version());

        $this->travel(1)->days();
        $this->put(route('admin.legal.update'), ['seller_terms_publish' => '1', 'seller_terms_changes' => "Commission on delivery fees.\nNew payout day."])
            ->assertSessionHas('success');
        $version = SellerTerms::version();
        $this->assertNotSame(SellerTerms::BUILT_IN_VERSION, $version);
        $this->assertSame(['Commission on delivery fees.', 'New payout day.'], SellerTerms::changes());
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'seller_terms.published')->count());
        Notification::assertSentTo($shop, SellerTermsUpdated::class, fn ($n) => $n->version === $version);
        Notification::assertNotSentTo($suspended, SellerTermsUpdated::class);
        $mail = (new SellerTermsUpdated($version))->toMail($shop->fresh());
        $this->assertStringContainsString('Seller Terms have changed', $mail->subject);
        $this->assertContains('• New payout day.', $mail->introLines);

        // Admin → Sellers shows who has not agreed; the shop is asked again
        $this->get(route('admin.sellers'))->assertOk()->assertSee('data-seller-terms-pending', false);
        $this->get(route('admin.sellers.performance', $shop))->assertOk()->assertSee('not the current version');
        $this->actingAs($shop->fresh())->get(route('seller.dashboard'))->assertRedirect(route('seller.terms.show'));
        $this->get(route('seller.terms.show'))->assertSee('New payout day.');
        $this->post(route('seller.terms.agree'), ['agree' => '1']);
        $this->assertSame($version, $shop->fresh()->seller_terms_version);
        $this->assertSame([$version], $shop->sellerTermsAgreements()->pluck('version')->all());
        $this->actingAs($admin)->get(route('admin.legal'))->assertSee('1 of 2 shops have agreed to it.');
    }

    public function test_the_seller_policies_are_only_for_shops_the_team_and_would_be_shops_while_applications_are_open(): void
    {
        $pages = ['/seller-terms', '/prohibited-items'];
        $shop = $this->staffedShop('Reef Goods', ['onboarding_completed_at' => now()]);
        $packer = $this->staffMember($shop, 'packer');
        $support = User::factory()->create();
        $support->roles()->attach(Role::firstOrCreate(['name' => 'support'], ['display_name' => 'Support'])->id);
        $customer = User::factory()->create();

        // Applications open: visitors are asked to sign in, then read them like the application form
        foreach ($pages as $page) {
            $this->get($page)->assertRedirect(route('login'));
        }
        $this->get('/')->assertOk()->assertDontSee(route('policies.prohibited'), false)->assertDontSee(route('policies.seller_terms'), false);
        $this->get('/terms')->assertOk()->assertDontSee(route('policies.seller_terms'), false)->assertDontSee(route('policies.prohibited'), false);
        foreach ($pages as $page) {
            $this->actingAs($customer)->get($page)->assertOk();
        }
        $this->get('/terms')->assertSee(route('policies.seller_terms'), false);

        // Applications paused or hidden: only shops, their staff and the team
        foreach (['paused', 'hidden'] as $state) {
            Setting::set(['seller_applications' => $state]);
            \Illuminate\Support\Facades\Auth::forgetGuards();
            foreach ($pages as $page) {
                $this->get($page)->assertNotFound();
            }
            foreach ($pages as $page) {
                $this->actingAs($customer)->get($page)->assertNotFound();
                foreach ([$shop, $packer, $support] as $reader) {
                    $this->actingAs($reader)->get($page)->assertOk();
                }
            }
            $this->actingAs($customer)->get('/terms')->assertDontSee(route('policies.seller_terms'), false);
            $this->actingAs($shop)->get('/terms')->assertSee(route('policies.seller_terms'), false)->assertSee(route('policies.prohibited'), false);
        }
    }

    public function test_the_seller_terms_cover_pre_orders_payments_rights_customer_data_and_tax(): void
    {
        Setting::set(['company_trading_name' => 'iruali']);

        $this->actingAs($this->staffedShop())->get('/seller-terms')->assertOk()
            ->assertSee('Version of 24 October 2026')
            ->assertSee('A pre-order is a promise.')
            ->assertSee('by bank transfer (once iruali has confirmed it) or, where offered, in cash on delivery')
            ->assertSee('This includes what &quot;Import from a link&quot; copies from another website', false)
            ->assertSee('no marketing, no adding customers to lists or groups')
            ->assertSee('Never ask a customer to pay you outside iruali')
            ->assertSee('registering for GST with MIRA')
            ->assertSee('a chargeback')
            ->assertSee('If the two differ, the English version applies.')
            ->assertDontSee('{trading_name}');
    }
}
