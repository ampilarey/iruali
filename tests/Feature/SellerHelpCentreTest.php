<?php

namespace Tests\Feature;

use App\Http\Controllers\Seller\HelpController;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerHelpCentreTest extends TestCase
{
    use RefreshDatabase;

    protected function seller(): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    public function test_index_links_every_guide_and_the_nav_links_the_help_centre(): void
    {
        $this->actingAs($this->seller());

        $index = $this->get('/seller/help')->assertOk()->assertSee('Help centre');
        foreach (array_keys(HelpController::guides()) as $slug) {
            $index->assertSee(route('seller.help.show', $slug), false);
        }
        $this->assertCount(5, HelpController::guides());

        $this->get('/seller/dashboard')->assertOk()->assertSee(route('seller.help'), false);
        $this->get('/seller/help/no-such-guide')->assertNotFound();
    }

    public function test_guides_exist_in_english_and_dhivehi_and_use_the_live_settings(): void
    {
        Setting::set(['return_window_days' => 9, 'late_shipment_days' => 4, 'default_commission_rate' => 12]);
        $seller = $this->seller();

        foreach (array_keys(HelpController::guides()) as $slug) {
            $this->assertFileExists(resource_path("views/seller/help/en/{$slug}.blade.php"));
            $this->assertFileExists(resource_path("views/seller/help/dv/{$slug}.blade.php"));

            $en = $this->actingAs($seller)->withSession(['locale' => 'en'])->get("/seller/help/{$slug}")->assertOk()->getContent();
            $this->assertStringContainsString('dir="ltr"', $en);
            $this->assertDoesNotMatchRegularExpression('/@(if|endif|foreach|php|endphp)\b/', $en);

            $dv = $this->withSession(['locale' => 'dv'])->get("/seller/help/{$slug}")->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<article dir="rtl"/', $dv, "{$slug} has a right-to-left Dhivehi article");
            $this->assertGreaterThan(500, preg_match_all('/[\x{0780}-\x{07BF}]/u', $dv), "{$slug} contains Dhivehi text");
        }

        $this->withSession(['locale' => 'en']);
        $this->get('/seller/help/handling-returns')->assertSee('9 days');
        $this->get('/seller/help/packing-shipping')->assertSee('4 days of payment');
        $this->get('/seller/help/commission-payouts')->assertSee('12%');
        $this->get('/seller/help/listing-products')->assertSee('Compare-at price')->assertSee('Name (Dhivehi)');
        $this->get('/seller/help/getting-approved')->assertSee('Shop logo')->assertSee('First product');
    }

    public function test_only_sellers_read_the_help_centre(): void
    {
        $this->get('/seller/help')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/seller/help')->assertForbidden();
    }
}
