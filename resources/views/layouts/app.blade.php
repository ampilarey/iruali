<!DOCTYPE html>
@php
    // Dhivehi reads right-to-left. Admin and Seller Centre pages are English-only for now, so they stay LTR.
    $rtl = app()->getLocale() === 'dv' && ! request()->routeIs('admin.*', 'seller.*');
    $announcement = \App\Models\Setting::get('announcement_text');
    $contactEmail = \App\Models\Setting::get('contact_email');
    $contactPhone = \App\Models\Setting::get('contact_phone');
    $navDepartments = $navDepartments ?? collect();
    $cartCount = $cartCount ?? 0;
    $wishlistCount = $wishlistCount ?? 0;
    $otherLocale = app()->getLocale() === 'dv' ? 'en' : 'dv';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicon and Web App Manifest -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0B7A70">

    <x-seo-meta :seo="$seo ?? null" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&family=Noto+Sans+Thaana:wght@400;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="font-sans antialiased bg-background text-dark pb-16 lg:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:start-2 focus:z-[100] focus:bg-white focus:px-4 focus:py-2 focus:rounded-lg focus:shadow">{{ __('Skip to content') }}</a>

    <!-- Utility bar (desktop) -->
    <div class="hidden lg:block bg-reef text-white/85 text-xs">
        <div class="max-w-7xl mx-auto px-6 h-8 flex items-center justify-between gap-6">
            <p class="truncate">@if($announcement){{ __($announcement) }}@endif</p>
            <div class="flex items-center gap-5 shrink-0">
                <a href="{{ route('order.track.form') }}" class="hover:text-white">{{ __('Track Order') }}</a>
                <a href="{{ route('seller.apply') }}" class="hover:text-white">{{ __('Sell on iruali') }}</a>
                <a href="{{ route('help') }}" class="hover:text-white">{{ __('Help centre') }}</a>
                @if($contactPhone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhone) }}" class="hover:text-white" dir="ltr">{{ $contactPhone }}</a>
                @endif
                <form action="{{ route('locale.switch') }}" method="POST">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $otherLocale }}">
                    <button type="submit" class="inline-flex items-center gap-1 hover:text-white" lang="{{ $otherLocale }}">
                        <x-icon name="globe" class="w-3.5 h-3.5" />{{ $otherLocale === 'dv' ? 'ދިވެހި' : 'English' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 lg:px-6">
            <div class="flex items-center gap-2 lg:gap-6 h-14 lg:h-[72px]">
                <button type="button" data-drawer-open class="lg:hidden -ms-1 p-2 text-dark" aria-label="{{ __('Open menu') }}">
                    <x-icon name="menu" class="w-6 h-6" />
                </button>

                <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ __('iruali home') }}">
                    <img src="/images/brand/iruali-logo.svg" alt="iruali" width="156" height="40" class="h-8 lg:h-10 w-auto">
                </a>

                <!-- Search (desktop) -->
                <form action="{{ route('search') }}" method="GET" role="search" data-suggest class="hidden lg:flex flex-1 h-11 rounded-lg border-2 border-primary overflow-hidden bg-white focus-within:ring-2 focus-within:ring-primary/30">
                    <label for="search-department" class="sr-only">{{ __('Department') }}</label>
                    <select id="search-department" name="category" class="h-full border-0 border-e border-gray-200 bg-gray-50 text-sm text-gray-700 ps-3 pe-8 max-w-[11rem] focus:ring-0">
                        <option value="">{{ __('All departments') }}</option>
                        @foreach($navDepartments as $dept)
                            <option value="{{ $dept->slug }}" @selected(request('category') === $dept->slug || (request()->route('category')?->slug ?? null) === $dept->slug)>{{ $dept->localized_name }}</option>
                        @endforeach
                    </select>
                    <label for="search-q" class="sr-only">{{ __('Search') }}</label>
                    <input id="search-q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search products, brands and shops') }}"
                           class="flex-1 min-w-0 border-0 px-4 text-sm focus:ring-0" autocomplete="off">
                    <button type="submit" class="px-5 bg-primary hover:bg-primary-hover text-white flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="search" class="w-5 h-5" /><span class="sr-only">{{ __('Search') }}</span>
                    </button>
                </form>

                <div class="flex items-center gap-1 lg:gap-5 ms-auto lg:ms-0">
                    <!-- Account -->
                    @auth
                        <div class="relative group hidden lg:block">
                            <a href="{{ route('account') }}" class="flex items-center gap-2 text-dark hover:text-primary">
                                <x-icon name="user" class="w-6 h-6" />
                                <span class="leading-tight text-start">
                                    <span class="block text-xs text-gray-500">{{ __('Hello, :name', ['name' => \Illuminate\Support\Str::of(auth()->user()->name)->before(' ')]) }}</span>
                                    <span class="flex items-center gap-1 text-sm font-semibold">{{ __('Account & Orders') }}<x-icon name="chevron-down" class="w-3 h-3" /></span>
                                </span>
                            </a>
                            <div class="absolute end-0 top-full pt-2 w-64 invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 transition z-50">
                                <div class="bg-white rounded-xl shadow-xl border border-gray-200 py-2 text-sm">
                                    <div class="px-4 py-2 border-b border-gray-100">
                                        <p class="font-semibold text-dark truncate">{{ auth()->user()->name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                                    </div>
                                    <a href="{{ route('account') }}" class="block px-4 py-2 hover:bg-gray-50">{{ __('My Account') }}</a>
                                    <a href="{{ route('orders') }}" class="block px-4 py-2 hover:bg-gray-50">{{ __('My Orders') }}</a>
                                    <a href="{{ route('wishlist') }}" class="block px-4 py-2 hover:bg-gray-50">{{ __('Wishlist') }}</a>
                                    <a href="{{ route(auth()->user()->hasRole('seller') ? 'seller.dashboard' : 'seller.apply') }}" class="block px-4 py-2 hover:bg-gray-50">{{ auth()->user()->hasRole('seller') ? __('Seller Centre') : __('Sell on iruali') }}</a>
                                    @if(auth()->user()->hasRole('admin'))
                                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 hover:bg-gray-50">{{ __('Admin Dashboard') }}</a>
                                    @endif
                                    <form action="{{ route('logout') }}" method="POST" class="border-t border-gray-100 mt-1 pt-1">
                                        @csrf
                                        <button type="submit" class="w-full text-start px-4 py-2 text-danger hover:bg-danger-50">{{ __('Sign Out') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="hidden lg:flex items-center gap-2 text-dark hover:text-primary">
                            <x-icon name="user" class="w-6 h-6" />
                            <span class="leading-tight text-start">
                                <span class="block text-xs text-gray-500">{{ __('Hello, sign in') }}</span>
                                <span class="block text-sm font-semibold">{{ __('Account & Orders') }}</span>
                            </span>
                        </a>
                    @endauth

                    <a href="{{ auth()->check() ? route('account') : route('login') }}" class="lg:hidden p-2 text-dark" aria-label="{{ __('My Account') }}">
                        <x-icon name="user" class="w-6 h-6" />
                    </a>

                    <!-- Wishlist -->
                    <a href="{{ route('wishlist') }}" class="relative hidden lg:flex items-center p-1 text-dark hover:text-primary" aria-label="{{ __('Wishlist') }}">
                        <x-icon name="heart" class="w-6 h-6" />
                        @if($wishlistCount > 0)
                            <span class="absolute -top-1 -end-1 min-w-5 h-5 px-1 rounded-full bg-primary text-white text-[11px] font-bold flex items-center justify-center">{{ $wishlistCount }}</span>
                        @endif
                    </a>

                    <!-- Cart -->
                    <a href="{{ route('cart') }}" class="relative flex items-center gap-2 p-2 lg:p-0 text-dark hover:text-primary" aria-label="{{ __('Cart') }}">
                        <span class="relative">
                            <x-icon name="cart" class="w-6 h-6" />
                            @if($cartCount > 0)
                                <span class="absolute -top-2 -end-2 min-w-5 h-5 px-1 rounded-full bg-sun text-sun-on text-[11px] font-bold flex items-center justify-center">{{ $cartCount }}</span>
                            @endif
                        </span>
                        <span class="hidden lg:block text-sm font-semibold">{{ __('Cart') }}</span>
                    </a>
                </div>
            </div>

            <!-- Search (mobile) -->
            <form action="{{ route('search') }}" method="GET" role="search" data-suggest class="lg:hidden pb-2.5">
                <label for="search-q-mobile" class="sr-only">{{ __('Search') }}</label>
                <div class="flex h-11 rounded-lg border-2 border-primary overflow-hidden bg-white">
                    <input id="search-q-mobile" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search products, brands and shops') }}"
                           class="flex-1 min-w-0 border-0 px-3 text-base focus:ring-0" autocomplete="off">
                    <button type="submit" class="px-4 bg-primary text-white" aria-label="{{ __('Search') }}"><x-icon name="search" class="w-5 h-5" /></button>
                </div>
            </form>
        </div>

        <!-- Department bar (desktop) -->
        <nav class="hidden lg:block bg-primary text-white" aria-label="{{ __('Departments') }}">
            <div class="max-w-7xl mx-auto px-6 flex items-center h-11 text-sm font-medium">
                <div class="relative" data-mega>
                    <button type="button" data-mega-toggle aria-expanded="false" class="h-11 flex items-center gap-2 pe-5 me-2 border-e border-white/20 font-semibold hover:text-sun">
                        <x-icon name="menu" class="w-5 h-5" />{{ __('All Departments') }}<x-icon name="chevron-down" class="w-3.5 h-3.5" />
                    </button>
                    <div data-mega-panel class="hidden absolute start-0 top-full w-[760px] bg-white text-dark rounded-b-xl shadow-2xl border border-gray-200 p-5 z-50">
                        <div class="grid grid-cols-2 gap-1">
                            @foreach($navDepartments as $dept)
                                <a href="{{ route('categories.show', $dept) }}" class="flex items-start gap-3 p-3 rounded-lg hover:bg-primary-50 group/dept">
                                    <span class="w-10 h-10 shrink-0 rounded-lg bg-primary-50 group-hover/dept:bg-white text-primary flex items-center justify-center"><x-icon :name="$dept->slug" class="w-5 h-5" /></span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold group-hover/dept:text-primary">{{ $dept->localized_name }}</span>
                                        <span class="block text-xs text-gray-500 line-clamp-2">{{ $dept->localized_description }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-sm">
                            <a href="{{ route('categories.index') }}" class="font-semibold text-primary hover:underline">{{ __('See all departments') }}</a>
                            <a href="{{ route('deals') }}" class="inline-flex items-center gap-1.5 font-semibold text-coral hover:underline"><x-icon name="tag" class="w-4 h-4" />{{ __('Today\'s deals') }}</a>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1 min-w-0 overflow-hidden">
                    @foreach($navDepartments->take(6) as $dept)
                        <a href="{{ route('categories.show', $dept) }}" class="px-3 h-11 flex items-center whitespace-nowrap hover:bg-white/10 {{ request()->route('category')?->id === $dept->id ? 'bg-white/15' : '' }}">{{ $dept->localized_name }}</a>
                    @endforeach
                </div>
                <div class="ms-auto flex items-center gap-1 shrink-0">
                    <a href="{{ route('shop', ['sort' => 'newest']) }}" class="px-3 h-11 flex items-center hover:bg-white/10">{{ __('New arrivals') }}</a>
                    <a href="{{ route('deals') }}" class="ms-1 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sun text-sun-on font-semibold hover:bg-accent-400"><x-icon name="tag" class="w-4 h-4" />{{ __('Deals') }}</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Mobile drawer -->
    <div data-drawer class="fixed inset-0 z-[60] hidden lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
        <div data-drawer-close class="absolute inset-0 bg-reef/60"></div>
        <aside class="absolute inset-y-0 start-0 w-[86%] max-w-sm bg-white shadow-2xl flex flex-col">
            <div class="bg-primary text-white px-4 py-4 flex items-center gap-3">
                <x-icon name="user" class="w-7 h-7" />
                <div class="flex-1 min-w-0">
                    @auth
                        <p class="font-semibold truncate">{{ __('Hello, :name', ['name' => \Illuminate\Support\Str::of(auth()->user()->name)->before(' ')]) }}</p>
                        <a href="{{ route('account') }}" class="text-sm text-white/85 underline">{{ __('My Account') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="font-semibold">{{ __('Hello, sign in') }}</a>
                        <a href="{{ route('register') }}" class="block text-sm text-white/85 underline">{{ __('Create an account') }}</a>
                    @endauth
                </div>
                <button type="button" data-drawer-close class="p-1" aria-label="{{ __('Close menu') }}"><x-icon name="x" class="w-6 h-6" /></button>
            </div>
            <nav class="flex-1 overflow-y-auto overscroll-contain">
                <p class="px-4 pt-4 pb-1 text-xs font-bold uppercase tracking-wide text-gray-500">{{ __('Departments') }}</p>
                @foreach($navDepartments as $dept)
                    <a href="{{ route('categories.show', $dept) }}" class="flex items-center gap-3 px-4 py-3 text-dark hover:bg-gray-50">
                        <span class="w-8 h-8 rounded-lg bg-primary-50 text-primary flex items-center justify-center"><x-icon :name="$dept->slug" class="w-4 h-4" /></span>
                        <span class="flex-1">{{ $dept->localized_name }}</span>
                        <x-icon name="chevron-right" class="w-4 h-4 text-gray-400 rtl:rotate-180" />
                    </a>
                @endforeach
                <div class="border-t border-gray-100 mt-2">
                    <p class="px-4 pt-4 pb-1 text-xs font-bold uppercase tracking-wide text-gray-500">{{ __('Shop') }}</p>
                    <a href="{{ route('deals') }}" class="flex items-center gap-3 px-4 py-3 font-semibold text-coral hover:bg-gray-50"><x-icon name="tag" class="w-5 h-5" />{{ __('Today\'s deals') }}</a>
                    <a href="{{ route('shop', ['sort' => 'newest']) }}" class="flex items-center gap-3 px-4 py-3 text-dark hover:bg-gray-50"><x-icon name="gift" class="w-5 h-5 text-gray-500" />{{ __('New arrivals') }}</a>
                    <a href="{{ route('shop') }}" class="flex items-center gap-3 px-4 py-3 text-dark hover:bg-gray-50"><x-icon name="squares" class="w-5 h-5 text-gray-500" />{{ __('Shop all products') }}</a>
                    <a href="{{ route('compare') }}" class="flex items-center gap-3 px-4 py-3 text-dark hover:bg-gray-50"><x-icon name="columns" class="w-5 h-5 text-gray-500" />{{ __('Compare products') }}</a>
                </div>
                <div class="border-t border-gray-100 mt-2">
                    <p class="px-4 pt-4 pb-1 text-xs font-bold uppercase tracking-wide text-gray-500">{{ __('Your account') }}</p>
                    <a href="{{ route('orders') }}" class="block px-4 py-3 text-dark hover:bg-gray-50">{{ __('My Orders') }}</a>
                    <a href="{{ route('wishlist') }}" class="block px-4 py-3 text-dark hover:bg-gray-50">{{ __('Wishlist') }}</a>
                    <a href="{{ route('order.track.form') }}" class="block px-4 py-3 text-dark hover:bg-gray-50">{{ __('Track Order') }}</a>
                    @auth
                        <a href="{{ route(auth()->user()->hasRole('seller') ? 'seller.dashboard' : 'seller.apply') }}" class="block px-4 py-3 text-dark hover:bg-gray-50">{{ auth()->user()->hasRole('seller') ? __('Seller Centre') : __('Sell on iruali') }}</a>
                        @if(auth()->user()->hasRole('admin'))
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-3 text-dark hover:bg-gray-50">{{ __('Admin Dashboard') }}</a>
                        @endif
                    @else
                        <a href="{{ route('seller.apply') }}" class="block px-4 py-3 text-dark hover:bg-gray-50">{{ __('Sell on iruali') }}</a>
                    @endauth
                    <a href="{{ route('help') }}" class="block px-4 py-3 text-dark hover:bg-gray-50">{{ __('Help centre') }}</a>
                </div>
            </nav>
            <div class="border-t border-gray-200 p-4 flex items-center justify-between gap-3">
                <form action="{{ route('locale.switch') }}" method="POST">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $otherLocale }}">
                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium" lang="{{ $otherLocale }}">
                        <x-icon name="globe" class="w-4 h-4" />{{ $otherLocale === 'dv' ? 'ދިވެހި' : 'English' }}
                    </button>
                </form>
                @auth
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-danger">{{ __('Sign Out') }}</button>
                    </form>
                @endauth
            </div>
        </aside>
    </div>

    <!-- Main Content -->
    <main id="main" class="min-h-[60vh]">
        @yield('content')
    </main>

    <!-- Why iruali -->
    <section class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 text-sm">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 shrink-0 rounded-full bg-primary-50 text-primary flex items-center justify-center"><x-icon name="truck" /></span>
                <span><span class="block font-semibold">{{ __('Delivered to your island') }}</span><span class="text-gray-500 text-xs">{{ __('Every atoll, by boat and air') }}</span></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 shrink-0 rounded-full bg-primary-50 text-primary flex items-center justify-center"><x-icon name="shield" /></span>
                <span><span class="block font-semibold">{{ __('Reviewed local sellers') }}</span><span class="text-gray-500 text-xs">{{ __('Every shop is checked before it goes live') }}</span></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 shrink-0 rounded-full bg-primary-50 text-primary flex items-center justify-center"><x-icon name="bank" /></span>
                <span><span class="block font-semibold">{{ __('Pay your way') }}</span><span class="text-gray-500 text-xs">{{ __('Cash on delivery or bank transfer') }}</span></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 shrink-0 rounded-full bg-primary-50 text-primary flex items-center justify-center"><x-icon name="gift" /></span>
                <span><span class="block font-semibold">{{ __('Points on every order') }}</span><span class="text-gray-500 text-xs">{{ __('Spend them on your next order') }}</span></span>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-footer text-white">
        <div class="border-b border-white/10">
            <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="flex-1">
                    <p class="font-display text-lg font-bold">{{ __('Get deals in your inbox') }}</p>
                    <p class="text-sm text-white/70">{{ __('New shops, new arrivals and the best deals, about once a week.') }}</p>
                </div>
                <form action="{{ route('newsletter.store') }}" method="POST" class="flex gap-2 w-full lg:w-auto">
                    @csrf
                    <label for="newsletter-email" class="sr-only">{{ __('Email address') }}</label>
                    <input id="newsletter-email" type="email" name="email" required placeholder="{{ __('Email address') }}" class="flex-1 lg:w-72 min-w-0 rounded-lg border-0 bg-white/10 px-4 py-2.5 text-white placeholder-white/50 focus:ring-2 focus:ring-sun">
                    <button type="submit" class="px-5 rounded-lg bg-sun text-sun-on font-semibold hover:bg-accent-400">{{ __('Subscribe') }}</button>
                </form>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 lg:px-6 py-10 grid grid-cols-2 lg:grid-cols-5 gap-8 text-sm">
            <div class="col-span-2 lg:col-span-1">
                <img src="/images/brand/iruali-logo-reversed.svg" alt="iruali" width="141" height="36" class="h-9 w-auto mb-4">
                <p class="text-white/70 mb-4">{{ __('Shops from every island, in one place. Buy from local sellers across the Maldives, delivered to your island.') }}</p>
                <ul class="space-y-2 text-white/85">
                    @if($contactPhone)
                        <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhone) }}" class="inline-flex items-center gap-2 hover:text-white" dir="ltr"><x-icon name="phone" class="w-4 h-4" />{{ $contactPhone }}</a></li>
                    @endif
                    @if($whatsappNumber = preg_replace('/[^0-9]/', '', (string) \App\Models\Setting::get('whatsapp_number')))
                        <li><a href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:text-white"><x-icon name="chat" class="w-4 h-4" />{{ __('Chat on WhatsApp') }}</a></li>
                    @endif
                    @if($contactEmail)
                        <li><a href="mailto:{{ $contactEmail }}" class="inline-flex items-center gap-2 hover:text-white"><x-icon name="mail" class="w-4 h-4" />{{ $contactEmail }}</a></li>
                    @endif
                </ul>
            </div>
            <div>
                <h3 class="font-semibold mb-3">{{ __('Shop') }}</h3>
                <ul class="space-y-2 text-white/70">
                    <li><a href="{{ route('shop') }}" class="hover:text-white">{{ __('Shop all products') }}</a></li>
                    <li><a href="{{ route('categories.index') }}" class="hover:text-white">{{ __('Departments') }}</a></li>
                    <li><a href="{{ route('deals') }}" class="hover:text-white">{{ __('Deals') }}</a></li>
                    <li><a href="{{ route('shop', ['sort' => 'newest']) }}" class="hover:text-white">{{ __('New arrivals') }}</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold mb-3">{{ __('Customer Service') }}</h3>
                <ul class="space-y-2 text-white/70">
                    <li><a href="{{ route('help') }}" class="hover:text-white">{{ __('Help centre') }}</a></li>
                    <li><a href="{{ route('order.track.form') }}" class="hover:text-white">{{ __('Track Order') }}</a></li>
                    <li><a href="{{ route('help') }}#delivery" class="hover:text-white">{{ __('Delivery & fees') }}</a></li>
                    <li><a href="{{ route('help') }}#returns" class="hover:text-white">{{ __('Returns & Exchanges') }}</a></li>
                    <li><a href="{{ route('help') }}#contact" class="hover:text-white">{{ __('Contact Us') }}</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold mb-3">{{ __('Your account') }}</h3>
                <ul class="space-y-2 text-white/70">
                    <li><a href="{{ route('account') }}" class="hover:text-white">{{ __('My Account') }}</a></li>
                    <li><a href="{{ route('orders') }}" class="hover:text-white">{{ __('Order History') }}</a></li>
                    <li><a href="{{ route('wishlist') }}" class="hover:text-white">{{ __('Wishlist') }}</a></li>
                    <li><a href="{{ route('cart') }}" class="hover:text-white">{{ __('Cart') }}</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold mb-3">{{ __('Sell on iruali') }}</h3>
                <p class="text-white/70 mb-3">{{ __('Reach customers on every inhabited island.') }}</p>
                <a href="{{ route('seller.apply') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sun text-sun-on font-semibold hover:bg-accent-400">{{ __('Open your shop') }}</a>
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="max-w-7xl mx-auto px-4 lg:px-6 py-5 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-white/60">
                <p>&copy; {{ date('Y') }} iruali. {{ __('All rights reserved.') }}</p>
                <p class="flex items-center gap-2"><x-icon name="bank" class="w-4 h-4" />{{ __('Cash on delivery') }} &middot; {{ __('Bank transfer') }}</p>
            </div>
        </div>
    </footer>

    <!-- Bottom tab bar (mobile) -->
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-50 bg-white border-t border-gray-200 shadow-[0_-2px_12px_rgba(15,42,58,0.08)]" style="padding-bottom: env(safe-area-inset-bottom);" aria-label="{{ __('Main') }}">
        <div class="grid grid-cols-5 h-16 text-[11px] font-medium">
            @php $tab = 'flex flex-col items-center justify-center gap-1'; @endphp
            <a href="{{ route('home') }}" class="{{ $tab }} {{ request()->routeIs('home') ? 'text-primary' : 'text-gray-600' }}"><x-icon name="home" class="w-6 h-6" />{{ __('Home') }}</a>
            <button type="button" data-drawer-open class="{{ $tab }} {{ request()->routeIs('categories.*') ? 'text-primary' : 'text-gray-600' }}"><x-icon name="squares" class="w-6 h-6" />{{ __('Departments') }}</button>
            <a href="{{ route('deals') }}" class="{{ $tab }} {{ request()->routeIs('deals') ? 'text-coral' : 'text-gray-600' }}"><x-icon name="tag" class="w-6 h-6" />{{ __('Deals') }}</a>
            <a href="{{ auth()->check() ? route('account') : route('login') }}" class="{{ $tab }} {{ request()->routeIs('account', 'orders*', 'login') ? 'text-primary' : 'text-gray-600' }}"><x-icon name="user" class="w-6 h-6" />{{ __('Account') }}</a>
            <a href="{{ route('cart') }}" class="{{ $tab }} {{ request()->routeIs('cart', 'checkout') ? 'text-primary' : 'text-gray-600' }}">
                <span class="relative"><x-icon name="cart" class="w-6 h-6" />
                    @if($cartCount > 0)<span class="absolute -top-1.5 -end-2.5 min-w-5 h-5 px-1 rounded-full bg-sun text-sun-on text-[11px] font-bold flex items-center justify-center">{{ $cartCount }}</span>@endif
                </span>{{ __('Cart') }}
            </a>
        </div>
    </nav>

    <!-- Compare tray -->
    @php $compareIds = session('compare', []); @endphp
    @if($compareIds && ! request()->routeIs('compare'))
        @php $compareItems = \App\Models\Product::whereIn('id', $compareIds)->with('mainImage')->get(); @endphp
        <div class="fixed z-40 end-4 bottom-20 lg:bottom-6 {{ request()->routeIs('products.show') ? 'hidden lg:block' : '' }}">
            <a href="{{ route('compare') }}" class="flex items-center gap-3 ps-2 pe-4 py-2 rounded-full bg-reef text-white shadow-2xl hover:bg-reef-night">
                <span class="flex -space-x-2 rtl:space-x-reverse">
                    @foreach($compareItems->take(4) as $c)
                        <img src="{{ $c->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="w-8 h-8 rounded-full border-2 border-reef object-cover bg-primary-50">
                    @endforeach
                </span>
                <span class="text-sm font-semibold">{{ __('Compare') }} ({{ $compareItems->count() }})</span>
            </a>
        </div>
    @endif

    <!-- Flash messages, shown as toasts by resources/js/notifications.js -->
    @if(session('notification'))
        <div id="session-notification" data-notification="{{ json_encode(session('notification')) }}" hidden></div>
    @endif
    @if((session('success') || session('error') || session('warning') || session('info')) && ! request()->routeIs('admin.*', 'seller.*'))
        <div id="legacy-notifications" hidden>
            @foreach(['success', 'error', 'warning', 'info'] as $type)
                @if(session($type) && is_string(session($type)))<div data-type="{{ $type }}" data-message="{{ session($type) }}"></div>@endif
            @endforeach
        </div>
    @endif

    <script>
        (function () {
            var drawer = document.querySelector('[data-drawer]');
            function setDrawer(open) {
                if (!drawer) return;
                drawer.classList.toggle('hidden', !open);
                document.documentElement.classList.toggle('overflow-hidden', open);
            }
            document.querySelectorAll('[data-drawer-open]').forEach(function (b) { b.addEventListener('click', function () { setDrawer(true); }); });
            document.querySelectorAll('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', function () { setDrawer(false); }); });

            var mega = document.querySelector('[data-mega]');
            if (mega) {
                var toggle = mega.querySelector('[data-mega-toggle]'), panel = mega.querySelector('[data-mega-panel]'), timer;
                function setMega(open) { panel.classList.toggle('hidden', !open); toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
                toggle.addEventListener('click', function () { setMega(panel.classList.contains('hidden')); });
                mega.addEventListener('mouseenter', function () { clearTimeout(timer); setMega(true); });
                mega.addEventListener('mouseleave', function () { timer = setTimeout(function () { setMega(false); }, 150); });
                document.addEventListener('click', function (e) { if (!mega.contains(e.target)) setMega(false); });
            }


            // Deal countdowns
            var timers = document.querySelectorAll('[data-countdown]');
            if (timers.length) {
                var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
                var tick = function () {
                    timers.forEach(function (t) {
                        var ms = new Date(t.dataset.countdown) - new Date();
                        if (ms <= 0) { t.textContent = ''; t.parentElement.querySelector('span').textContent = t.dataset.ended; return; }
                        var s = Math.floor(ms / 1000), d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
                        t.textContent = (d ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s % 60);
                    });
                };
                tick(); setInterval(tick, 1000);
            }

            // Search suggestions as you type
            var suggestUrl = {{ \Illuminate\Support\Js::from(route('search.suggest')) }};
            var labels = {{ \Illuminate\Support\Js::from(['products' => __('Products'), 'departments' => __('Departments'), 'brands' => __('Brands'), 'all' => __('See all results for “:q”'), 'none' => __('No suggestions')]) }};
            document.querySelectorAll('[data-suggest]').forEach(function (form) {
                var input = form.querySelector('input[name="q"]'), box = document.createElement('div'), timer, last = '';
                box.className = 'hidden fixed z-[70] bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden text-sm';
                box.setAttribute('role', 'listbox');
                document.body.appendChild(box);
                function place() {
                    var r = (form.querySelector('.rounded-lg') || form).getBoundingClientRect();
                    box.style.top = (r.bottom + 4) + 'px'; box.style.left = r.left + 'px'; box.style.width = r.width + 'px';
                }
                function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
                function render(data, q) {
                    box.innerHTML = '';
                    var any = false;
                    [['departments', 'squares'], ['brands', 'tag']].forEach(function (group) {
                        if (!data[group[0]].length) return;
                        any = true;
                        box.appendChild(el('p', 'px-4 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500', labels[group[0]]));
                        data[group[0]].forEach(function (item) {
                            var a = el('a', 'block px-4 py-2 hover:bg-primary-50 focus:bg-primary-50 outline-none', item.name); a.href = item.url; box.appendChild(a);
                        });
                    });
                    if (data.products.length) {
                        any = true;
                        box.appendChild(el('p', 'px-4 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500', labels.products));
                        data.products.forEach(function (p) {
                            var a = el('a', 'flex items-center gap-3 px-4 py-2 hover:bg-primary-50 focus:bg-primary-50 outline-none'); a.href = p.url;
                            var img = el('img', 'w-10 h-10 rounded object-cover bg-primary-50 shrink-0'); img.src = p.image; img.alt = '';
                            var name = el('span', 'flex-1 min-w-0 truncate', p.name);
                            var price = el('span', 'font-semibold shrink-0' + (p.in_stock ? '' : ' text-gray-400'), p.price);
                            a.append(img, name, price); box.appendChild(a);
                        });
                    }
                    if (!any) box.appendChild(el('p', 'px-4 py-3 text-gray-500', labels.none));
                    var all = el('a', 'block px-4 py-3 border-t border-gray-100 font-semibold text-primary hover:bg-primary-50', labels.all.replace(':q', q));
                    all.href = form.action + '?q=' + encodeURIComponent(q); box.appendChild(all);
                    place(); box.classList.remove('hidden');
                }
                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    var q = input.value.trim();
                    if (q.length < 2) { box.classList.add('hidden'); return; }
                    timer = setTimeout(function () {
                        last = q;
                        fetch(suggestUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (data) { if (q === last && input.value.trim() === q) render(data, q); })
                            .catch(function () {});
                    }, 180);
                });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown' && !box.classList.contains('hidden')) { e.preventDefault(); var f = box.querySelector('a'); if (f) f.focus(); }
                });
                box.addEventListener('keydown', function (e) {
                    var links = Array.prototype.slice.call(box.querySelectorAll('a')), i = links.indexOf(document.activeElement);
                    if (e.key === 'ArrowDown') { e.preventDefault(); (links[i + 1] || links[0]).focus(); }
                    if (e.key === 'ArrowUp') { e.preventDefault(); i <= 0 ? input.focus() : links[i - 1].focus(); }
                });
                document.addEventListener('click', function (e) { if (!form.contains(e.target) && !box.contains(e.target)) box.classList.add('hidden'); });
                window.addEventListener('resize', function () { if (!box.classList.contains('hidden')) place(); });
                window.addEventListener('scroll', function () { if (!box.classList.contains('hidden')) place(); }, { passive: true });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') box.classList.add('hidden'); });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                setDrawer(false);
                if (mega) mega.querySelector('[data-mega-panel]').classList.add('hidden');
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
