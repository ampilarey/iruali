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

        // The Seller Centre: the shop's owner, or one of its staff for their role's pages (ShopAccess)
        if ($role === 'seller') {
            return app(ShopAccess::class)->handle($request, $next);
        }

        if (! auth()->user()->hasRole($role)) {
            abort(403, 'Access denied. '.ucfirst($role).' role required.');
        }

        return $next($request);
    }
}
