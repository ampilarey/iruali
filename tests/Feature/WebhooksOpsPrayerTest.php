<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\OrderNotifier;
use App\Support\PrayerTimes;
use App\Support\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Webhooks out, the ready check and ops alerts, and prayer times.
 */
class WebhooksOpsPrayerTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_order_events_are_signed_and_sent_to_endpoints_that_want_them(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok')]);
        $this->actingAs($this->admin())->post(route('admin.webhooks.store'), ['url' => 'http://insecure.example.com', 'events' => ['order.paid']])->assertSessionHasErrors('url');
        $this->post(route('admin.webhooks.store'), ['url' => 'https://hooks.example.com/iruali', 'events' => ['order.paid', 'order.delivered']])->assertSessionHas('success');
        $endpoint = WebhookEndpoint::sole();

        $order = Order::factory()->create(['payment_status' => 'paid', 'status' => 'processing']);
        Webhooks::order('order.created', $order); // not wanted
        Http::assertNothingSent();

        app(OrderNotifier::class)->paymentUpdated($order);
        Http::assertSent(function ($request) use ($endpoint, $order) {
            $body = $request->body();

            return $request->url() === 'https://hooks.example.com/iruali'
                && $request->header('X-Iruali-Event')[0] === 'order.paid'
                && $request->header('X-Iruali-Signature')[0] === Webhooks::sign($body, $endpoint->secret)
                && json_decode($body, true)['order']['number'] === $order->order_number
                && ! str_contains($body, (string) $order->user?->email);
        });
        $this->assertSame(200, $endpoint->fresh()->last_status);

        $this->post(route('admin.webhooks.test', $endpoint))->assertSessionHas('success');
    }

    public function test_a_failing_endpoint_is_retried_later(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);
        $endpoint = WebhookEndpoint::create(['url' => 'https://hooks.example.com/x', 'secret' => 'abc', 'events' => ['order.created'], 'is_active' => true]);
        $job = (new SendWebhook($endpoint->id, ['event' => 'order.created']))->withFakeQueueInteractions();
        $job->handle();
        $job->assertReleased();
        $this->assertSame('HTTP 500', $endpoint->fresh()->last_error);
    }

    public function test_the_ready_check_answers_and_ops_watch_alerts_once(): void
    {
        $this->getJson('/api/health/ready')->assertOk()->assertJson(['status' => 'ready', 'checks' => ['database' => true, 'cache' => true, 'storage' => true]]);
        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'healthy']); // unchanged for the deploy check

        Mail::fake();
        config(['mail.alerts_to' => 'owner@iruali.mv', 'queue.default' => 'database']);
        DB::table('failed_jobs')->insert(['uuid' => 'u1', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\SendWebhook']), 'exception' => 'boom', 'failed_at' => now()]);
        Cache::forget('queue.heartbeat');

        $this->artisan('ops:watch')->expectsOutputToContain('1 queued job(s) failed: SendWebhook')->assertSuccessful();
        $this->artisan('ops:watch')->expectsOutputToContain('queue worker has not run'); // still stopped, but no second email this hour

        (new \App\Jobs\QueueHeartbeat)->handle();
        $this->artisan('ops:watch')->expectsOutputToContain('All good.');
    }

    public function test_prayer_times_for_an_island(): void
    {
        $times = PrayerTimes::calculate(4.1755, 73.5093, Carbon::parse('2026-10-18'));
        $this->assertMatchesRegularExpression('/^04:[3-5]\d$/', $times['fajr']);
        $this->assertMatchesRegularExpression('/^1[12]:\d\d$/', $times['dhuhr']);
        $this->assertMatchesRegularExpression('/^17:[45]\d$/', $times['maghrib']);
        $this->assertTrue($times['fajr'] < $times['sunrise'] && $times['sunrise'] < $times['dhuhr'] && $times['dhuhr'] < $times['asr'] && $times['asr'] < $times['maghrib'] && $times['maghrib'] < $times['isha']);
        $this->assertSame(['fajr', $times['fajr']], PrayerTimes::next($times, Carbon::parse('2026-10-18 21:00', 'Indian/Maldives')));

        $this->get(route('home'))->assertDontSee('data-prayer-times', false);
        Setting::set(['prayer_times_enabled' => 1]);
        $this->get(route('home'))->assertSee('data-prayer-times', false)->assertSee('Malé');
    }
}
