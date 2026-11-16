<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the service worker (public/sw.js) may keep for offline use. A page made for a signed-in
 * person (their name, messages, the form token) is marked X-Iruali-Private and never kept; the page
 * right after signing out carries X-Iruali-Clear-Pages, and every kept page is dropped.
 * In the web group, after the session starts: the "just signed out" flash is read before it is saved.
 */
class OfflineCopies
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()) {
            $response->headers->set('X-Iruali-Private', '1');
        }
        if ($request->hasSession() && $request->session()->get('iruali.signed_out')) {
            $response->headers->set('X-Iruali-Clear-Pages', '1');
        }

        return $response;
    }
}
