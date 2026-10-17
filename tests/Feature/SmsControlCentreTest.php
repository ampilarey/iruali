<?php

namespace Tests\Feature;

use App\Console\Commands\SmsReleaseHeldCommand;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\SmsTypeSetting;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin → SMS control centre: every message in one list (switch off, own wording in English and
 * Dhivehi), all SMS off, a monthly limit, quiet hours that hold messages until morning, and the log.
 */
class SmsControlCentreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sms.driver' => 'http', 'sms.http.url' => 'https://sms.test/send', 'sms.http.method' => 'POST',
            'sms.http.body_template' => '{"to":"{to}","text":"{message}"}', 'sms.http.success_regex' => '"status":\s*"OK"',
        ]);
        app(SmsManager::class)->using(null);
        Http::fake(fn () => $this->gatewayDown ? Http::response('down', 500) : Http::response(['status' => 'OK'], 200));
    }

    protected bool $gatewayDown = false;

    protected function staff(string $role): User
    {
        $user = User::factory()->create(['phone' => '7770009']);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    public function test_the_built_in_wording_is_unchanged_and_an_admin_can_reword_or_switch_off_a_message(): void
    {
        $data = ['number' => 'ORD-1', 'amount' => 'MVR 10.00'];
        $this->assertSame('iruali: we have your order ORD-1 (MVR 10.00). We will text you when it is on its way.', SmsTypes::render('order_placed', $data)->text);

        SmsTypeSetting::create(['key' => 'order_placed', 'enabled' => true, 'template_en' => 'Thanks! Order {number}, {amount}. {unknown}', 'template_dv' => 'ޝުކުރިއްޔާ {number}']);
        $this->assertSame('Thanks! Order ORD-1, MVR 10.00.', SmsTypes::render('order_placed', $data)->text, 'unknown placeholders are dropped');
        app()->setLocale('dv');
        $this->assertSame('ޝުކުރިއްޔާ ORD-1', SmsTypes::render('order_placed', $data)->text);
        app()->setLocale('en');

        // Tracking details are added after the text, or where {details} is written
        $this->assertSame('iruali: order ORD-1 is on its way. Boat, 24 Oct.', SmsTypes::render('order_shipped', ['number' => 'ORD-1', 'details' => 'Boat, 24 Oct.'])->text);
        SmsTypeSetting::create(['key' => 'order_shipped', 'enabled' => true, 'template_en' => 'On its way ({details}) - {number}']);
        $this->assertSame('On its way (Boat.) - ORD-1', SmsTypes::render('order_shipped', ['number' => 'ORD-1', 'details' => 'Boat.'])->text);

        // Switched off: nothing is sent
        SmsTypeSetting::where('key', 'order_placed')->first()->update(['enabled' => false]);
        $this->assertSame('', SmsTypes::render('order_placed', $data)->text);
        $order = \App\Models\Order::factory()->create(['order_number' => 'ORD-9']);
        \Illuminate\Support\Facades\Notification::route('sms', '7771234')->notify(new OrderPlaced($order));
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_messages_are_logged_with_their_type_and_the_switch_stops_everything(): void
    {
        $sms = app(SmsManager::class);
        $sms->send('7771234', SmsTypes::render('order_delivered', ['number' => 'ORD-1']));
        $this->assertDatabaseHas('sms_messages', ['type' => 'order_delivered', 'status' => 'sent']);

        Setting::set(['sms_enabled' => 0]);
        $result = $sms->send('7771234', SmsTypes::render('phone_code', ['code' => '123456']));
        $this->assertSame('blocked', $result->status);
        Http::assertSentCount(1);
    }

    public function test_the_monthly_limit_stops_updates_but_not_codes_and_tells_the_team_once(): void
    {
        Mail::fake();
        config(['mail.alerts_to' => 'ops@iruali.test']);
        Setting::set(['sms_monthly_limit' => 2]);
        $sms = app(SmsManager::class);

        $sms->send('7771234', SmsTypes::render('order_delivered', ['number' => 'A']));
        $sms->send('7771234', SmsTypes::render('order_delivered', ['number' => 'B']));
        $this->assertSame('blocked', $sms->send('7771234', SmsTypes::render('order_delivered', ['number' => 'C']))->status);
        $this->assertSame('blocked', $sms->send('7771234', SmsTypes::render('order_delivered', ['number' => 'D']))->status);
        $this->assertSame('sent', $sms->send('7771234', SmsTypes::render('phone_code', ['code' => '111111']))->status, 'codes still go');

        Mail::assertQueuedCount(1);
        Http::assertSentCount(3);
    }

    public function test_quiet_hours_hold_messages_until_morning(): void
    {
        Setting::set(['sms_quiet_from' => '22:00', 'sms_quiet_until' => '08:00']);
        $this->travelTo(Carbon::parse('2026-10-20 23:30'));
        $sms = app(SmsManager::class);

        $this->assertSame('held', $sms->send('7771234', SmsTypes::render('order_delivered', ['number' => 'A']))->status);
        $this->assertSame('sent', $sms->send('7771234', SmsTypes::render('phone_code', ['code' => '222222']))->status, 'codes go at night');
        $held = SmsMessage::where('status', 'held')->sole();
        $this->assertSame('2026-10-21 08:00', $held->send_after->format('Y-m-d H:i'));

        $this->artisan(SmsReleaseHeldCommand::class)->assertSuccessful();
        $this->assertSame('held', $held->fresh()->status, 'not yet');

        $this->travelTo(Carbon::parse('2026-10-21 08:01'));
        $this->artisan(SmsReleaseHeldCommand::class)->expectsOutputToContain('Released 1')->assertSuccessful();
        $this->assertSame('sent', $held->fresh()->status);
        Http::assertSentCount(2);

        // Daytime: straight out
        $this->assertNull(SmsManager::quietUntil(Carbon::parse('2026-10-21 12:00')));
    }

    public function test_admins_manage_it_and_support_only_reads_and_tests(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.sms'))->assertOk()
            ->assertSee('data-sms-types', false)->assertSee('Phone verification code')->assertSee('Order on its way')
            ->assertSee('{number}')->assertSee('iruali: we have your order ORD-7K2M9QX (MVR 450.00).');

        // Wording checks: only this message's placeholders, and the code must stay
        $this->put(route('admin.sms.types.update', 'phone_code'), ['enabled' => 1, 'template_en' => 'Your code'])->assertSessionHasErrors('template_en');
        $this->put(route('admin.sms.types.update', 'order_placed'), ['enabled' => 1, 'template_en' => 'Order {numbr}'])->assertSessionHasErrors('template_en');
        $this->put(route('admin.sms.types.update', 'phone_code'), ['enabled' => 0])->assertSessionHasErrors('enabled');
        $this->put(route('admin.sms.types.update', 'order_placed'), ['enabled' => 0, 'template_en' => 'Got order {number}'])->assertRedirect(route('admin.sms').'#sms-type-order_placed');
        $this->assertFalse(SmsTypeSetting::where('key', 'order_placed')->sole()->enabled);
        $this->assertTrue(\App\Models\AuditLog::where('action', 'sms.type_saved')->exists());

        $this->put(route('admin.sms.settings'), ['sms_enabled' => 1, 'sms_monthly_limit' => 500, 'sms_quiet_from' => '22:00', 'sms_quiet_until' => ''])->assertSessionHasErrors('sms_quiet_until');
        $this->put(route('admin.sms.settings'), ['sms_enabled' => 1, 'sms_monthly_limit' => 500, 'sms_quiet_from' => '22:00', 'sms_quiet_until' => '08:00'])->assertSessionHas('success');
        $this->assertSame(500, SmsManager::monthlyLimit());

        // Test a message with example values
        $this->post(route('admin.sms.types.test', 'order_delivered'), ['to' => '7771234', 'locale' => 'en'])->assertSessionHas('success');
        Http::assertSent(fn ($request) => $request['text'] === 'iruali: order ORD-7K2M9QX has been delivered. Enjoy!');

        // Support: sees the log and tests, but can't change switches or wording
        $support = $this->staff('support');
        $this->actingAs($support)->get(route('admin.sms'))->assertOk()->assertSee('Only admins can change these.');
        $this->put(route('admin.sms.settings'), ['sms_enabled' => 0, 'sms_monthly_limit' => 0])->assertForbidden();
        $this->put(route('admin.sms.types.update', 'order_delivered'), ['enabled' => 0])->assertForbidden();
        $this->assertTrue(SmsManager::enabled());
    }

    public function test_the_log_filters_exports_and_resends(): void
    {
        $admin = $this->staff('admin');
        $sms = app(SmsManager::class);
        $sms->send('7771234', SmsTypes::render('order_delivered', ['number' => 'ORD-OK']));
        $this->gatewayDown = true;
        $sms->send('9991234', SmsTypes::render('order_cancelled', ['number' => 'ORD-FAIL']));
        $failed = SmsMessage::where('status', 'failed')->sole();

        $this->actingAs($admin)->get(route('admin.sms', ['status' => 'failed']))->assertOk()->assertSee('ORD-FAIL')->assertDontSee('ORD-OK');
        $this->get(route('admin.sms', ['q' => '777 1234']))->assertOk()->assertSee('ORD-OK')->assertDontSee('ORD-FAIL');

        $csv = $this->get(route('admin.sms.export', ['type' => 'order_cancelled']))->assertOk()->streamedContent();
        $this->assertStringContainsString('ORD-FAIL', $csv);
        $this->assertStringNotContainsString('ORD-OK', $csv);

        $this->gatewayDown = false;
        $this->post(route('admin.sms.resend', $failed))->assertSessionHas('success');
        $this->assertSame(2, SmsMessage::where('message', 'like', '%ORD-FAIL%')->count());
        $this->assertTrue(SmsMessage::where('message', 'like', '%ORD-FAIL%')->where('status', 'sent')->exists());
    }

    public function test_sms_text_is_still_a_string_for_existing_callers(): void
    {
        $text = new SmsText('order_delivered', 'Hello');
        $this->assertSame('Hello', (string) $text);
        $this->assertStringContainsString('Hel', (string) $text);
    }
}
