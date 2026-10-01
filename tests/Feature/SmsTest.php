<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The SMS layer: number normalisation, the log and HTTP drivers, the message log, the
 * notification channel and the admin page.
 */
class SmsTest extends TestCase
{
    use RefreshDatabase;

    protected function gateway(array $overrides = []): void
    {
        config(array_merge([
            'sms.driver' => 'http',
            'sms.sender_id' => 'iruali',
            'sms.http.url' => 'https://sms.test/send',
            'sms.http.method' => 'POST',
            'sms.http.auth_header' => 'Authorization: Bearer secret-token',
            'sms.http.body_template' => '{"to":"{to}","text":"{message}","from":"{sender}"}',
            'sms.http.success_regex' => '"status":\s*"(OK|queued)"',
        ], $overrides));
        app(SmsManager::class)->using(null);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_maldivian_numbers_are_normalised(): void
    {
        $this->assertSame('+9607771234', PhoneNumber::normalize('777 1234'));
        $this->assertSame('+9607771234', PhoneNumber::normalize('+960 777-1234'));
        $this->assertSame('+9609991234', PhoneNumber::normalize('009609991234'));
        $this->assertSame('+9609991234', PhoneNumber::normalize('9609991234'));
        $this->assertNull(PhoneNumber::normalize('3321234'), 'landlines are not mobiles');
        $this->assertNull(PhoneNumber::normalize('+44 7700 900123'));
        $this->assertNull(PhoneNumber::normalize('call-me'));
        $this->assertNull(PhoneNumber::normalize(null));
    }

    public function test_log_driver_records_the_message_without_sending(): void
    {
        config(['sms.driver' => 'log']);
        Http::fake();

        $result = app(SmsManager::class)->send('7771234', 'Your code is 123456');

        $this->assertTrue($result->success);
        $this->assertSame('logged', $result->status);
        $this->assertFalse(app(SmsManager::class)->isLive());
        Http::assertNothingSent();
        $this->assertDatabaseHas('sms_messages', ['to' => '+9607771234', 'message' => 'Your code is 123456', 'status' => 'logged']);
    }

    public function test_http_driver_posts_the_json_template_and_reads_success_from_the_regex(): void
    {
        $this->gateway();
        Http::fake(['sms.test/*' => Http::response(['status' => 'queued', 'id' => 'm1'], 200)]);

        $result = app(SmsManager::class)->send('777 1234', 'Order ORD-1 is on its way, "ރީތި"');

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.test/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['to'] === '+9607771234'
                && $request['text'] === 'Order ORD-1 is on its way, "ރީތި"'
                && $request['from'] === 'iruali';
        });
        $this->assertDatabaseHas('sms_messages', ['to' => '+9607771234', 'status' => 'sent']);
        $this->assertStringContainsString('queued', SmsMessage::sole()->provider_response);
    }

    public function test_http_driver_supports_get_with_a_query_template(): void
    {
        $this->gateway(['sms.http.method' => 'GET', 'sms.http.body_template' => 'dest={to}&msg={message}&src={sender}', 'sms.http.success_regex' => '']);
        Http::fake(['sms.test/*' => Http::response('OK 1', 200)]);

        $this->assertTrue(app(SmsManager::class)->send('9991234', 'Hello & welcome')->success);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'dest=%2B9609991234')
            && str_contains($request->url(), 'msg=Hello%20%26%20welcome')
            && str_contains($request->url(), 'src=iruali'));
    }

    public function test_a_rejected_or_unreachable_gateway_is_a_failure_and_never_throws(): void
    {
        $this->gateway();
        Http::fake(['sms.test/*' => Http::response(['status' => 'ERROR', 'reason' => 'insufficient credit'], 200)]);
        $result = app(SmsManager::class)->send('7771234', 'x');
        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('insufficient credit', $result->providerResponse);

        Http::fake(['sms.test/*' => Http::response('Unauthorized', 401)]);
        $this->assertFalse(app(SmsManager::class)->send('7771234', 'x')->success);

        Http::fake(['sms.test/*' => fn () => throw new ConnectionException('timed out')]);
        $result = app(SmsManager::class)->send('7771234', 'x');
        $this->assertFalse($result->success);
        $this->assertStringContainsString('timed out', $result->providerResponse);

        // A bad number never reaches the gateway
        Http::fake();
        $this->assertSame('invalid', app(SmsManager::class)->send('3321234', 'x')->status);
        Http::assertNothingSent();

        $this->assertSame(['failed', 'failed', 'failed', 'invalid'], SmsMessage::orderBy('id')->pluck('status')->all());
    }

    public function test_notifications_can_go_out_through_the_sms_channel(): void
    {
        $this->gateway();
        Http::fake(['sms.test/*' => Http::response(['status' => 'OK'], 200)]);
        $user = User::factory()->create(['phone' => '7770001', 'preferred_language' => 'dv']);

        $user->notify(new class extends Notification
        {
            public function via($notifiable): array
            {
                return ['sms'];
            }

            public function toSms($notifiable): string
            {
                return 'Hi '.$notifiable->name.' ('.app()->getLocale().')';
            }
        });

        Http::assertSent(fn ($request) => $request['to'] === '+9607770001' && $request['text'] === 'Hi '.$user->name.' (dv)');
        $this->assertDatabaseHas('sms_messages', ['to' => '+9607770001', 'status' => 'sent']);
    }

    public function test_admin_sees_the_log_and_can_send_a_test_message(): void
    {
        config(['sms.driver' => 'log']);
        app(SmsManager::class)->send('7771234', 'Earlier message');

        $this->actingAs(User::factory()->create())->get(route('admin.sms'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.sms'))->assertOk()
            ->assertSee('Earlier message')->assertSee('+9607771234')->assertSee('Send a test SMS')->assertSee('nothing is sent');

        $this->post(route('admin.sms.test'), ['to' => '3321234', 'message' => 'x'])->assertSessionHasErrors('to');
        $this->post(route('admin.sms.test'), ['to' => '999 1234', 'message' => 'Test from admin'])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('sms_messages', ['to' => '+9609991234', 'message' => 'Test from admin', 'status' => 'logged']);

        $this->gateway();
        Http::fake(['sms.test/*' => Http::response('nope', 500)]);
        $this->post(route('admin.sms.test'), ['to' => '999 1234', 'message' => 'Live test'])->assertSessionHas('error');
        $this->get(route('admin.sms'))->assertOk()->assertSee('failed');
    }
}
