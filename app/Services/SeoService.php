<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * Generate SEO data for a product page
     */
    public static function forProduct(Product $product): array
    {
        $name = LocalizationService::getLocalizedValue($product, 'name');
        $description = LocalizationService::getLocalizedValue($product, 'description');
        
        // Clean description for meta
        $metaDescription = Str::limit(strip_tags($description), 160);
        
        // Generate keywords from product data
        $keywords = collect([
            $name,
            $product->brand,
            $product->category?->name,
            $product->tags ? implode(', ', $product->tags) : null,
            'iruali',
            'Maldives',
            'e-commerce'
        ])->filter()->implode(', ');

        return [
            'title' => $name . ' - iruali',
            'description' => $metaDescription,
            'keywords' => $keywords,
            'og_title' => $name,
            'og_description' => $metaDescription,
            'og_type' => 'product',
            'og_image' => $product->main_image ? asset($product->main_image) : asset('images/og-image.svg'),
            'twitter_title' => $name,
            'twitter_description' => $metaDescription,
            'twitter_image' => $product->main_image ? asset($product->main_image) : asset('images/og-image.svg'),
            'canonical_url' => route('products.show', $product->slug),
            'schema' => self::generateProductSchema($product),
        ];
    }

    /**
     * Generate SEO data for a category page
     */
    public static function forCategory(Category $category): array
    {
        $name = LocalizationService::getLocalizedValue($category, 'name');
        $description = LocalizationService::getLocalizedValue($category, 'description');
        
        $metaDescription = Str::limit(strip_tags($description), 160);
        
        return [
            'title' => $name . ' - iruali',
            'description' => $metaDescription,
            'keywords' => "{$name}, iruali, Maldives, e-commerce, online shopping",
            'og_title' => $name,
            'og_description' => $metaDescription,
            'og_type' => 'website',
            'og_image' => asset('images/og-image.svg'),
            'twitter_title' => $name,
            'twitter_description' => $metaDescription,
            'twitter_image' => asset('images/og-image.svg'),
            'canonical_url' => route('categories.show', $category->slug),
            'schema' => self::generateCategorySchema($category),
        ];
    }

    /**
     * Generate SEO data for search results
     */
    public static function forSearch(string $query, int $totalResults = 0): array
    {
        $title = "Search results for '{$query}'";
        $description = "Find the best products for '{$query}' on iruali. " . 
                      ($totalResults > 0 ? "{$totalResults} products found." : "Shop now!");
        
        return [
            'title' => $title . ' - iruali',
            'description' => $description,
            'keywords' => "{$query}, search, iruali, Maldives, e-commerce",
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
            'og_image' => asset('images/og-image.svg'),
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => asset('images/og-image.svg'),
            'canonical_url' => request()->url(),
            'schema' => null,
        ];
    }

    /**
     * Generate SEO data for user profile/seller page
     */
    public static function forUser(User $user): array
    {
        $title = $user->is_seller ? "Shop by {$user->name}" : "{$user->name}'s Profile";
        $description = $user->is_seller 
            ? "Discover amazing products from {$user->name} on iruali. Shop the latest collection now!"
            : "View {$user->name}'s profile on iruali.";
        
        return [
            'title' => $title . ' - iruali',
            'description' => $description,
            'keywords' => "{$user->name}, iruali, Maldives, e-commerce" . ($user->is_seller ? ", seller, shop" : ""),
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'profile',
            'og_image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.svg'),
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.svg'),
            'canonical_url' => request()->url(),
            'schema' => self::generateUserSchema($user),
        ];
    }

    /**
     * Generate default SEO data
     */
    public static function getDefault(): array
    {
        return [
            'title' => config('app.name'),
            'description' => 'iruali is a modern, multi-vendor e-commerce platform for the Maldives. Shop the latest products, flash sales, and more!',
            'keywords' => 'iruali, e-commerce, Maldives, shop, online, multi-vendor, flash sale, deals, products',
            'og_title' => config('app.name'),
            'og_description' => 'iruali is a modern, multi-vendor e-commerce platform for the Maldives. Shop the latest products, flash sales, and more!',
            'og_type' => 'website',
            'og_image' => asset('images/og-image.svg'),
            'twitter_title' => config('app.name'),
            'twitter_description' => 'iruali is a modern, multi-vendor e-commerce platform for the Maldives. Shop the latest products, flash sales, and more!',
            'twitter_image' => asset('images/og-image.svg'),
            'canonical_url' => request()->url(),
            'schema' => self::generateWebsiteSchema(),
        ];
    }

    /**
     * Generate JSON-LD schema for a product
     */
    private static function generateProductSchema(Product $product): array
    {
        $name = LocalizationService::getLocalizedValue($product, 'name');
        $description = LocalizationService::getLocalizedValue($product, 'description');
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => $description,
            'image' => $product->main_image ? asset($product->main_image) : asset('images/og-image.svg'),
            'brand' => [
                '@type' => 'Brand',
                'name' => $product->brand ?? 'iruali'
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->final_price,
                'priceCurrency' => 'MVR',
                'availability' => $product->is_in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => route('products.show', $product->slug)
            ],
            'category' => $product->category ? LocalizationService::getLocalizedValue($product->category, 'name') : null,
            'sku' => $product->sku,
        ];
    }

    /**
     * Generate JSON-LD schema for a category
     */
    private static function generateCategorySchema(Category $category): array
    {
        $name = LocalizationService::getLocalizedValue($category, 'name');
        $description = LocalizationService::getLocalizedValue($category, 'description');
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $description,
            'url' => route('categories.show', $category->slug),
        ];
    }

    /**
     * Generate JSON-LD schema for a user/seller
     */
    private static function generateUserSchema(User $user): array
    {
        if ($user->is_seller) {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $user->name,
                'url' => request()->url(),
                'image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.svg'),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $user->name,
            'url' => request()->url(),
            'image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.svg'),
        ];
    }

    /**
     * Generate JSON-LD schema for the website
     */
    private static function generateWebsiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'description' => 'iruali is a modern, multi-vendor e-commerce platform for the Maldives.',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search') . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string'
            ]
        ];
    }
} 