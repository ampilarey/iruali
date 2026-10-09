<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Frames: this site (the admin's newsletter preview) and the product video players, nothing else
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::contentSecurityPolicy());
        }

        return $response;
    }

    /**
     * Only frames are restricted: the video providers in App\Support\ProductVideo::FRAME_SOURCES.
     * Their thumbnails are never loaded (the placeholder is drawn by the page), so img-src stays open.
     */
    public static function contentSecurityPolicy(): string
    {
        return "frame-src 'self' ".implode(' ', \App\Support\ProductVideo::FRAME_SOURCES);
    }
}
