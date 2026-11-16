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
}
