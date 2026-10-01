@props(['seo' => null])

@php
    $seo = $seo ?? App\Services\SeoService::getDefault();

    // A page that sets @section('title') wins over the generic site title (products, categories,
    // search and shops already get theirs from SeoService).
    // (inline @section values arrive HTML-escaped; decode so the {{ }} below escapes exactly once)
    $pageTitle = html_entity_decode(trim(strip_tags($__env->yieldContent('title'))), ENT_QUOTES | ENT_HTML5);
    if ($pageTitle !== '' && $seo['title'] === config('app.name')) {
        $pageTitle = preg_replace('/\s*[-–|]\s*iruali\s*$/i', '', $pageTitle);
        $seo['title'] = $pageTitle.' - iruali';
        $seo['og_title'] = $seo['twitter_title'] = $pageTitle;
    }

    // Private, transactional and duplicate-content pages are not for search engines
    $noindex = trim($__env->yieldContent('robots')) === 'noindex, nofollow'
        || request()->routeIs('login', 'register', 'password.*', 'verification.*', '2fa.*', 'account*', 'profile.*', 'cart*', 'checkout*',
            'orders*', 'wishlist*', 'compare*', 'saved.*', 'search', 'order.track.*', 'returns.*', 'payments.*', 'admin.*', 'seller.*', 'locale.*');
@endphp

{{-- Basic Meta Tags --}}
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="keywords" content="{{ $seo['keywords'] }}">

{{-- Canonical URL --}}
<link rel="canonical" href="{{ $seo['canonical_url'] }}">

{{-- Open Graph Meta Tags --}}
<meta property="og:title" content="{{ $seo['og_title'] }}">
<meta property="og:description" content="{{ $seo['og_description'] }}">
<meta property="og:type" content="{{ $seo['og_type'] }}">
<meta property="og:url" content="{{ $seo['canonical_url'] }}">
<meta property="og:image" content="{{ $seo['og_image'] }}">
@if(str_ends_with($seo['og_image'], 'og-image.png'))
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
@endif
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">

{{-- Twitter Card Meta Tags --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['twitter_title'] }}">
<meta name="twitter:description" content="{{ $seo['twitter_description'] }}">
<meta name="twitter:image" content="{{ $seo['twitter_image'] }}">
<meta name="twitter:site" content="@iruali">

{{-- Additional Meta Tags --}}
<meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow' }}">
<meta name="author" content="{{ config('app.name') }}">
<meta name="language" content="{{ app()->getLocale() }}">

{{-- JSON-LD Schema --}}
@if(isset($seo['schema']) && $seo['schema'])
    <script type="application/ld+json">
        {!! json_encode($seo['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
@endif

{{-- Additional Meta Tags for Products --}}
@if(isset($seo['og_type']) && $seo['og_type'] === 'product')
    <meta property="product:price:amount" content="{{ $seo['price'] ?? '' }}">
    <meta property="product:price:currency" content="MVR">
    <meta property="product:availability" content="{{ $seo['availability'] ?? 'in stock' }}">
@endif 