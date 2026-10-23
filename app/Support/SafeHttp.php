<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetching a page or a picture from an address someone typed (ProductImportService), without letting
 * it reach anything but the public internet: http(s) on the usual ports only, no user:password in the
 * address, and every address the host name resolves to must be a public one (not this server, the
 * hosting company's network or a private range). The connection is pinned to the address that was
 * checked, and redirects are followed by hand (at most MAX_REDIRECTS), each checked the same way.
 */
final class SafeHttp
{
    public const MAX_REDIRECTS = 3;

    /** @var (Closure(string): list<string>)|null  host name → its addresses (tests swap it) */
    protected static ?Closure $resolver = null;

    /**
     * Resolve host names another way (tests), or null for the real DNS.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * GET the address: its body, content type (lower case, without parameters) and the address it
     * ended at, or null when it is not allowed, can't be reached, fails, or is larger than $maxBytes.
     *
     * @return array{body: string, type: string, url: string}|null
     */
    public static function get(string $url, int $maxBytes, int $timeout = 10): ?array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = self::target($url);
            if ($target === null) {
                return null;
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; iruali-product-import/1.0; +https://iruali.mv)',
                    'Accept' => 'text/html,application/xhtml+xml,application/json,image/*;q=0.9,*/*;q=0.8',
                ])->withOptions([
                    'allow_redirects' => false,
                    // Connect to the address that was checked, not whatever the name resolves to a moment later
                    'curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.(str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'])]],
                    'on_headers' => function ($response) use ($maxBytes) {
                        if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                            throw new \RuntimeException('Too large');
                        }
                    },
                ])->connectTimeout(5)->timeout($timeout)->get($url);
            } catch (Throwable) {
                return null;
            }

            if ($response->redirect()) {
                $location = trim((string) $response->header('Location'));
                if ($location === '') {
                    return null;
                }
                $url = self::absolute($url, $location);

                continue;
            }
            $body = $response->body();
            if (! $response->successful() || strlen($body) > $maxBytes) {
                return null;
            }

            return ['body' => $body, 'type' => strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0])), 'url' => $url];
        }

        return null;
    }

    /**
     * May this address be fetched: its host, port and the public address to connect to; null when not.
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    public static function target(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        // Names that only mean something inside a network, whatever they resolve to
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|lan|home\.arpa)$/', $host) || ! str_contains($host, '.') && ! str_contains($host, ':')) {
            return null;
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if ($addresses === []) {
            return null;
        }
        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                return null; // one private answer is enough to refuse (no DNS games)
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    public static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    /** An address in a page (relative, scheme-relative or absolute) as an absolute one. */
    public static function absolute(string $base, string $ref): string
    {
        $ref = trim(html_entity_decode($ref, ENT_QUOTES | ENT_HTML5));
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($ref, '//')) {
            return $scheme.':'.$ref;
        }
        if (str_starts_with($ref, '/')) {
            return $origin.$ref;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/') ?: '/';

        return $origin.$dir.$ref;
    }

    /**
     * @return list<string>
     */
    protected static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return array_values((self::$resolver)($host));
        }

        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
