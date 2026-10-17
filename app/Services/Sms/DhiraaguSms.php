<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Dhiraagu Bulk SMS (messaging.dhiraagu.com.mv), set up with just the portal's username and
 * password (SMS_DRIVER=dhiraagu). The same call Akuru and Bake & Grill make: the number without the
 * plus (9607771234), the text, and authorizationKey = base64("username:password"). Dhiraagu says
 * yes with transactionStatus "true" (or true), or only a transactionId.
 */
class DhiraaguSms implements SmsDriver
{
    public const DEFAULT_URL = 'https://messaging.dhiraagu.com.mv/v1/api/sms';

    public function __construct(protected array $config) {}

    public function send(string $to, string $message, string $sender): SmsResult
    {
        $username = trim((string) ($this->config['username'] ?? ''));
        $password = (string) ($this->config['password'] ?? '');
        if ($username === '' || $password === '') {
            return SmsResult::failed('SMS_DHIRAAGU_USERNAME / SMS_DHIRAAGU_PASSWORD are not set');
        }

        $body = [
            'destination' => [ltrim($to, '+')],
            'content' => $message,
            'authorizationKey' => base64_encode($username.':'.$password),
        ];
        if (! empty($this->config['use_sender']) && $sender !== '') {
            $body['source'] = $sender; // only once Dhiraagu has approved the sender name
        }

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 15))->acceptJson()
                ->post((string) ($this->config['url'] ?: self::DEFAULT_URL), $body);
        } catch (Throwable $e) {
            return SmsResult::failed(mb_substr(get_class($e).': '.$e->getMessage(), 0, 1000));
        }

        $reply = mb_substr((string) $response->body(), 0, 2000);
        $data = (array) ($response->json() ?? []);

        return $response->successful() && $this->accepted($data)
            ? SmsResult::sent($reply)
            : SmsResult::failed(($response->successful() ? '' : 'HTTP '.$response->status().': ').$reply);
    }

    /**
     * Dhiraagu has answered with "true", true and "True"; some replies only carry a transactionId.
     *
     * @param  array<string, mixed>  $data
     */
    protected function accepted(array $data): bool
    {
        $status = $data['transactionStatus'] ?? $data['transaction_status'] ?? null;
        if (in_array($status, [true, 'true', 'True', 1, '1'], true)) {
            return true;
        }

        return $status === null && ! empty($data['transactionId']);
    }
}
