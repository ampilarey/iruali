<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit plan batch 5: phone layout, Dhivehi and accessibility fixes that a page render can show.
 */
class PhoneAndLanguageFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_message_bubble_stays_off_the_add_to_cart_bar_on_a_phone(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="[^"]*hidden lg:flex[^"]*"[^>]*data-floating-message/', $html);
        // The zoom dialog has no empty image address (it would load the page itself)
        $this->assertStringNotContainsString('<img src=""', $html);

        $this->assertDoesNotMatchRegularExpression('/hidden lg:flex[^"]*"[^>]*data-floating-message/', $this->get('/')->getContent());
    }

    public function test_dhivehi_policy_pages_mark_the_english_wording(): void
    {
        $this->get('/dv/terms')->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('This page is in English for now. A Dhivehi version is on its way.', [], 'dv'))
            ->assertSee('<div dir="ltr" lang="en"', false);
        $this->get('/terms')->assertOk()->assertDontSee('<div dir="ltr" lang="en"', false);
    }

    public function test_a_forbidden_page_says_so_in_the_visitors_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden()
            ->assertSee('No access to this page')->assertSee('<h1', false);
        $this->actingAs($user)->withSession(['locale' => 'dv'])->get('/admin/dashboard')->assertForbidden()
            ->assertSee(__('No access to this page', [], 'dv'));
    }

    public function test_seller_centre_in_a_dhivehi_session_is_marked_english(): void
    {
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        $response = $this->actingAs($shop)->withSession(['locale' => 'dv'])->followingRedirects()->get(route('seller.dashboard'))->assertOk();
        $response->assertSee('<html lang="en" dir="ltr">', false);

        $this->withSession(['locale' => 'dv'])->get('/dv')->assertSee('<html lang="dv" dir="rtl">', false);
    }

    public function test_sign_in_and_register_pages_have_a_main_heading(): void
    {
        $this->get(route('login.email'))->assertOk()->assertSee('<h1', false);
        $this->get(route('register'))->assertOk()->assertSee('<h1', false);
    }
}
