<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\ShopTaxProfile;
use App\Services\GstService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GstFixtures;
use Tests\TestCase;

/**
 * Admin → Tax (iruali's GST settings) and Seller Centre → Settings → Tax (a shop's GST details).
 */
class GstSettingsTest extends TestCase
{
    use GstFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_defaults_change_nothing_until_set(): void
    {
        $gst = app(GstService::class);

        $this->assertFalse($gst->platformRegistered());
        $this->assertNull($gst->platformTin());
        $this->assertSame(8.0, $gst->rate());
        $this->assertSame('INV', $gst->invoicePrefix());
    }

    public function test_admin_saves_the_tax_settings_and_the_change_is_audited(): void
    {
        $admin = $this->staff('admin');
        Setting::set(['company_legal_name' => 'Iruali Pvt Ltd', 'company_address' => 'H. Example, Malé']);

        $this->actingAs($admin)->get(route('admin.tax'))->assertOk()
            ->assertSee('GST-ready')->assertSee('iruali is not GST-registered')->assertSee('name="gst_tin"', false)
            ->assertSee('value="8.00"', false)->assertSee('Iruali Pvt Ltd')->assertSee('H. Example, Malé')
            ->assertSee('data-nav="admin.tax"', false)->assertDontSee('compliant');

        // Registered needs a TIN in MIRA's format
        $this->actingAs($admin)->put(route('admin.tax.update'), ['gst_registered' => '1', 'gst_tin' => '', 'gst_rate' => '8', 'invoice_prefix' => 'INV'])->assertSessionHasErrors('gst_tin');
        $this->actingAs($admin)->put(route('admin.tax.update'), ['gst_registered' => '1', 'gst_tin' => '1012345GST5', 'gst_rate' => '8', 'invoice_prefix' => 'INV'])->assertSessionHasErrors('gst_tin');
        $this->actingAs($admin)->put(route('admin.tax.update'), ['gst_registered' => '0', 'gst_tin' => '', 'gst_rate' => '101', 'invoice_prefix' => 'IN V'])->assertSessionHasErrors(['gst_rate', 'invoice_prefix']);
        $this->assertFalse(app(GstService::class)->platformRegistered());

        $this->actingAs($admin)->put(route('admin.tax.update'), ['gst_registered' => '1', 'gst_tin' => '1000001 gst 501', 'gst_rate' => '8.5', 'invoice_prefix' => 'iru'])
            ->assertRedirect(route('admin.tax'));

        $gst = app(GstService::class);
        $this->assertTrue($gst->platformRegistered());
        $this->assertSame('1000001GST501', $gst->platformTin());
        $this->assertSame(8.5, $gst->rate());
        $this->assertSame('IRU', $gst->invoicePrefix());

        $log = AuditLog::where('action', 'tax.settings_saved')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['gst_registered' => false, 'gst_tin' => '', 'gst_rate' => 8, 'invoice_prefix' => 'INV'], $log->changes['before']);
        $this->assertSame(['gst_registered' => true, 'gst_tin' => '1000001GST501', 'gst_rate' => 8.5, 'invoice_prefix' => 'IRU'], $log->changes['after']);
        $this->actingAs($admin)->get(route('admin.audit', ['action' => 'tax.settings_saved']))->assertOk()->assertSee('Tax settings saved');

        // Saving the same values again is not a change
        $this->actingAs($admin)->put(route('admin.tax.update'), ['gst_registered' => '1', 'gst_tin' => '1000001GST501', 'gst_rate' => '8.50', 'invoice_prefix' => 'IRU']);
        $this->assertSame(1, AuditLog::where('action', 'tax.settings_saved')->count());

        $this->actingAs($admin)->get(route('admin.tax'))->assertOk()->assertSee('iruali is GST-registered')->assertSee('1000001GST501');
    }

    public function test_finance_staff_open_the_tax_pages_and_support_and_customers_do_not(): void
    {
        $finance = $this->staff('finance');
        $this->actingAs($finance)->get(route('admin.tax'))->assertOk();
        $this->actingAs($finance)->get(route('admin.tax.report'))->assertOk();
        $this->actingAs($finance)->get(route('admin.dashboard'))->assertOk()->assertSee('data-nav="admin.tax"', false);
        $this->actingAs($finance)->put(route('admin.tax.update'), ['gst_registered' => '0', 'gst_tin' => '', 'gst_rate' => '8', 'invoice_prefix' => 'INV'])->assertRedirect(route('admin.tax'));

        $support = $this->staff('support');
        $this->actingAs($support)->get(route('admin.tax'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.tax.report'))->assertForbidden();
        $this->actingAs($support)->put(route('admin.tax.update'), ['gst_registered' => '1'])->assertForbidden();
        $this->actingAs($support)->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-nav="admin.tax"', false);

        $this->actingAs($this->shop('Island Crafts'))->get(route('admin.tax'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.tax'))->assertRedirect(route('login'));
    }

    public function test_a_shop_saves_its_tax_details_and_admins_see_them(): void
    {
        $shop = $this->shop('Island Crafts');

        $this->actingAs($shop)->get(route('seller.settings.bank'))->assertOk()->assertSee(route('seller.settings.tax'), false);
        $this->actingAs($shop)->get(route('seller.settings.tax'))->assertOk()
            ->assertSee('Is your shop GST-registered?')->assertSee('name="tin"', false)->assertSee('value="Island Crafts"', false);

        // Registered needs a valid TIN, the registered name and the address
        $this->actingAs($shop)->put(route('seller.settings.tax.update'), ['gst_registered' => '1', 'tin' => 'GST123', 'registered_name' => '', 'business_address' => ''])
            ->assertSessionHasErrors(['tin', 'registered_name', 'business_address']);
        $this->assertSame(0, ShopTaxProfile::count());

        $this->actingAs($shop)->put(route('seller.settings.tax.update'), ['gst_registered' => '1', 'tin' => '1012345 gst 501', 'registered_name' => 'Island Crafts Pvt Ltd', 'business_address' => 'H. Coral View, Malé'])
            ->assertRedirect(route('seller.settings.tax'));
        $profile = ShopTaxProfile::where('user_id', $shop->id)->sole();
        $this->assertTrue($profile->gst_registered);
        $this->assertSame('1012345GST501', $profile->tin);
        $this->assertTrue($profile->isRegistered());

        $log = AuditLog::where('action', 'shop.tax_details_saved')->sole();
        $this->assertSame($shop->id, $log->subject_id);
        $this->assertSame('1012345GST501', $log->changes['after']['tin']);

        $this->actingAs($this->staff('admin'))->get(route('admin.sellers'))->assertOk()
            ->assertSee('data-seller-tax', false)->assertSee('1012345GST501')->assertSee('Island Crafts Pvt Ltd');
        $this->actingAs($this->staff('admin'))->get(route('admin.tax'))->assertOk()->assertSee('1012345GST501');

        // Not registered: the TIN may be left out, but if given it must be valid
        $this->actingAs($shop)->put(route('seller.settings.tax.update'), ['gst_registered' => '0', 'tin' => 'nonsense'])->assertSessionHasErrors('tin');
        $this->actingAs($shop)->put(route('seller.settings.tax.update'), ['gst_registered' => '0', 'tin' => ''])->assertSessionHasNoErrors();
        $this->assertFalse($profile->fresh()->gst_registered);
        $this->actingAs($this->staff('admin'))->get(route('admin.sellers'))->assertOk()->assertSee('Not GST-registered');

        // Customers can't reach the page
        $this->actingAs(\App\Models\User::factory()->create())->get(route('seller.settings.tax'))->assertForbidden();
    }
}
