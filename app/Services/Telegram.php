<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Posts messages to a Telegram chat through a bot (TELEGRAM_BOT_TOKEN from @BotFather), for team
 * alerts. The bot must be a member of the group. Never throws.
 */
class Telegram
{
    public static function configured(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function send(string $chatId, string $text): array
    {
        if (! self::configured() || trim($chatId) === '') {
            return ['ok' => false, 'error' => 'Telegram is not set up'];
        }

        try {
            $response = Http::timeout(10)->acceptJson()->post($this->url('sendMessage'), [
                'chat_id' => trim($chatId),
                'text' => mb_substr($text, 0, 4000),
                'disable_web_page_preview' => true,
            ]);
            $ok = $response->successful() && ($response->json('ok') === true);

            return ['ok' => $ok, 'error' => $ok ? null : (string) ($response->json('description') ?? 'HTTP '.$response->status())];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /**
     * Chats that sent the bot something lately (for finding the group's chat id).
     *
     * @return list<array{id: string, title: string}>
     */
    public function recentChats(): array
    {
        if (! self::configured()) {
            return [];
        }

        try {
            $updates = (array) Http::timeout(10)->acceptJson()->get($this->url('getUpdates'))->json('result');
        } catch (Throwable) {
            return [];
        }

        $chats = [];
        foreach ($updates as $update) {
            $chat = $update['message']['chat'] ?? $update['my_chat_member']['chat'] ?? $update['channel_post']['chat'] ?? null;
            if (is_array($chat) && isset($chat['id'])) {
                $chats[(string) $chat['id']] = ['id' => (string) $chat['id'], 'title' => (string) ($chat['title'] ?? trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? '')) ?: $chat['id'])];
            }
        }

        return array_values($chats);
    }

    protected function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method;
    }
}
