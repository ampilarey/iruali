<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\BackInStock;
use App\Services\StockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin → Settings → "Dhivehi on the site": off makes the site English only (no language button,
 * /dv pages open the English page, emails in English, no Dhivehi pages in the sitemap), keeping all
 * Dhivehi text for when it comes back on.
 */
class DhivehiSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        return Product::factory()->create(['slug' => 'copper-kettle', 'name' => ['en' => 'Copper kettle', 'dv' => 'ކޮޕަރ ކެތަލް'], 'stock_quantity' => 5]);
    }

    public function test_on_by_default_with_the_language_button_and_dhivehi_pages(): void
    {
        $this->product();

        $this->get('/')->assertOk()->assertSee('ދިވެހި');
        $this->get('/dv/products/copper-kettle')->assertOk()->assertSee('dir="rtl"', false)->assertSee('ކޮޕަރ ކެތަލް');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/dv/products/copper-kettle', false);
    }

    public function test_off_the_site_is_english_only(): void
    {
        $this->product();
        Setting::set(['dhivehi_enabled' => 0]);

        // No language button, and English even for a Dhivehi phone or an old choice
        $this->get('/')->assertOk()->assertDontSee('hreflang="dv"', false)->assertDontSee('ދިވެހި');
        $this->withHeader('Accept-Language', 'dv')->get('/')->assertOk()->assertSee('lang="en"', false);
        $this->withSession(['locale' => 'dv'])->get('/products/copper-kettle')->assertOk()->assertSee('Copper kettle')->assertSee('dir="ltr"', false);

        // A /dv link opens the same page in English
        $this->get('/dv/products/copper-kettle')->assertRedirect(url('/products/copper-kettle'));
        $this->get('/dv')->assertRedirect(url('/'));
        $this->get('/dv/products?sort=newest')->assertRedirect(url('/products?sort=newest'));

        // Switching to Dhivehi is refused; the sitemap lists English pages only
        $this->post(route('locale.switch'), ['locale' => 'dv'])->assertSessionHasErrors('locale');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/products/copper-kettle', false)->assertDontSee('/dv/', false);
    }

    public function test_emails_go_out_in_english_while_it_is_off(): void
    {
        Notification::fake();
        $product = $this->product();
        $customer = User::factory()->create(['preferred_language' => 'dv']);
        $this->assertSame('dv', $customer->preferredLocale());

        Setting::set(['dhivehi_enabled' => 0]);
        $this->assertSame('en', $customer->fresh()->preferredLocale());

        // An alert stored in Dhivehi still goes out
        StockAlert::create(['product_id' => $product->id, 'email' => 'fan@example.com', 'locale' => 'dv']);
        app(StockAlertService::class)->productRestocked($product);
        Notification::assertSentOnDemandTimes(BackInStock::class, 1);

        // and is written in English: the listener puts a Dhivehi send back in English (the sender
        // restores the language afterwards)
        app()->setLocale('dv');
        event(new \Illuminate\Notifications\Events\NotificationSending(new \Illuminate\Notifications\AnonymousNotifiable, new BackInStock($product), 'mail'));
        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_admin_switches_it_and_dhivehi_text_is_kept(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $product = $this->product();

        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('data-dhivehi-setting', false);
        $fields = ['loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50];

        $this->put(route('admin.settings.update'), $fields + ['dhivehi_enabled' => 0])->assertRedirect(route('admin.settings'));
        $this->assertFalse(\App\Services\LocalizationService::dhivehiEnabled());
        $this->assertSame('ކޮޕަރ ކެތަލް', $product->fresh()->getTranslation('name', 'dv'));

        $this->put(route('admin.settings.update'), $fields + ['dhivehi_enabled' => 1]);
        $this->assertTrue(\App\Services\LocalizationService::dhivehiEnabled());
        auth()->logout();
        $this->get('/dv/products/copper-kettle')->assertOk()->assertSee('ކޮޕަރ ކެތަލް');
    }
}
