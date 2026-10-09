<?php

namespace App\Http\Middleware;

use App\Models\ShopStaff;
use App\Models\User;
use App\Support\Audit;
use App\Support\CurrentShop;
use App\Support\ShopStaffAccess;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Seller Centre gate, run by role:seller (CheckRole hands the seller role to it) on every
 * seller route. It lets in:
 *  - the shop's owner (the seller role), unless the shop is suspended, exactly as before;
 *  - a member of a shop's staff, for the pages their role may open (config/shop_staff.php), while
 *    their shop is open: a suspended or rejected shop is closed to its staff too. When the owner
 *    asks for it, staff must have two-step sign-in on first.
 * It sets CurrentShop for the request. What staff change is written to the audit log, with them
 * as the actor and the shop as the subject.
 */
class ShopAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->hasRole('seller')) {
            // A pending shop may look around (it sees "awaiting approval"); a suspended one may not.
            // (Rejection removes the seller role.)
            if ($user->status === 'suspended') {
                abort(403, 'Your shop is not active.');
            }
            CurrentShop::set($user, null);

            return $next($request);
        }

        $membership = CurrentShop::membershipOf($user);
        $shop = $membership?->shop;
        if (! $membership || ! $shop || ! $shop->hasRole('seller')) {
            abort(403, 'Access denied. Seller role required.');
        }
        if ($shop->status === 'suspended') {
            abort(403, 'Your shop is not active.');
        }

        $routeName = (string) $request->route()?->getName();
        if (! ShopStaffAccess::allows((string) $membership->role, $routeName)) {
            // The Seller Centre's front door: everyone is taken to the first page they can open
            if ($routeName === 'seller.dashboard' && ($home = ShopStaffAccess::homeRoute((string) $membership->role)) !== null) {
                $request->session()->reflash(); // a message for the dashboard shows on their first page instead

                return redirect()->route($home);
            }
            abort(403, 'Your role in this shop cannot open this page.');
        }

        if ($shop->staff_require_two_factor && ! $user->isTwoFactorEnabled()) {
            $request->session()->reflash();

            return redirect()->route('profile.2fa.setup')
                ->with('warning', __(':shop asks its staff to use two-step sign-in. Set it up here before opening the Seller Centre.', ['shop' => $shop->shopName()]));
        }

        CurrentShop::set($shop, $membership);
        $response = $next($request);
        $this->audit($request, $response, $shop, $membership);

        return $response;
    }

    /**
     * A change a staff member made (not a page they looked at, nor a form that came back with errors).
     */
    protected function audit(Request $request, Response $response, User $shop, ShopStaff $membership): void
    {
        $flashed = $request->hasSession() ? (array) $request->session()->get('_flash.new', []) : [];
        if ($request->isMethodSafe() || $response->getStatusCode() >= 400 || array_intersect(['errors', 'error'], $flashed) !== []) {
            return;
        }

        $parameters = [];
        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            $parameters[$name] = $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : null);
        }

        Audit::record('shop.staff_action', $shop, [
            'route' => $request->route()?->getName(),
            'role' => $membership->role,
            'shop' => $shop->shopName(),
        ] + array_filter($parameters, fn ($value) => $value !== null));
    }
}
