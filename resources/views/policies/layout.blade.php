@extends('layouts.app')

@php
    use App\Models\Setting;
    use App\Support\Company;
    $policyLinks = [
        'policies.terms' => __('Terms & Conditions'),
        'policies.refunds' => __('Returns, Refunds & Cancellations'),
        'policies.delivery' => __('Delivery Policy'),
        'policies.privacy' => __('Privacy Policy'),
        'policies.security' => __('Payment Security'),
        'policies.about' => __('About & Contact'),
    ];

    // Same approach as Bake & Grill: the owner can replace a policy's text from Admin → Legal pages
    // (plain text, shown as written), and "Last updated" appears only once a date has been set.
    $policyKey = trim($__env->yieldContent('policy_key'));
    $override = $policyKey !== '' ? trim((string) Setting::get('legal_'.$policyKey.'_body')) : '';
    $updated = trim((string) Setting::get('legal_last_updated_date'));
    $whatsapp = preg_replace('/[^0-9]/', '', (string) Setting::get('whatsapp_number'));
@endphp

@section('content')
<div class="bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 lg:px-6 py-6 lg:py-10 grid lg:grid-cols-[15rem_1fr] gap-6 lg:gap-10">
        <nav aria-label="{{ __('Policies') }}" class="lg:sticky lg:top-36 self-start">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">{{ __('Policies') }}</p>
            <ul class="flex lg:flex-col gap-1 overflow-x-auto scrollbar-hide text-sm">
                @foreach($policyLinks as $route => $label)
                    <li class="shrink-0"><a href="{{ route($route) }}" class="block px-3 py-2 rounded-lg whitespace-nowrap {{ request()->routeIs($route) ? 'bg-primary text-white font-semibold' : 'text-gray-700 hover:bg-white' }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <article class="bg-white border border-gray-200 rounded-xl p-5 sm:p-8 lg:p-10 min-w-0">
            <h1 class="font-display text-2xl lg:text-3xl font-bold text-dark">@yield('policy_title')</h1>
            <p class="text-gray-600 mt-1">@yield('policy_subtitle', __('Please read this policy before completing your purchase.'))</p>

            {{-- BML requirement 3: corporate information, shown before the policy text --}}
            <div class="mt-5 rounded-lg border border-primary-100 bg-primary-50 p-4 text-sm text-gray-700 space-y-0.5">
                <p class="font-semibold text-dark">{{ Company::legalName() ?? Company::tradingName() }}@if(Company::legalName() && Company::legalName() !== Company::tradingName()) ({{ __('trading as') }} {{ Company::tradingName() }})@endif</p>
                @if(Company::registrationNo())<p>{{ __('Registration no.') }} {{ Company::registrationNo() }}</p>@endif
                @if(Company::address())<p>{{ Company::address() }}</p>@endif
                @if(Company::postalAddress() && Company::postalAddress() !== Company::address())<p>{{ __('Postal address') }}: {{ Company::postalAddress() }}</p>@endif
                <p>
                    @if(Company::phone()){{ __('Phone') }}: <a href="tel:{{ preg_replace('/[^0-9+]/', '', Company::phone()) }}" class="text-primary font-medium" dir="ltr">{{ Company::phone() }}</a>@endif
                    @if(Company::phone() && Company::email()) &nbsp;|&nbsp; @endif
                    @if(Company::email()){{ __('Email') }}: <a href="mailto:{{ Company::email() }}" class="text-primary font-medium">{{ Company::email() }}</a>@endif
                </p>
                <p>{{ __('Customer service') }}: @if($whatsapp){{ __('WhatsApp') }} <a href="https://wa.me/{{ $whatsapp }}" class="text-primary font-medium" dir="ltr">+{{ $whatsapp }}</a>, @endif{{ __('phone or email above') }}@if(Company::hours()) ({{ Company::hours() }})@endif.</p>
            </div>

            <div class="policy mt-6 text-gray-700 leading-relaxed space-y-4 [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-dark [&_h2]:pt-4 [&_ul]:list-disc [&_ul]:ps-6 [&_ul]:space-y-1 [&_a]:text-primary [&_a]:font-medium [&_a:hover]:underline [&_strong]:text-dark">
                @if($override !== '')
                    <div class="whitespace-pre-line">{{ $override }}</div>
                @else
                    @yield('policy')
                @endif
            </div>

            <p class="mt-10 rounded-lg bg-gray-50 border border-gray-200 p-4 text-sm text-gray-600">{{ __('We recommend you keep a copy of your order confirmation, payment receipt and these policies for your records.') }}</p>
            @if($updated !== '')
                <p class="mt-4 text-xs text-gray-500">{{ __('Last updated') }}: {{ $updated }}</p>
            @endif
        </article>
    </div>
</div>
@endsection
