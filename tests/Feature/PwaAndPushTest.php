<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PaymentUpdated;
use App\Notifications\SellerOrderShipped;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

/**
 * Installable app: manifest, service worker, offline page, push subscriptions and the webpush channel.
 */
class PwaAndPushTest extends TestCase
{
    use RefreshDatabase;

    protected function withVapid(): void
    {
        config(['webpush.public_key' => 'BPublicKeyForTests', 'webpush.private_key' => 'PrivateKeyForTests', 'webpush.subject' => 'mailto:test@iruali.mv']);
    }

    public function test_manifest_is_valid_and_its_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());

        $this->assertSame('iruali', $manifest['name']);
        $this->assertSame('iruali', $manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $manifest['theme_color']);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $manifest['background_color']);

        $sizes = array_column(array_filter($manifest['icons'], fn ($i) => $i['type'] === 'image/png' && $i['purpose'] === 'any'), 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), $icon['src'].' is missing');
        }
        foreach ($manifest['shortcuts'] as $shortcut) {
            $this->assertFileExists(public_path(ltrim($shortcut['icons'][0]['src'], '/')));
        }

        $this->get('/')->assertOk()->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
    }

    public function test_service_worker_handles_fetch_push_and_never_caches_private_pages(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        foreach (["addEventListener('install'", "addEventListener('fetch'", "addEventListener('push'", "addEventListener('notificationclick'", "'/offline'", 'showNotification', 'openWindow'] as $needle) {
            $this->assertStringContainsString($needle, $sw);
        }
        preg_match('/const PRIVATE = (\/.+\/);/', $sw, $m);
        $this->assertNotEmpty($m, 'The private-path pattern is missing');
        foreach (['/admin/dashboard', '/seller/orders', '/account', '/checkout', '/api/v1/cart', '/dv/account/edit', '/orders/5', '/cart'] as $path) {
            $this->assertSame(1, preg_match($m[1], $path), $path.' should be private');
        }
        foreach (['/', '/products/x', '/dv/products/x', '/deals', '/build/assets/app.js', '/images/icons/icon-192.png'] as $path) {
            $this->assertSame(0, preg_match($m[1], $path), $path.' should be cacheable');
        }
    }

    public function test_offline_page_is_translated_and_noindex(): void
    {
        $this->get('/offline')->assertOk()->assertSee('You are offline')->assertSee('content="noindex, nofollow"', false);
        $this->withSession(['locale' => 'dv'])->get('/offline')->assertOk()->assertSee('dir="rtl"', false)->assertSee('އޮފްލައިން');
    }

    public function test_account_page_offers_push_only_when_vapid_is_configured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')->assertOk()->assertDontSee('data-push', false);

        $this->withVapid();
        $this->actingAs($user)->get('/account')->assertOk()
            ->assertSee('Get order updates on this device')
            ->assertSee('data-key="BPublicKeyForTests"', false)
            ->assertSee(route('account.push.store'), false);
    }

    public function test_subscription_store_and_destroy(): void
    {
        $user = User::factory()->create();
        $payload = ['endpoint' => 'https://push.example.com/send/abc', 'keys' => ['p256dh' => 'p256', 'auth' => 'authkey'], 'content_encoding' => 'aes128gcm'];

        $this->postJson('/account/push', $payload)->assertUnauthorized();

        $this->actingAs($user)->postJson('/account/push', ['endpoint' => 'http://insecure.example.com/x', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])
            ->assertUnprocessable()->assertJsonValidationErrors('endpoint');
        $this->actingAs($user)->postJson('/account/push', ['endpoint' => 'https://push.example.com/send/abc'])
            ->assertUnprocessable()->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);

        $this->actingAs($user)->postJson('/account/push', $payload)->assertCreated()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => $payload['endpoint'], 'public_key' => 'p256', 'auth_token' => 'authkey', 'content_encoding' => 'aes128gcm']);

        // The same browser subscribing again (or under another account) updates the one row
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/account/push', array_merge($payload, ['keys' => ['p256dh' => 'new', 'auth' => 'new-auth']]))->assertCreated();
        $this->assertSame(1, PushSubscription::count());
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $other->id, 'public_key' => 'new']);

        // Only the owner can remove it
        $this->actingAs($user)->deleteJson('/account/push', ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->actingAs($other)->deleteJson('/account/push', ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_channel_sends_the_payload_and_drops_gone_subscriptions(): void
    {
        $this->withVapid();
        $customer = User::factory()->create(['name' => 'Aminath']);
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'shipped', 'order_number' => 'ORD-777']);
        $live = PushSubscription::create(['user_id' => $customer->id, 'endpoint' => 'https://push.example.com/live', 'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm']);
        $gone = PushSubscription::create(['user_id' => $customer->id, 'endpoint' => 'https://push.example.com/gone', 'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm']);

        $sent = [];
        $client = $this->mock(WebPush::class);
        $client->shouldReceive('sendOneNotification')->twice()->andReturnUsing(function (SubscriptionInterface $subscription, string $payload) use (&$sent) {
            $sent[$subscription->getEndpoint()] = json_decode($payload, true);
            $request = new PsrRequest('POST', $subscription->getEndpoint());

            return str_ends_with($subscription->getEndpoint(), '/gone')
                ? new MessageSentReport($request, new PsrResponse(410), false, 'Gone')
                : new MessageSentReport($request, new PsrResponse(201), true);
        });

        $notification = new OrderStatusChanged($order);
        $this->assertContains(WebPushChannel::class, $notification->via($customer));
        $customer->notify($notification);

        $this->assertSame('Order ORD-777 is on its way', $sent['https://push.example.com/live']['title']);
        $this->assertSame(route('orders.show', $order), $sent['https://push.example.com/live']['url']);
        $this->assertSame('Tap to see your order.', $sent['https://push.example.com/live']['body']);
        $this->assertStringEndsWith('/images/icons/icon-192.png', $sent['https://push.example.com/live']['icon']);
        $this->assertSame('order-'.$order->id, $sent['https://push.example.com/live']['tag']);

        $this->assertDatabaseHas('push_subscriptions', ['id' => $live->id]);
        $this->assertDatabaseMissing('push_subscriptions', ['id' => $gone->id]);
    }

    public function test_payment_and_shipped_part_payloads_and_channel_is_off_without_keys(): void
    {
        $customer = User::factory()->create();
        $shop = User::factory()->create(['business_name' => 'Reefline']);
        $order = Order::factory()->create(['user_id' => $customer->id, 'order_number' => 'ORD-900', 'total_amount' => 250]);
        $part = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $shop->id, 'status' => 'shipped', 'tracking_note' => 'MTCC boat Tuesday', 'subtotal' => 250, 'commission_rate' => 10, 'commission_amount' => 25, 'seller_amount' => 225]);

        $this->assertSame(['mail'], (new PaymentUpdated($order))->via($customer));
        $this->assertSame(['mail'], (new SellerOrderShipped($part))->via($customer));

        $this->withVapid();
        $payment = (new PaymentUpdated($order))->toWebPush($customer);
        $this->assertSame('Payment received for order ORD-900', $payment['title']);
        $this->assertStringContainsString('250.00', $payment['body']);
        $this->assertSame(route('orders.show', $order), $payment['url']);

        $shipped = (new SellerOrderShipped($part))->toWebPush($customer);
        $this->assertSame('Items from Reefline are on their way (order ORD-900)', $shipped['title']);
        $this->assertSame('Tracking: MTCC boat Tuesday', $shipped['body']);
        $this->assertContains(WebPushChannel::class, (new SellerOrderShipped($part))->via($customer));

        // Nothing happens (and nothing is sent) for a user without subscriptions
        $this->mock(WebPush::class)->shouldNotReceive('sendOneNotification');
        $customer->notify(new PaymentUpdated($order));
    }

    public function test_vapid_command_writes_keys_to_an_env_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "APP_NAME=iruali\nVAPID_SUBJECT=mailto:old@example.com\n");

        $this->artisan('push:vapid', ['--file' => $file, '--subject' => 'mailto:hello@iruali.mv'])->assertSuccessful();

        $env = file_get_contents($file);
        $this->assertMatchesRegularExpression('/^VAPID_PUBLIC_KEY=[A-Za-z0-9_-]{80,}$/m', $env);
        $this->assertMatchesRegularExpression('/^VAPID_PRIVATE_KEY=[A-Za-z0-9_-]{40,}$/m', $env);
        $this->assertStringContainsString("VAPID_SUBJECT=mailto:hello@iruali.mv\n", $env);
        $this->assertStringNotContainsString('old@example.com', $env);
        $this->assertSame(1, substr_count($env, 'VAPID_SUBJECT='));

        // A second run refuses to overwrite unless forced
        $before = $env;
        $this->artisan('push:vapid', ['--file' => $file])->assertFailed();
        $this->assertSame($before, file_get_contents($file));
        $this->artisan('push:vapid', ['--file' => $file, '--force' => true])->assertSuccessful();
        $this->assertNotSame($before, file_get_contents($file));

        unlink($file);
    }
}
