<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\TeamAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Admin → Team alerts: events posted to a Telegram group and texted to the staff who chose them.
 */
class TeamAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'log', 'services.telegram.bot_token' => '123:abc']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
            ['message' => ['chat' => ['id' => -100555, 'title' => 'iruali team']]],
        ]], 200)]);
    }

    protected function staff(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(\Illuminate\Support\Arr::except($attributes, ['team_alerts']));
        $user->forceFill(['team_alerts' => $attributes['team_alerts'] ?? null])->save();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    public function test_an_event_goes_to_the_group_and_to_staff_who_chose_it(): void
    {
        Setting::set(['telegram_chat_id' => '-100555', 'telegram_events' => 'new_order,refund_due']);
        $owner = $this->staff('admin', ['phone' => '7770001', 'team_alerts' => ['new_order']]);
        $this->staff('support', ['phone' => '7770002', 'team_alerts' => ['refund_due']]);
        $this->staff('finance', ['phone' => null, 'team_alerts' => ['new_order']]); // no phone

        TeamAlerts::send('new_order', 'iruali: new order ORD-1.', 'https://iruali.test/admin/orders/1');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/bot123:abc/sendMessage') && $r['chat_id'] === '-100555' && $r['text'] === "iruali: new order ORD-1.\nhttps://iruali.test/admin/orders/1");
        $this->assertSame(['+9607770001'], SmsMessage::where('type', 'team_alert')->pluck('to')->all());

        // An event the group does not take, nobody chose: nothing at all
        Http::fake();
        TeamAlerts::send('backup_failed', 'x');
        Http::assertNothingSent();
        $this->assertSame(1, SmsMessage::count());
        $this->assertNotNull($owner);
    }

    public function test_a_new_shop_application_raises_an_alert(): void
    {
        Setting::set(['telegram_chat_id' => '-100555', 'telegram_events' => 'shop_application']);
        $island = \App\Models\Island::firstOrCreate(['name' => 'Hulhumalé'], ['atoll' => 'Kaafu', 'is_active' => true]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)->post(route('seller.apply.store'), [
            'business_name' => 'Reef Goods', 'business_description' => 'Fishing gear and rope for island boats.',
            'phone' => '7779999', 'address' => 'H. Blue', 'agree_seller_terms' => 1,
            'home_island_id' => $island->id, 'delivery_coverage' => User::COVERAGE_ALL,
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => str_contains((string) ($r['text'] ?? ''), 'new shop application: Reef Goods'));
    }

    public function test_admins_choose_the_alerts_and_send_tests(): void
    {
        $admin = $this->staff('admin', ['phone' => '7770001']);
        $support = $this->staff('support', ['phone' => '7770002']);

        $this->actingAs($admin)->get(route('admin.alerts'))->assertOk()->assertSee('Telegram group')->assertSee('Find my group')->assertSee($support->name);

        $this->post(route('admin.alerts.find-chats'))->assertSessionHas('telegram_chats', [['id' => '-100555', 'title' => 'iruali team']]);
        $this->put(route('admin.alerts.update'), ['telegram_chat_id' => 'my group'])->assertSessionHasErrors('telegram_chat_id');
        $this->put(route('admin.alerts.update'), [
            'telegram_chat_id' => '-100555', 'telegram_events' => ['new_order', 'bank_slip'],
            'sms' => [$support->id => ['refund_due', 'dispute_opened']],
        ])->assertRedirect(route('admin.alerts'));

        $this->assertSame(['new_order', 'bank_slip'], TeamAlerts::telegramEvents());
        $this->assertSame(['refund_due', 'dispute_opened'], $support->fresh()->team_alerts);
        $this->assertSame([], $admin->fresh()->team_alerts);

        $this->post(route('admin.alerts.test'), ['to' => 'telegram'])->assertSessionHas('success');
        $this->post(route('admin.alerts.test'), ['to' => (string) $support->id])->assertSessionHas('success');
        $this->assertTrue(SmsMessage::where('to', '+9607770002')->where('type', 'team_alert')->exists());

        // Only admins
        $this->actingAs($support)->get(route('admin.alerts'))->assertForbidden();
    }
}
