<?php

namespace App\Http\Middleware;

use App\Support\StaffAccess as Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin area gate: the user must be staff (admin, support or finance) and the route must be
 * on their role's list in config/staff.php. Replaces role:admin on the admin route group.
 */
class StaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isStaff()) {
            abort(403, 'Access denied. Staff role required.');
        }

        $routeName = (string) $request->route()?->getName();
        if ($routeName === '' || ! Access::can($routeName, $user)) {
            abort(403, 'Your staff role cannot open this page.');
        }

        return $next($request);
    }
}
