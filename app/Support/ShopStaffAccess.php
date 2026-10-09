<?php

namespace App\Support;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Which Seller Centre pages each shop staff role may open (config/shop_staff.php). Used by the
 * role:seller middleware (App\Http\Middleware\ShopAccess), by CurrentShop::can() and by the Seller
 * Centre nav to hide links the person cannot open. Patterns match like config/staff.php's:
 * "seller.orders.*" is a prefix, anything else must match exactly.
 */
class ShopStaffAccess
{
    /** @return list<string> the staff roles, e.g. ['manager', 'packer'] */
    public static function roles(): array
    {
        return array_values(array_map('strval', array_keys((array) config('shop_staff.access', []))));
    }

    public static function isRole(string $role): bool
    {
        return in_array($role, self::roles(), true);
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'owner' => __('Owner'),
            'manager' => __('Manager'),
            'packer' => __('Packer'),
            default => Str::headline($role),
        };
    }

    public static function roleDescription(string $role): string
    {
        return match ($role) {
            'manager' => __('Everything in the Seller Centre except bank details, earnings and payouts, tax registration, business verification, holiday mode and staff.'),
            'packer' => __('Orders (view, update, packing slips, pickups), the stock page and product questions.'),
            default => '',
        };
    }

    /** Pages no staff member may open, whatever their role (money, tax, documents, staff, closing the shop). */
    public static function ownerOnly(string $routeName): bool
    {
        foreach ((array) config('shop_staff.owner_only', []) as $pattern) {
            if (StaffAccess::matches((string) $pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    public static function allows(string $role, string $routeName): bool
    {
        if ($routeName === '' || self::ownerOnly($routeName)) {
            return false;
        }

        foreach ((array) config("shop_staff.access.{$role}", []) as $pattern) {
            if (StaffAccess::matches((string) $pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Seller Centre's routes (named seller.*, behind role:seller), in the order they are registered.
     *
     * @return list<RoutingRoute>
     */
    public static function sellerRoutes(): array
    {
        $all = Route::getRoutes()->getRoutes();
        // Worked out once (again only if routes are added)
        if (self::$sellerRoutes === null || self::$sellerRoutes['count'] !== count($all)) {
            $router = app('router');
            $routes = [];
            foreach ($all as $route) {
                if (str_starts_with((string) $route->getName(), 'seller.') && in_array(\App\Http\Middleware\CheckRole::class.':seller', $router->gatherRouteMiddleware($route), true)) {
                    $routes[] = $route;
                }
            }
            self::$sellerRoutes = ['count' => count($all), 'routes' => $routes];
        }

        return self::$sellerRoutes['routes'];
    }

    /** @var array{count: int, routes: list<RoutingRoute>}|null */
    private static ?array $sellerRoutes = null;

    /**
     * Pages a role can go to straight from a link: GET routes without parameters it may open.
     *
     * @return list<string>
     */
    public static function landingPages(string $role): array
    {
        $names = [];
        foreach (self::sellerRoutes() as $route) {
            $name = (string) $route->getName();
            if (in_array('GET', $route->methods(), true) && $route->parameterNames() === [] && self::allows($role, $name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** The page a role starts on: the dashboard, or else the first page it may open. */
    public static function homeRoute(string $role): ?string
    {
        return self::allows($role, 'seller.dashboard') ? 'seller.dashboard' : (self::landingPages($role)[0] ?? null);
    }

    /**
     * The Seller Centre tabs ([route => [label, pattern]]) the signed-in person can open. For staff,
     * a tab whose own page is closed points at the first page under it that is open (Settings goes
     * to Delivery & pickup for a manager), or is left out. Tabs for routes that do not exist are left out.
     *
     * @param  array<string, array{0: string, 1: string}>  $tabs
     * @return array<string, array{0: string, 1: string}>
     */
    public static function visibleTabs(array $tabs): array
    {
        $tabs = array_filter($tabs, fn ($route) => Route::has($route), ARRAY_FILTER_USE_KEY);
        if (! CurrentShop::isStaff()) {
            return $tabs;
        }

        $role = CurrentShop::role();
        $pages = null;
        $visible = [];
        foreach ($tabs as $route => [$label, $pattern]) {
            if (! self::allows($role, $route)) {
                $pages ??= self::landingPages($role);
                $route = collect($pages)->first(fn ($name) => Str::is($pattern, $name));
            }
            if ($route !== null && ! isset($visible[$route])) {
                $visible[$route] = [$label, $pattern];
            }
        }

        return $visible;
    }
}
