<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\CartReminder;
use App\Support\SmsOptOut;
use App\Support\SmsSegments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SMS campaigns to segments (preview, test, schedule, stop link, the monthly limit) and the
 * abandoned-cart SMS.
 */
class SmsCampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create(['phone' => '7770001']);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    protected function smsFan(string $phone, array $attributes = []): User
    {
        return User::factory()->create($attributes + ['phone' => $phone, 'phone_verified_at' => now(), 'notification_preferences' => ['customer' => ['marketing' => 'sms']]]);
    }

    public function test_only_customers_who_chose_offers_by_sms_are_in_a_segment(): void
    {
        $fan = $this->smsFan('7771111', ['name' => 'Aisha Ibrahim']);
        $this->smsFan('7772222', ['marketing_opt_out_at' => now()]);
        User::factory()->create(['phone' => '7773333', 'phone_verified_at' => now()]); // email for offers
        $this->smsFan('7774444', ['phone_verified_at' => null]);

        $this->assertSame([$fan->id], SmsSegments::recipients('all', null)->pluck('id')->all());
        $this->assertSame(1, SmsSegments::parts(str_repeat('a', 160)));
        $this->assertSame(2, SmsSegments::parts(str_repeat('a', 161)));
        $this->assertSame(2, SmsSegments::parts(str_repeat('ހ', 71)));
    }

    public function test_an_admin_previews_tests_and_sends_a_campaign_once(): void
    {
        $fan = $this->smsFan('7771111', ['name' => 'Aisha Ibrahim']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.sms.campaigns.store'), ['name' => 'Eid', 'message' => 'iruali: hi {name}, 20% off today.', 'segment' => 'category'])->assertSessionHasErrors('segment_value');
        $this->post(route('admin.sms.campaigns.store'), ['name' => 'Eid', 'message' => 'iruali: hi {name}, 20% off today.', 'segment' => 'all']);
        $campaign = SmsCampaign::sole();

        $this->get(route('admin.sms.campaigns.show', $campaign))->assertOk()->assertSee('data-campaign-recipients', false)->assertSee('<strong>1</strong>', false);
        $this->post(route('admin.sms.campaigns.test', $campaign))->assertSessionHas('success');
        $this->assertSame('+9607770001', SmsMessage::latest('id')->first()->to);

        $this->post(route('admin.sms.campaigns.send', $campaign), ['when' => 'now', 'confirm' => 1])->assertSessionHas('success');
        $this->artisan('sms:send-campaigns')->assertSuccessful();
        $this->artisan('sms:send-campaigns');

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $message = SmsMessage::where('to', '+9607771111')->sole();
        $this->assertStringContainsString('hi Aisha, 20% off today.', $message->message);
        $this->assertStringContainsString(SmsOptOut::url($fan), $message->message);
    }

    public function test_the_monthly_limit_and_scheduling(): void
    {
        $this->smsFan('7771111');
        $this->smsFan('7772222');
        Setting::set(['sms_monthly_limit' => 1]);
        $campaign = SmsCampaign::create(['name' => 'Big', 'message' => 'Hello', 'segment' => 'all', 'status' => 'draft']);

        $this->actingAs($this->admin())->post(route('admin.sms.campaigns.send', $campaign), ['when' => 'now', 'confirm' => 1])->assertSessionHas('error');

        Setting::set(['sms_monthly_limit' => 0]);
        $this->post(route('admin.sms.campaigns.send', $campaign), ['when' => 'later', 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'confirm' => 1])->assertSessionHas('success');
        $this->artisan('sms:send-campaigns');
        $this->assertSame('scheduled', $campaign->fresh()->status);
        $this->post(route('admin.sms.campaigns.cancel', $campaign))->assertSessionHas('success');
        $this->assertSame('cancelled', $campaign->fresh()->status);
    }

    public function test_the_stop_link_turns_offers_by_sms_off(): void
    {
        $fan = $this->smsFan('7771111');
        $this->get(SmsOptOut::url($fan))->assertOk()->assertSee('data-sms-stop', false);
        $this->assertSame('sms', $fan->fresh()->notificationPreference('marketing')); // a link preview stops nothing
        $this->post(route('sms.stop.store', SmsOptOut::code($fan)))->assertOk();
        $this->assertSame('email', $fan->fresh()->notificationPreference('marketing'));
        $this->get(route('sms.stop', ['code' => 'zz-00000000']))->assertNotFound();
    }

    public function test_cart_reminders_go_by_sms_when_switched_on(): void
    {
        $fan = $this->smsFan('7771111');
        $cart = Cart::factory()->create(['user_id' => $fan->id]);
        $reminder = new CartReminder($cart);
        $this->assertSame(['mail'], $reminder->via($fan));

        Setting::set(['abandoned_cart_sms_enabled' => 1]);
        $this->assertSame(['sms'], $reminder->via($fan));
        $this->assertStringContainsString(route('cart'), $reminder->toSms($fan)->text);

        $voucher = Voucher::factory()->create(['code' => 'BACK-ABC123', 'type' => 'percent', 'amount' => 10]);
        $this->assertStringContainsString('BACK-ABC123', (new CartReminder($cart, 2, $voucher))->toSms($fan)->text);
    }
}
