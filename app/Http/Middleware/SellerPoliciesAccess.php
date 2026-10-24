<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SellerApplications;
use App\Support\SellerTerms;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The seller policies (Seller Terms, Prohibited & Restricted Items) are not public: they open for
 * shops, iruali's team and, while applications are open, signed-in visitors (SellerTerms::visibleTo()).
 * A visitor who is not signed in is asked to sign in while applications are open; otherwise 404.
 */
class SellerPoliciesAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (SellerTerms::visibleTo($user instanceof User ? $user : null)) {
            return $next($request);
        }
        if (! $user && SellerApplications::open()) {
            return redirect()->guest(route('login'));
        }

        abort(404);
    }
}
