<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must have two-step sign-in on before they can use /admin. Anyone without it is sent
 * to the setup page (the 2FA and logout routes themselves are always allowed).
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('staff.require_two_factor', true) || ! $user || ! $user->isStaff() || $user->isTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->routeIs('profile.2fa.*', 'logout', '2fa.*')) {
            return $next($request);
        }

        return redirect()->route('profile.2fa.setup')
            ->with('warning', __('Staff accounts must use two-step sign-in. Set it up here before opening the admin area.'));
    }
}
