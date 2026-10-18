<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Posts an approved social post to each channel it is for: the Facebook page (Graph API feed or
 * photo), Instagram (needs a picture: media container, then publish) and a Telegram channel (the
 * team-alerts bot, made an admin of the channel). Never throws; each channel's result is kept.
 */
class SocialPublisher
{
    /**
     * Channels that are set up, key => label.
     *
     * @return array<string, string>
     */
    public static function configuredChannels(): array
    {
        $fb = config('services.facebook');

        return array_filter([
            'facebook' => filled($fb['page_id'] ?? null) && filled($fb['page_token'] ?? null) ? SocialPost::CHANNELS['facebook'] : null,
            'instagram' => filled($fb['instagram_account_id'] ?? null) && filled($fb['page_token'] ?? null) ? SocialPost::CHANNELS['instagram'] : null,
            'telegram' => Telegram::configured() && filled(Setting::get('telegram_channel_id')) ? SocialPost::CHANNELS['telegram'] : null,
        ]);
    }

    public function publish(SocialPost $post): void
    {
        $results = [];
        foreach ((array) $post->channels as $channel) {
            $results[$channel] = match ($channel) {
                'facebook' => $this->facebook($post),
                'instagram' => $this->instagram($post),
                'telegram' => $this->telegram($post),
                default => ['ok' => false, 'error' => 'Unknown channel'],
            };
        }
        $ok = $results !== [] && collect($results)->every(fn ($r) => $r['ok']);
        $post->forceFill(['results' => $results, 'status' => $ok ? 'posted' : 'failed', 'posted_at' => $ok ? now() : null])->save();
    }

    protected function text(SocialPost $post): string
    {
        return trim($post->message.($post->link ? "\n".$post->link : ''));
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    protected function facebook(SocialPost $post): array
    {
        $fb = config('services.facebook');

        return $this->graph($post->image_url
            ? ['/'.$fb['page_id'].'/photos', ['url' => $post->image_url, 'caption' => $this->text($post)]]
            : ['/'.$fb['page_id'].'/feed', ['message' => $post->message, 'link' => $post->link]]);
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    protected function instagram(SocialPost $post): array
    {
        if (! $post->image_url) {
            return ['ok' => false, 'error' => 'Instagram needs a picture'];
        }
        $account = config('services.facebook.instagram_account_id');
        $container = $this->graph(['/'.$account.'/media', ['image_url' => $post->image_url, 'caption' => $this->text($post)]]);
        if (! $container['ok']) {
            return $container;
        }

        return $this->graph(['/'.$account.'/media_publish', ['creation_id' => $container['id']]]);
    }

    /**
     * @param  array{0: string, 1: array<string, mixed>}  $call
     * @return array{ok: bool, error: ?string, id?: string}
     */
    protected function graph(array $call): array
    {
        [$path, $params] = $call;
        try {
            $response = Http::timeout(20)->asForm()->post('https://graph.facebook.com/'.config('services.facebook.graph_version', 'v21.0').$path,
                array_filter($params) + ['access_token' => config('services.facebook.page_token')]);
            if ($response->successful() && $response->json('id')) {
                return ['ok' => true, 'error' => null, 'id' => (string) $response->json('id')];
            }

            return ['ok' => false, 'error' => (string) ($response->json('error.message') ?? 'HTTP '.$response->status())];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    protected function telegram(SocialPost $post): array
    {
        return app(Telegram::class)->send((string) Setting::get('telegram_channel_id'), $this->text($post));
    }
}
