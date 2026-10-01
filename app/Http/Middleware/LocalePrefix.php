<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The Dhivehi storefront lives under /dv/... (English keeps the plain URLs).
 *
 * This runs before routing. For a /dv request it hands the router a copy of the request whose
 * base URL is "/dv", exactly as if the app were installed in a /dv folder: routes match on the
 * path after the prefix, while request()->url(), fullUrl(), pagination and form actions keep the
 * prefix. Generated URLs get the prefix back only for storefront routes (see LocaleUrl), so the
 * generator's root is pinned to the real site root here.
 *
 * SetLocale reads the prefix from the request attribute and answers 404 for /dv on non-storefront
 * routes (account, checkout, admin, seller...).
 */
class LocalePrefix
{
    public const ATTRIBUTE = 'locale_prefix';

    public const PREFIX = 'dv';

    public function handle(Request $request, Closure $next)
    {
        $path = $request->getPathInfo();
        $prefix = '/'.self::PREFIX;

        $prefixed = ($path === $prefix || str_starts_with($path, $prefix.'/'))
            && ! str_starts_with($path, $prefix.'/api/');

        URL::forceRootUrl($prefixed ? $request->getSchemeAndHttpHost().$request->getBaseUrl() : null);

        if (! $prefixed) {
            return $next($request);
        }

        return $next($this->rebase($request));
    }

    /**
     * A copy of the request with "/dv" as its base URL.
     */
    protected function rebase(Request $request): Request
    {
        // Symfony takes the base URL from SCRIPT_NAME when its basename matches SCRIPT_FILENAME's,
        // and keeps it when it is a prefix of the request URI: "<base>/dv" is, for /dv and /dv/...
        $server = $request->server->all();
        $server['SCRIPT_NAME'] = $request->getBaseUrl().'/'.self::PREFIX;
        $server['PHP_SELF'] = $server['SCRIPT_NAME'];
        $server['SCRIPT_FILENAME'] = '/'.self::PREFIX;
        unset($server['ORIG_PATH_INFO'], $server['PATH_INFO'], $server['ORIG_SCRIPT_NAME']);

        $attributes = $request->attributes->all();
        $attributes[self::ATTRIBUTE] = self::PREFIX;

        return $request->duplicate(null, null, $attributes, null, null, $server);
    }

    /**
     * Whether the current request came in under the /dv prefix.
     */
    public static function active(?Request $request = null): bool
    {
        $request ??= request();

        return $request->attributes->get(self::ATTRIBUTE) === self::PREFIX;
    }
}
