<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;

class ViteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Override the default Vite manifest path for cPanel deployment
        Vite::useHotFile(public_path('hot'))
            ->useBuildDirectory('build')
            ->withEntryPoints(['resources/css/app.css', 'resources/js/app.js']);
    }
}
