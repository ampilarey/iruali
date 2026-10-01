<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->hasRole($role)) {
            abort(403, 'Access denied. '.ucfirst($role).' role required.');
        }

        // A pending shop may look around (it sees "awaiting approval"); a suspended one may not.
        // (Rejection removes the seller role, so the role check above already covers it.)
        if ($role === 'seller' && auth()->user()->status === 'suspended') {
            abort(403, 'Your shop is not active.');
        }

        return $next($request);
    }
}
