<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections on every response: no MIME sniffing (uploads can't run as HTML), no framing
 * by other sites, a tight referrer, no device APIs, HSTS once the site is served over HTTPS, and a
 * Content-Security-Policy that lets pages frame only this site and the product video players.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // geolocation=(self): "Use my location" in the delivery-island picker; never for embedded frames
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Frames stay limited to this site and the product video players. Scripts need a per-request
        // nonce; event-handler attributes stay allowed so existing confirm/print buttons keep working.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::contentSecurityPolicy($nonce));
            $this->stampScriptNonce($response, $nonce);
        }

        return $response;
    }

    /**
     * script-src allows this origin, the request nonce, and the analytics hosts.
     * script-src-attr 'unsafe-inline' keeps onclick/onsubmit handlers working.
     * frame-src is the video providers in App\Support\ProductVideo::FRAME_SOURCES.
     */
    public static function contentSecurityPolicy(?string $nonce = null): string
    {
        $nonce ??= Vite::cspNonce();
        $frameSrc = "frame-src 'self' ".implode(' ', \App\Support\ProductVideo::FRAME_SOURCES);

        return "script-src 'self' 'nonce-{$nonce}' https://plausible.io https://www.googletagmanager.com https://www.google-analytics.com; "
            ."script-src-attr 'unsafe-inline'; {$frameSrc}";
    }

    /**
     * Inline and JSON-LD script tags written in Blade do not know the nonce. Add it to any
     * script tag that does not already have one (Vite tags already do).
     */
    private function stampScriptNonce(Response $response, string $nonce): void
    {
        $type = (string) $response->headers->get('Content-Type', '');
        if ($type !== '' && ! str_contains($type, 'text/html') && ! str_contains($type, 'application/xhtml')) {
            return;
        }

        try {
            $content = $response->getContent();
        } catch (\Throwable) {
            return;
        }

        if (! is_string($content) || $content === '' || ! str_contains($content, '<script')) {
            return;
        }

        $escaped = htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $stamped = preg_replace_callback(
            '/<script\b([^>]*)>/i',
            function (array $match) use ($escaped): string {
                $attrs = $match[1];
                if (preg_match('/\bnonce=/i', $attrs)) {
                    return $match[0];
                }
                // JSON-LD and other data blocks are not executed, and CSP does not require a nonce on them.
                if (preg_match('/\btype=(["\'])([^"\']*)\1/i', $attrs, $type)
                    && ! in_array(strtolower($type[2]), ['text/javascript', 'application/javascript', 'module'], true)) {
                    return $match[0];
                }

                return '<script nonce="'.$escaped.'"'.$attrs.'>';
            },
            $content
        );

        if (is_string($stamped) && $stamped !== $content) {
            // setContent replaces Response::$original, which tests and the framework use to
            // read the view. Keep the rendered HTML and the original view.
            $original = $response->original ?? null;
            $response->setContent($stamped);
            if ($response instanceof \Illuminate\Http\Response) {
                $response->original = $original;
            }
        }
    }
}
