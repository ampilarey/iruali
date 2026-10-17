<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must have two-step sign-in on before they can use /admin. Anyone without it is sent
 * to the setup page (the 2FA and logout routes themselves are always allowed).
 *
 * Admins can make it optional in Admin → Settings; STAFF_REQUIRE_2FA=false turns it off for a
 * whole install (a dev machine). Staff who already use it keep being asked for their code.
 */
class RequireTwoFactor
{
    public static function required(): bool
    {
        return config('staff.require_two_factor', true)
            && filter_var(\App\Models\Setting::get('staff_require_two_factor', 1), FILTER_VALIDATE_BOOLEAN);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! self::required() || ! $user || ! $user->isStaff() || $user->isTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->routeIs('profile.2fa.*', 'logout', '2fa.*')) {
            return $next($request);
        }

        return redirect()->route('profile.2fa.setup')
            ->with('warning', __('Staff accounts must use two-step sign-in. Set it up here before opening the admin area.'));
    }
}
