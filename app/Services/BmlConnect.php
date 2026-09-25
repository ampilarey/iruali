<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for BML Connect v2 (Bank of Maldives payment gateway), redirect method.
 *
 * Amounts are integers in laari (MVR 1.00 = 100). The payment result is never taken from
 * the redirect or webhook alone: callers re-fetch the transaction with getTransaction().
 */
class BmlConnect
{
    public function enabled(): bool
    {
        return filled(config('services.bml.api_key'));
    }

    public function isSandbox(): bool
    {
        return config('services.bml.environment') !== 'production';
    }

    /**
     * @return array{id: string, url: string, state: string}&array<string, mixed>
     */
    public function createTransaction(array $payload): array
    {
        $response = $this->http()->post('/v2/transactions', $payload);

        if (! $response->successful() || ! $response->json('id') || ! ($response->json('url') ?? $response->json('shortUrl'))) {
            throw new RuntimeException('BML Connect could not create the transaction (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 300));
        }

        $data = $response->json();
        $data['url'] = $data['url'] ?? $data['shortUrl'];

        return $data;
    }

    public function getTransaction(string $id): array
    {
        // Reads are safe to retry; creating a transaction is not.
        $response = $this->http()->retry(2, 300, throw: false)->get('/v2/transactions/'.rawurlencode($id));

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('BML Connect could not load transaction '.$id.' (HTTP '.$response->status().')');
        }

        return $response->json();
    }

    /**
     * Webhooks are signed with SHA-256 of nonce + timestamp + API key.
     */
    public function verifyWebhookSignature(?string $nonce, ?string $timestamp, ?string $signature): bool
    {
        if (! $this->enabled() || ! $nonce || ! $timestamp || ! $signature) {
            return false;
        }

        $expected = hash('sha256', $nonce.$timestamp.config('services.bml.api_key'));

        return hash_equals($expected, strtolower($signature));
    }

    public static function toLaari(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function http(): PendingRequest
    {
        if (! $this->enabled()) {
            throw new RuntimeException('BML Connect is not configured (BML_API_KEY is empty).');
        }

        return Http::baseUrl(rtrim(config('services.bml.base_uri'), '/'))
            ->withHeaders(['Authorization' => config('services.bml.api_key')])
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
