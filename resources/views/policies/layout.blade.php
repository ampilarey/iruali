@extends('layouts.app')

@php
    use App\Support\Company;
    $policyLinks = [
        'policies.terms' => __('Terms & Conditions'),
        'policies.refunds' => __('Returns, Refunds & Cancellations'),
        'policies.delivery' => __('Delivery Policy'),
        'policies.privacy' => __('Privacy Policy'),
        'policies.security' => __('Payment Security'),
        'policies.about' => __('About & Contact'),
    ];
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
            <p class="text-sm text-gray-500 mt-1">{{ __('Last updated') }}: @yield('policy_updated', '25 September 2026')</p>
            <div class="policy mt-6 text-gray-700 leading-relaxed space-y-4 [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-dark [&_h2]:pt-4 [&_ul]:list-disc [&_ul]:ps-6 [&_ul]:space-y-1 [&_a]:text-primary [&_a]:font-medium [&_a:hover]:underline [&_strong]:text-dark">
                @yield('policy')
            </div>
            <div class="mt-10 rounded-lg bg-gray-50 border border-gray-200 p-4 text-sm text-gray-600">
                <p class="font-semibold text-dark">{{ Company::legalName() ?? Company::tradingName() }}@if(Company::legalName() && Company::legalName() !== Company::tradingName()) ({{ __('trading as') }} {{ Company::tradingName() }})@endif</p>
                @if(Company::registrationNo())<p>{{ __('Registration no.') }} {{ Company::registrationNo() }}</p>@endif
                @if(Company::address())<p>{{ Company::address() }}</p>@endif
                <p class="mt-1">
                    @if(Company::email())<a href="mailto:{{ Company::email() }}" class="text-primary">{{ Company::email() }}</a>@endif
                    @if(Company::email() && Company::phone()) &middot; @endif
                    @if(Company::phone())<a href="tel:{{ preg_replace('/[^0-9+]/', '', Company::phone()) }}" class="text-primary" dir="ltr">{{ Company::phone() }}</a>@endif
                </p>
                <p class="mt-2">{{ __('We recommend you keep a copy of your order confirmation, payment receipt and these policies for your records.') }}</p>
            </div>
        </article>
    </div>
</div>
@endsection
