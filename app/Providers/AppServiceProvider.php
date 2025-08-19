<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use App\Services\SeoService;
use App\Services\CartService;
use App\Services\DiscountService;
use App\Services\OrderService;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CartService::class);
        $this->app->singleton(DiscountService::class);
        $this->app->singleton(OrderService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share SEO data with all views
        View::composer('layouts.app', function ($view) {
            $seo = $this->getSeoData();
            $view->with('seo', $seo);
        });

        // Register custom Vite configuration for cPanel deployment
        $this->app->register(\App\Providers\ViteServiceProvider::class);
    }

    /**
     * Get SEO data based on current route and model
     */
    private function getSeoData(): array
    {
        $route = Route::current();
        
        if (!$route) {
            return SeoService::getDefault();
        }

        $routeName = $route->getName();
        $routeParameters = $route->parameters();

        // Product detail page
        if ($routeName === 'products.show' && isset($routeParameters['product'])) {
            $product = $routeParameters['product'];
            if ($product instanceof Product) {
                return SeoService::forProduct($product);
            }
        }

        // Category detail page
        if ($routeName === 'categories.show' && isset($routeParameters['category'])) {
            $category = $routeParameters['category'];
            if ($category instanceof Category) {
                return SeoService::forCategory($category);
            }
        }

        // User profile page
        if ($routeName === 'users.show' && isset($routeParameters['user'])) {
            $user = $routeParameters['user'];
            if ($user instanceof User) {
                return SeoService::forUser($user);
            }
        }

        // Search results page
        if ($routeName === 'search') {
            $query = request()->get('q', '');
            $totalResults = request()->get('total', 0);
            return SeoService::forSearch($query, $totalResults);
        }

        // Shop page
        if ($routeName === 'shop') {
            return [
                'title' => 'Shop - iruali',
                'description' => 'Discover amazing products on iruali. Shop the latest collections, flash sales, and deals from top sellers in the Maldives.',
                'keywords' => 'shop, iruali, Maldives, e-commerce, online shopping, products, deals',
                'og_title' => 'Shop - iruali',
                'og_description' => 'Discover amazing products on iruali. Shop the latest collections, flash sales, and deals from top sellers in the Maldives.',
                'og_type' => 'website',
                'og_image' => asset('images/og-image.svg'),
                'twitter_title' => 'Shop - iruali',
                'twitter_description' => 'Discover amazing products on iruali. Shop the latest collections, flash sales, and deals from top sellers in the Maldives.',
                'twitter_image' => asset('images/og-image.svg'),
                'canonical_url' => route('shop'),
                'schema' => null,
            ];
        }

        // Products listing page
        if ($routeName === 'products.index') {
            return [
                'title' => 'All Products - iruali',
                'description' => 'Browse all products on iruali. Find the best deals, latest arrivals, and popular items from trusted sellers in the Maldives.',
                'keywords' => 'products, iruali, Maldives, e-commerce, online shopping, all products',
                'og_title' => 'All Products - iruali',
                'og_description' => 'Browse all products on iruali. Find the best deals, latest arrivals, and popular items from trusted sellers in the Maldives.',
                'og_type' => 'website',
                'og_image' => asset('images/og-image.svg'),
                'twitter_title' => 'All Products - iruali',
                'twitter_description' => 'Browse all products on iruali. Find the best deals, latest arrivals, and popular items from trusted sellers in the Maldives.',
                'twitter_image' => asset('images/og-image.svg'),
                'canonical_url' => route('products.index'),
                'schema' => null,
            ];
        }

        // Categories listing page
        if ($routeName === 'categories.index') {
            return [
                'title' => 'Categories - iruali',
                'description' => 'Explore product categories on iruali. Find exactly what you\'re looking for with our organized collection of products from the Maldives.',
                'keywords' => 'categories, iruali, Maldives, e-commerce, product categories',
                'og_title' => 'Categories - iruali',
                'og_description' => 'Explore product categories on iruali. Find exactly what you\'re looking for with our organized collection of products from the Maldives.',
                'og_type' => 'website',
                'og_image' => asset('images/og-image.svg'),
                'twitter_title' => 'Categories - iruali',
                'twitter_description' => 'Explore product categories on iruali. Find exactly what you\'re looking for with our organized collection of products from the Maldives.',
                'twitter_image' => asset('images/og-image.svg'),
                'canonical_url' => route('categories.index'),
                'schema' => null,
            ];
        }

        // Default SEO data
        return SeoService::getDefault();
    }
}
