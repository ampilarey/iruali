<?php

namespace App\Support;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Real HTTP through Laravel's client: one cookie jar for the whole run (session, CSRF, cart),
 * redirects are not followed so each check can look at the Location header itself.
 */
class HttpSmokeFetcher implements SmokeFetcher
{
    private CookieJar $jar;

    public function __construct(private int $timeout = 20)
    {
        $this->jar = new CookieJar;
    }

    public function request(string $method, string $url, array $data = []): Response
    {
        $client = $this->client();

        return strtoupper($method) === 'POST'
            ? $client->asForm()->post($url, $data)
            : $client->get($url, $data);
    }

    protected function client(): PendingRequest
    {
        return Http::withOptions(['cookies' => $this->jar, 'allow_redirects' => false])
            ->timeout($this->timeout)
            ->withHeaders([
                'User-Agent' => 'Iruali-Smoke/1.0 (+php artisan iruali:smoke)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml,application/json;q=0.9,*/*;q=0.8',
            ]);
    }
}
