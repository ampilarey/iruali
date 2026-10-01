<?php

namespace Tests\Support;

use App\Support\SmokeFetcher;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Answers SmokeChecks from a map of "METHOD /path" => [status, body, headers]; unknown routes 404.
 */
class FakeSmokeFetcher implements SmokeFetcher
{
    /** @var list<array{method: string, url: string, data: array}> */
    public array $requests = [];

    /**
     * @param  array<string, array{0: int, 1?: string, 2?: array<string, string>}|\Closure>  $routes
     */
    public function __construct(private array $routes = [], public string $base = 'http://smoke.test') {}

    public function on(string $route, int $status, string $body = '', array $headers = []): static
    {
        $this->routes[$route] = [$status, $body, $headers];

        return $this;
    }

    /**
     * Make this route throw a connection error (server down, DNS, timeout).
     */
    public function failing(string $route): static
    {
        $this->routes[$route] = 'connection-error';

        return $this;
    }

    public function request(string $method, string $url, array $data = []): Response
    {
        $this->requests[] = ['method' => strtoupper($method), 'url' => $url, 'data' => $data];

        $path = str_starts_with($url, $this->base) ? substr($url, strlen($this->base)) : $url;
        $key = strtoupper($method).' '.$path;
        $route = $this->routes[$key] ?? $this->routes[strtoupper($method).' '.strtok($path, '?')] ?? null;

        if ($route instanceof \Closure) {
            $route = $route($data, $this);
        }
        if ($route === 'connection-error') {
            throw new ConnectionException('cURL error 7: Failed to connect');
        }
        if ($route === null) {
            return $this->make(404, 'Not Found');
        }

        return $this->make($route[0], $route[1] ?? '', $route[2] ?? []);
    }

    public function make(int $status, string $body = '', array $headers = []): Response
    {
        return new Response(new PsrResponse($status, $headers, $body));
    }

    public function sent(string $method, string $path): ?array
    {
        foreach ($this->requests as $r) {
            if ($r['method'] === strtoupper($method) && ($r['url'] === $this->base.$path || $r['url'] === $path)) {
                return $r;
            }
        }

        return null;
    }
}
