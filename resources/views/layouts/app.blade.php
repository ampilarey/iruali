<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo-meta :seo="$seo ?? null" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @if(app()->environment('production') && file_exists(public_path('build/assets/app-DRV1gWo1.css')))
        <link rel="stylesheet" href="{{ asset('build/assets/app-DRV1gWo1.css') }}">
        <script type="module" src="{{ asset('build/assets/app-DNxiirP_.js') }}"></script>
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    
    @stack('styles')
</head>
<body class="font-sans antialiased">
    <!-- Header -->
    <header class="bg-white shadow sticky top-0 z-30">
        <!-- Desktop Header -->
        <div class="hidden lg:flex max-w-7xl mx-auto px-4 py-3 items-center justify-between">
            <!-- Left: Logo -->
            <div class="flex items-center min-w-[120px]">
                <a href="/" class="text-2xl font-bold text-primary-600">iruali</a>
            </div>
            <!-- Center: Navigation -->
            <nav class="flex-1 flex justify-center space-x-8">
                <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">{{ __('app.home') }}</a>
                <a href="{{ route('shop') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">{{ __('app.shop') }}</a>
                <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">{{ __('app.products') }}</a>
                <a href="{{ route('categories.index') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">{{ __('app.categories') }}</a>
            </nav>
            <!-- Right: Search, Cart, User -->
            <div class="flex items-center space-x-6 min-w-[400px] justify-end">
                <!-- Search -->
                <form action="{{ route('search') }}" method="GET" class="relative w-48">
                    <input type="text" name="q" placeholder="Search products..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm">
                    <button type="submit" class="absolute left-3 top-2.5 text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                </form>
                <!-- Language Switcher -->
                <form action="{{ route('locale.switch') }}" method="GET" class="flex items-center">
                    <input type="hidden" name="redirect" value="{{ url()->current() }}">
                    <select name="locale" onchange="this.form.submit()" class="bg-white text-gray-700 text-sm border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>EN</option>
                        <option value="dv" {{ app()->getLocale() == 'dv' ? 'selected' : '' }}>ދިވެހިބަސް</option>
                    </select>
                </form>
                <!-- Cart -->
                <a href="{{ route('cart') }}" class="relative flex items-center px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 ml-4">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path></svg>
                    @if(auth()->check() && auth()->user()->cart && auth()->user()->cart->item_count > 0)
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">{{ auth()->user()->cart->item_count }}</span>
                    @endif
                </a>
                <!-- User -->
                @auth
                    <div class="relative group ml-4">
                        <button class="flex items-center space-x-2 px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">
                            <img class="w-8 h-8 rounded-full" src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . auth()->user()->name }}" alt="{{ auth()->user()->name }}">
                            <span class="text-sm font-medium text-gray-700">{{ auth()->user()->name }}</span>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl py-2 z-50 opacity-0 group-hover:opacity-100 group-hover:visible invisible transition-all duration-200 border border-gray-200">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center space-x-3">
                                <img class="w-10 h-10 rounded-full" src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . auth()->user()->name }}" alt="{{ auth()->user()->name }}">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-blue-600 font-semibold">{{ __('app.loyalty_points') }}: {{ auth()->user()->loyalty_points ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="py-2">
                                <a href="{{ route('account') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">{{ __('app.account') }}</a>
                                <a href="{{ route('wishlist.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">{{ __('app.wishlist') }}</a>
                                <a href="{{ route('orders.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">{{ __('app.orders') }}</a>
                            </div>
                            <div class="border-t border-gray-100 pt-2">
                                <form action="{{ route('logout') }}" method="POST" class="block">@csrf
                                    <button type="submit" class="w-full text-left flex items-center px-4 py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50">{{ __('app.logout') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center space-x-3 ml-4">
                        <a href="{{ route('login') }}" class="text-gray-700 px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm font-medium">{{ __('app.login') }}</a>
                        <a href="{{ route('register') }}" class="text-white px-3 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-sm font-medium">{{ __('app.register') }}</a>
                    </div>
                @endauth
            </div>
        </div>
        <!-- Mobile Header -->
        <div class="lg:hidden px-4 py-3 flex items-center justify-between max-w-7xl mx-auto">
            <!-- Hamburger -->
            <button id="mobile-menu-button" class="text-gray-700 hover:text-primary-600 focus:outline-none p-2 -ml-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <!-- Logo -->
            <a href="/" class="text-lg sm:text-xl font-bold text-primary-600">iruali</a>
            <!-- Right side: Language + Cart -->
            <div class="flex items-center space-x-2">
                <!-- Language Switcher -->
                <form action="{{ route('locale.switch') }}" method="GET" class="flex items-center">
                    <input type="hidden" name="redirect" value="{{ url()->current() }}">
                    <select name="locale" onchange="this.form.submit()" class="bg-white text-gray-700 text-xs border border-gray-300 rounded px-1 py-1 focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                        <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>EN</option>
                        <option value="dv" {{ app()->getLocale() == 'dv' ? 'selected' : '' }}>ދިވެހިބަސް</option>
                    </select>
                </form>
                <!-- Cart -->
                <a href="{{ route('cart') }}" class="relative flex items-center p-2 rounded-lg bg-gray-100 hover:bg-gray-200">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path></svg>
                    @if(auth()->check() && auth()->user()->cart && auth()->user()->cart->item_count > 0)
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">{{ auth()->user()->cart->item_count }}</span>
                    @endif
                </a>
            </div>
        </div>
        <!-- Mobile Search Bar -->
        <div class="lg:hidden px-4 pb-3">
            <form action="{{ route('search') }}" method="GET" class="relative flex">
                <input type="text" name="q" placeholder="Search products..." class="flex-1 pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-base">
                <button type="submit" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
            </form>
        </div>
        <!-- Mobile Side Drawer -->
        <div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden">
            <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out -translate-x-full" id="mobile-menu">
                <div class="flex items-center justify-between p-4 border-b">
                    <span class="text-xl font-bold text-primary-600">iruali</span>
                    <button id="close-mobile-menu" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <nav class="p-4 space-y-1">
                    <a href="{{ route('home') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.home') }}</a>
                    <a href="{{ route('shop') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.shop') }}</a>
                    <a href="{{ route('products.index') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.products') }}</a>
                    <a href="{{ route('categories.index') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.categories') }}</a>
                    <a href="{{ route('cart') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.cart') }}</a>
                </nav>
                <div class="p-4 border-t mt-4">
                    @auth
                        <div class="flex items-center space-x-3 mb-4 p-3 bg-gray-50 rounded-lg">
                            <img class="w-10 h-10 rounded-full" src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . auth()->user()->name }}" alt="{{ auth()->user()->name }}">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-blue-600">Points: {{ auth()->user()->loyalty_points ?? 0 }}</p>
                            </div>
                        </div>
                        <a href="{{ route('wishlist.index') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.wishlist') }}</a>
                        <a href="{{ route('orders.index') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.orders') }}</a>
                        <a href="{{ route('account') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg text-base">{{ __('app.account') }}</a>
                        <form action="{{ route('logout') }}" method="POST" class="mt-4">@csrf
                            <button type="submit" class="w-full text-left px-4 py-3 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg flex items-center text-base">{{ __('app.logout') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block px-4 py-3 text-gray-700 hover:text-primary-600 hover:bg-gray-100 rounded-lg mb-3 text-base">{{ __('app.login') }}</a>
                        <a href="{{ route('register') }}" class="block px-4 py-3 bg-primary-600 text-white rounded-lg text-center hover:bg-primary-700 text-base">{{ __('app.register') }}</a>
                    @endauth
                </div>
                <!-- Language Switcher -->
                <form action="{{ route('locale.switch') }}" method="GET" class="flex items-center p-4 border-t mt-4">
                    <input type="hidden" name="redirect" value="{{ url()->current() }}">
                    <select name="locale" onchange="this.form.submit()" class="bg-transparent text-gray-700 text-sm border border-gray-300 rounded px-2 py-1">
                        <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>English</option>
                        <option value="dv" {{ app()->getLocale() == 'dv' ? 'selected' : '' }}>ދިވެހިބަސް</option>
                    </select>
                </form>
            </div>
        </div>
        <!-- Mobile Menu JS -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const mobileMenuButton = document.getElementById('mobile-menu-button');
                const mobileMenu = document.getElementById('mobile-menu');
                const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
                const closeMobileMenu = document.getElementById('close-mobile-menu');
                function openMobileMenu() {
                    mobileMenuOverlay.classList.remove('hidden');
                    setTimeout(() => { mobileMenu.classList.remove('-translate-x-full'); }, 10);
                }
                function closeMobileMenuFunc() {
                    mobileMenu.classList.add('-translate-x-full');
                    setTimeout(() => { mobileMenuOverlay.classList.add('hidden'); }, 300);
                }
                mobileMenuButton.addEventListener('click', openMobileMenu);
                closeMobileMenu.addEventListener('click', closeMobileMenuFunc);
                mobileMenuOverlay.addEventListener('click', closeMobileMenuFunc);
            });
        </script>
    </header>

    <!-- Notification System -->
    @if(session('notification'))
        <div id="session-notification" data-notification='@json(session('notification'))' style="display: none;"></div>
    @endif

    @if(session('notifications'))
        <div id="session-notifications" data-notifications='@json(session('notifications'))' style="display: none;"></div>
    @endif

    <!-- Legacy Session Messages (for backward compatibility) -->
    @if(session('success') || session('error') || session('warning') || session('info'))
        <div id="legacy-notifications" style="display: none;">
            @if(session('success'))
                <div data-type="success" data-message="{{ session('success') }}"></div>
            @endif
            @if(session('error'))
                <div data-type="error" data-message="{{ session('error') }}"></div>
            @endif
            @if(session('warning'))
                <div data-type="warning" data-message="{{ session('warning') }}"></div>
            @endif
            @if(session('info'))
                <div data-type="info" data-message="{{ session('info') }}"></div>
            @endif
        </div>
    @endif

    <!-- Main content -->
    <main class="main-content max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-green-900 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">iruali</h3>
                    <p class="text-gray-300 text-sm">Maldives' premier multi-vendor e-commerce platform. Shop the latest products, flash sales, and more!</p>
                </div>
                <div>
                    <h4 class="text-md font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('home') }}" class="text-gray-300 hover:text-white">Home</a></li>
                        <li><a href="{{ route('shop') }}" class="text-gray-300 hover:text-white">Shop</a></li>
                        <li><a href="{{ route('products.index') }}" class="text-gray-300 hover:text-white">Products</a></li>
                        <li><a href="{{ route('categories.index') }}" class="text-gray-300 hover:text-white">Categories</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-md font-semibold mb-4">Customer Service</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="text-gray-300 hover:text-white">Contact Us</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">Shipping Info</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">Returns</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-md font-semibold mb-4">Connect With Us</h4>
                    <div class="flex space-x-4">
                        <a href="https://facebook.com/iruali" target="_blank" aria-label="Facebook" class="text-gray-300 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.522-4.478-10-10-10S2 6.478 2 12c0 5 3.657 9.127 8.438 9.877v-6.987h-2.54v-2.89h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.242 0-1.632.771-1.632 1.562v1.875h2.773l-.443 2.89h-2.33v6.987C18.343 21.127 22 17 22 12z"/></svg>
                        </a>
                        <a href="https://twitter.com/iruali" target="_blank" aria-label="Twitter" class="text-gray-300 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557a9.93 9.93 0 01-2.828.775 4.932 4.932 0 002.165-2.724c-.951.564-2.005.974-3.127 1.195a4.92 4.92 0 00-8.384 4.482C7.691 8.095 4.066 6.13 1.64 3.161c-.542.929-.855 2.01-.855 3.17 0 2.188 1.115 4.116 2.813 5.247a4.904 4.904 0 01-2.229-.616c-.054 2.281 1.581 4.415 3.949 4.89a4.936 4.936 0 01-2.224.084c.627 1.956 2.444 3.377 4.6 3.417A9.867 9.867 0 010 19.54a13.94 13.94 0 007.548 2.209c9.058 0 14.009-7.513 14.009-14.009 0-.213-.005-.425-.014-.636A10.012 10.012 0 0024 4.557z"/></svg>
                        </a>
                        <a href="https://instagram.com/iruali" target="_blank" aria-label="Instagram" class="text-gray-300 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.366.062 2.633.334 3.608 1.308.974.974 1.246 2.242 1.308 3.608.058 1.266.07 1.646.07 4.85s-.012 3.584-.07 4.85c-.062 1.366-.334 2.633-1.308 3.608-.974.974-2.242 1.246-3.608 1.308-1.266.058-1.646.07-4.85.07s-3.584-.012-4.85-.07c-1.366-.062-2.633-.334-3.608-1.308-.974-.974-1.246-2.242-1.308-3.608C2.175 15.647 2.163 15.267 2.163 12s.012-3.584.07-4.85c.062-1.366.334-2.633 1.308-3.608.974-.974 2.242-1.246 3.608-1.308C8.416 2.175 8.796 2.163 12 2.163zm0-2.163C8.741 0 8.332.013 7.052.072 5.775.131 4.602.425 3.635 1.392 2.668 2.359 2.374 3.532 2.315 4.809.013 8.332 0 8.741 0 12c0 3.259.013 3.668.072 4.948.059 1.277.353 2.45 1.32 3.417.967.967 2.14 1.261 3.417 1.32C8.332 23.987 8.741 24 12 24s3.668-.013 4.948-.072c1.277-.059 2.45-.353 3.417-1.32.967-.967 1.261-2.14 1.32-3.417.059-1.28.072-1.689.072-4.948 0-3.259-.013-3.668-.072-4.948-.059-1.277-.353-2.45-1.32-3.417-.967-.967-2.14-1.261-3.417-1.32C15.668.013 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zm0 10.162a3.999 3.999 0 110-7.998 3.999 3.999 0 010 7.998zm6.406-11.845a1.44 1.44 0 11-2.88 0 1.44 1.44 0 012.88 0z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-green-800 text-center text-gray-300 text-sm">
                <p>&copy; {{ date('Y') }} iruali. All rights reserved.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html> 