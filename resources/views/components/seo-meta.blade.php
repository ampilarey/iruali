@props(['seo' => null])

@php
    $seo = $seo ?? App\Services\SeoService::getDefault();
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
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">

{{-- Twitter Card Meta Tags --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['twitter_title'] }}">
<meta name="twitter:description" content="{{ $seo['twitter_description'] }}">
<meta name="twitter:image" content="{{ $seo['twitter_image'] }}">
<meta name="twitter:site" content="@iruali">

{{-- Additional Meta Tags --}}
<meta name="robots" content="index, follow">
<meta name="author" content="{{ config('app.name') }}">
<meta name="language" content="{{ app()->getLocale() }}">

{{-- JSON-LD Schema --}}
@if(isset($seo['schema']) && $seo['schema'])
    <script type="application/ld+json">
        {!! json_encode($seo['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif

{{-- Additional Meta Tags for Products --}}
@if(isset($seo['og_type']) && $seo['og_type'] === 'product')
    <meta property="product:price:amount" content="{{ $seo['price'] ?? '' }}">
    <meta property="product:price:currency" content="MVR">
    <meta property="product:availability" content="{{ $seo['availability'] ?? 'in stock' }}">
@endif 