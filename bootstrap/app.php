<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Feature route files: routes/web/*.php get the web + locale middleware, routes/api/*.php the api group.
            foreach (glob(__DIR__.'/../routes/web/*.php') as $file) {
                Route::middleware(['web', \App\Http\Middleware\SetLocale::class])->group($file);
            }
            foreach (glob(__DIR__.'/../routes/api/*.php') as $file) {
                Route::middleware('api')->prefix('api')->group($file);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'staff' => \App\Http\Middleware\StaffAccess::class,      // admin area: role → allowed routes (config/staff.php)
            'staff.2fa' => \App\Http\Middleware\RequireTwoFactor::class, // staff must have two-step sign-in on
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
        // Which proxies: config('app.trusted_proxies'), set in AppServiceProvider::boot once .env is
        // loaded (this callback runs before it is, so env() here would always see the default)
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
        // Only requests for APP_URL's host (and its subdomains: www., test.) are answered: URLs the app builds
        // from the request (links in emails, the /dv prefix's root URL) can't point at a host a visitor sent.
        // Read at request time from config('app.url'); not applied locally or in tests.
        $middleware->trustHosts();
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // What the service worker may keep offline (needs the session, so in the web group)
        $middleware->web(append: [\App\Http\Middleware\OfflineCopies::class]);
        // /dv/... storefront URLs: runs before routing (see the middleware)
        $middleware->append(\App\Http\Middleware\LocalePrefix::class);
        $middleware->throttleApi(); // RateLimiter 'api' is defined in AppServiceProvider
        // The language is chosen before the sign-in check: a visitor who is not signed in and opens a
        // /dv account link is sent on by SetLocale (keeping Dhivehi), not straight to an English sign-in page
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\SetLocale::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Count every reported exception in error_events and alert on payment errors (app/Support/ErrorTracker.php)
        $exceptions->report(fn (\Throwable $e) => \App\Support\ErrorTracker::record($e));
        // Error pages (404, 403, 500...) in the visitor's language, also where no route matched; null = render as usual
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            \App\Http\Middleware\SetLocale::forErrorPage($request);

            return null;
        });
    })->create();
