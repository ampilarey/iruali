<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
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
            'e-commerce',
        ])->filter()->implode(', ');

        return [
            'title' => $name.' - iruali',
            'description' => $metaDescription,
            'keywords' => $keywords,
            'og_title' => $name,
            'og_description' => $metaDescription,
            'og_type' => 'product',
            'og_image' => $product->main_image ? asset($product->main_image) : asset('images/og-image.png'),
            'twitter_title' => $name,
            'twitter_description' => $metaDescription,
            'twitter_image' => $product->main_image ? asset($product->main_image) : asset('images/og-image.png'),
            'canonical_url' => route('products.show', $product->slug),
            'schema' => self::generateProductSchema($product),
            'extra_schema' => [self::breadcrumbSchema($product)],
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
            'title' => $name.' - iruali',
            'description' => $metaDescription,
            'keywords' => "{$name}, iruali, Maldives, e-commerce, online shopping",
            'og_title' => $name,
            'og_description' => $metaDescription,
            'og_type' => 'website',
            'og_image' => asset('images/og-image.png'),
            'twitter_title' => $name,
            'twitter_description' => $metaDescription,
            'twitter_image' => asset('images/og-image.png'),
            'canonical_url' => route('categories.show', $category->slug),
            'schema' => self::generateCategorySchema($category),
        ];
    }

    /**
     * A brand's page. Brands with only a product or two are left out of search engines until
     * they have more (thin pages hurt the whole site); the links on them are still followed.
     */
    public static function forBrand(Brand $brand): array
    {
        $count = $brand->activeProductCount();
        $url = route('brands.show', $brand);
        // Dhivehi pages use the brand's Dhivehi name when it has one
        $name = $brand->localizedName();
        $description = $brand->localizedDescription()
            ? Str::limit(trim(strip_tags($brand->localizedDescription())), 160)
            : __('Buy :brand from shops across the Maldives on iruali.', ['brand' => $name]);
        $image = $brand->logoUrl() ?? asset('images/og-image.png');

        $about = ['@type' => 'Brand', 'name' => $brand->name];
        if (filled($brand->name_dv)) {
            $about['alternateName'] = $brand->name_dv;
        }
        if ($brand->logoUrl()) {
            $about['logo'] = $brand->logoUrl();
        }

        return [
            'title' => $name.' - iruali',
            'description' => $description,
            'keywords' => collect([$name, $brand->name, 'iruali', 'Maldives', 'online shopping'])->unique()->implode(', '),
            'og_title' => $name,
            'og_description' => $description,
            'og_type' => 'website',
            'og_image' => $image,
            'twitter_title' => $name,
            'twitter_description' => $description,
            'twitter_image' => $image,
            'canonical_url' => $url,
            'robots' => $brand->isIndexable($count) ? null : 'noindex, follow',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $name,
                'description' => $description,
                'url' => $url,
                'about' => $about,
            ],
            'extra_schema' => [self::listBreadcrumbs([[__('Brands'), route('brands.index')], [$name, $url]])],
        ];
    }

    /**
     * The A–Z brands directory.
     */
    public static function forBrandDirectory(): array
    {
        $title = __('Brands');
        $description = __('Shop by brand: every brand sold on iruali by shops across the Maldives.');
        $url = route('brands.index');

        return [
            'title' => $title.' - iruali',
            'description' => $description,
            'keywords' => 'brands, iruali, Maldives, online shopping',
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
            'og_image' => asset('images/og-image.png'),
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => asset('images/og-image.png'),
            'canonical_url' => $url,
            'schema' => ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $title, 'description' => $description, 'url' => $url],
            'extra_schema' => [self::listBreadcrumbs([[$title, $url]])],
        ];
    }

    /**
     * Home › … breadcrumbs for a listing page.
     *
     * @param  array<int, array{0: string, 1: string}>  $items  [name, url] after Home
     */
    private static function listBreadcrumbs(array $items): array
    {
        $items = array_merge([[__('Home'), route('home')]], $items);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Generate SEO data for search results
     */
    public static function forSearch(string $query, int $totalResults = 0): array
    {
        $title = "Search results for '{$query}'";
        $description = "Find the best products for '{$query}' on iruali. ".
                      ($totalResults > 0 ? "{$totalResults} products found." : 'Shop now!');

        return [
            'title' => $title.' - iruali',
            'description' => $description,
            'keywords' => "{$query}, search, iruali, Maldives, e-commerce",
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
            'og_image' => asset('images/og-image.png'),
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => asset('images/og-image.png'),
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
            'title' => $title.' - iruali',
            'description' => $description,
            'keywords' => "{$user->name}, iruali, Maldives, e-commerce".($user->is_seller ? ', seller, shop' : ''),
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'profile',
            'og_image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.png'),
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.png'),
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
            'og_image' => asset('images/og-image.png'),
            'twitter_title' => config('app.name'),
            'twitter_description' => 'iruali is a modern, multi-vendor e-commerce platform for the Maldives. Shop the latest products, flash sales, and more!',
            'twitter_image' => asset('images/og-image.png'),
            'canonical_url' => request()->url(),
            'schema' => self::generateWebsiteSchema(),
            // The home page also says who runs the site
            'extra_schema' => request()->routeIs('home') ? [self::organizationSchema()] : [],
        ];
    }

    /**
     * Generate JSON-LD schema for a product
     */
    private static function generateProductSchema(Product $product): array
    {
        $name = LocalizationService::getLocalizedValue($product, 'name');
        $description = LocalizationService::getLocalizedValue($product, 'description');
        $url = route('products.show', $product->slug);
        $seller = $product->seller;

        // Gallery images first, the legacy main_image column as a fallback
        // ($product->images is the legacy JSON column, so the relation is read explicitly)
        $gallery = $product->relationLoaded('images') ? $product->getRelation('images') : $product->images()->get();
        $images = $gallery->sortByDesc('is_main')->pluck('url')->all();
        if ($images === [] && $product->main_image) {
            $images = [$product->main_image];
        }
        $images = array_values(array_map(fn ($u) => str_starts_with($u, 'http') ? $u : asset($u), $images)) ?: [asset('images/og-image.png')];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => Str::limit(trim(strip_tags((string) $description)), 5000, ''),
            'image' => $images,
            'sku' => $product->sku,
            'url' => $url,
            'category' => $product->category ? LocalizationService::getLocalizedValue($product->category, 'name') : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'price' => number_format((float) $product->final_price, 2, '.', ''),
                'priceCurrency' => 'MVR',
                'priceValidUntil' => ($product->deal_ends_at ?? now()->addYear())->toDateString(),
                'availability' => $product->effectiveStock() > 0 && ! $seller?->isOnHoliday() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $seller ? ($seller->business_name ?: $seller->name) : config('app.name'),
                    'url' => $seller ? route('sellers.show', $seller) : config('app.url'),
                ],
            ],
        ];
        if ($product->brand) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product->brand];
        }

        // Sold out but open for pre-orders: on pre-order, from the date it is expected to ship
        if ($product->preorder_ship_date && app(PreorderService::class)->isPreorderable($product)) {
            $schema['offers']['availability'] = 'https://schema.org/PreOrder';
            $schema['offers']['availabilityStarts'] = $product->preorder_ship_date->toDateString();
        }

        // Ratings only when there are approved reviews (Google rejects an empty aggregateRating).
        // n and avg are computed columns of this query, so they are read with getAttribute().
        $reviews = $product->reviews()->where('is_approved', true)->selectRaw('COUNT(*) as n, AVG(rating) as avg')->first();
        if ($reviews && (int) $reviews->getAttribute('n') > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $reviews->getAttribute('avg'), 1),
                'reviewCount' => (int) $reviews->getAttribute('n'),
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return $schema;
    }

    /**
     * Home › department › sub-department › product, as the page's breadcrumb shows it.
     */
    private static function breadcrumbSchema(Product $product): array
    {
        $name = LocalizationService::getLocalizedValue($product, 'name');
        $url = route('products.show', $product->slug);
        $items = [[__('Home'), route('home')]];
        if ($product->category?->parent) {
            $items[] = [LocalizationService::getLocalizedValue($product->category->parent, 'name'), route('categories.show', $product->category->parent->slug)];
        }
        if ($product->category) {
            $items[] = [LocalizationService::getLocalizedValue($product->category, 'name'), route('categories.show', $product->category->slug)];
        }
        $items[] = [$name, $url];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Who runs the site, from Admin → Settings (business details and social links). Shown on the home page.
     */
    public static function organizationSchema(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => Setting::get('company_trading_name') ?: config('app.name'),
            'url' => config('app.url'),
            'logo' => asset('images/brand/iruali-logo.svg'),
        ];
        if ($legal = Setting::get('company_legal_name')) {
            $schema['legalName'] = $legal;
        }
        if ($phone = Setting::get('contact_phone')) {
            $schema['telephone'] = $phone;
        }
        if ($email = Setting::get('contact_email')) {
            $schema['email'] = $email;
        }
        if ($address = Setting::get('company_address')) {
            $schema['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address, 'addressCountry' => 'MV'];
        }
        $sameAs = array_values(array_filter(array_map(fn ($key) => trim((string) Setting::get($key)), ['social_facebook', 'social_instagram', 'social_tiktok', 'social_x'])));
        if ($sameAs !== []) {
            $schema['sameAs'] = $sameAs;
        }

        return $schema;
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
                'image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.png'),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $user->name,
            'url' => request()->url(),
            'image' => $user->avatar ? asset($user->avatar) : asset('images/og-image.png'),
        ];
    }

    /**
     * Generate JSON-LD schema for the website
     */
    private static function generateWebsiteSchema(): array
    {
        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'description' => 'iruali is a modern, multi-vendor e-commerce platform for the Maldives.',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return $website;
    }
}
