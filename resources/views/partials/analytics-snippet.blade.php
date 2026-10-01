{{--
    Site analytics (Admin → Settings): the Plausible or GA4 tag, only on storefront pages, only when
    configured, and never for a browser that asks not to be tracked (DNT / Global Privacy Control).
--}}
@php
    $analyticsProvider = \App\Models\Setting::get('analytics_provider');
    $analyticsOn = in_array($analyticsProvider, ['plausible', 'ga4'], true)
        && ! request()->routeIs('admin.*', 'seller.*')
        && request()->header('DNT') !== '1'
        && request()->header('Sec-GPC') !== '1';
    $analyticsDomain = $analyticsOn ? trim((string) \App\Models\Setting::get('analytics_domain')) : '';
    $analyticsId = $analyticsOn ? trim((string) \App\Models\Setting::get('analytics_id')) : '';
@endphp
@if($analyticsOn && $analyticsProvider === 'plausible' && $analyticsDomain !== '')
    <script defer data-domain="{{ $analyticsDomain }}" src="https://plausible.io/js/script.js"></script>
@elseif($analyticsOn && $analyticsProvider === 'ga4' && $analyticsId !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($analyticsId), {'anonymize_ip': true});
    </script>
@endif
