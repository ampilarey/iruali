<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // /dv/... storefront URLs: runs before routing (see the middleware)
        $middleware->append(\App\Http\Middleware\LocalePrefix::class);
        $middleware->throttleApi(); // RateLimiter 'api' is defined in AppServiceProvider
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
