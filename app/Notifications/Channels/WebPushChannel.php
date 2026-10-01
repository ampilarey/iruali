<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Browser push notifications. A notification opts in by listing this channel in via() and giving
 * a toWebPush($notifiable) array: title, body, url (opened on tap) and an optional icon. Every
 * subscription of the user is tried; ones the push service reports gone (404/410) are deleted.
 */
class WebPushChannel
{
    public static function configured(): bool
    {
        return (string) config('webpush.public_key') !== '' && (string) config('webpush.private_key') !== '';
    }

    /**
     * The web-push client with this site's VAPID keys (bound in AppServiceProvider so tests can swap it).
     */
    public static function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ], ['TTL' => 86400]);
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! self::configured() || ! method_exists($notification, 'toWebPush') || ! method_exists($notifiable, 'pushSubscriptions')) {
            return;
        }

        $subscriptions = $notifiable->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $message = $notification->toWebPush($notifiable);
        $payload = json_encode([
            'title' => (string) ($message['title'] ?? config('app.name')),
            'body' => (string) ($message['body'] ?? ''),
            'url' => (string) ($message['url'] ?? url('/')),
            'icon' => (string) ($message['icon'] ?? asset('images/icons/icon-192.png')),
            'tag' => (string) ($message['tag'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $client = app(WebPush::class);

        foreach ($subscriptions as $subscription) {
            try {
                $report = $client->sendOneNotification($subscription->toWebPushSubscription(), $payload);
                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                } elseif (! $report->isSuccess()) {
                    logger()->warning('Web push failed: '.$report->getReason(), ['endpoint' => $subscription->endpoint]);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Remove a subscription by endpoint (the browser told us it is gone).
     */
    public static function forget(string $endpoint): void
    {
        PushSubscription::where('endpoint', $endpoint)->delete();
    }
}
