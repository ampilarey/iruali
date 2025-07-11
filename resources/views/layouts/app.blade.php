<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', 'iruali is a modern, multi-vendor e-commerce platform for the Maldives. Shop the latest products, flash sales, and more!')">
    <meta name="keywords" content="iruali, e-commerce, Maldives, shop, online, multi-vendor, flash sale, deals, products">
    <meta property="og:title" content="@yield('title', config('app.name'))">
    <meta property="og:description" content="@yield('meta_description', 'iruali is a modern, multi-vendor e-commerce platform for the Maldives. Shop the latest products, flash sales, and more!')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', config('app.name'))">
    <meta name="twitter:description" content="@yield('meta_description', 'iruali is a modern, multi-vendor e-commerce platform for the Maldives. Shop the latest products, flash sales, and more!')">
    <meta name="twitter:image" content="{{ asset('images/og-image.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @stack('styles')
</head>
<body class="bg-[#388e3c] font-sans antialiased">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-4 px-4 flex justify-between items-center">
            <div>
                <a href="/" class="text-2xl font-bold text-primary-600">iruali</a>
            </div>
            <div class="flex items-center space-x-6">
                <!-- Removed island selection dropdown -->
                <!-- Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">
                        {{ __('app.home') }}
                    </a>
                    <a href="{{ route('shop') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">
                        {{ __('app.shop') }}
                    </a>
                    <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">
                        {{ __('app.products') }}
                    </a>
                    <a href="{{ route('categories.index') }}" class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium">
                        {{ __('app.categories') }}
                    </a>
                </nav>

                <!-- Right side -->
                <div class="flex items-center space-x-4">
                    <!-- Search -->
                    <div class="relative">
                        <form action="{{ route('search') }}" method="GET" class="flex">
                            <input type="text" name="q" placeholder="Search products..." 
                                   class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <button type="submit" class="absolute left-3 top-2.5 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- Cart -->
                    <a href="{{ route('cart') }}" class="relative text-gray-700 hover:text-primary-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                        </svg>
                        @if(auth()->check() && auth()->user()->cart && auth()->user()->cart->item_count > 0)
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                {{ auth()->user()->cart->item_count }}
                            </span>
                        @endif
                    </a>

                    <!-- Language Switcher -->
                    <form action="{{ route('locale.switch') }}" method="GET" class="mr-4">
                        <input type="hidden" name="redirect" value="{{ url()->current() }}">
                        <select name="locale" onchange="this.form.submit()" class="border-gray-300 rounded-md text-sm px-2 py-1">
                            <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>English</option>
                            <option value="dv" {{ app()->getLocale() == 'dv' ? 'selected' : '' }}>ދިވެހިބަސް</option>
                        </select>
                    </form>

                    <!-- User menu -->
                    @auth
                        <div class="relative group">
                            <button class="flex items-center space-x-2 text-gray-700 hover:text-primary-600">
                                <img class="w-8 h-8 rounded-full" src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . auth()->user()->name }}" alt="{{ auth()->user()->name }}">
                                <span class="text-sm font-medium">{{ auth()->user()->name }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                                <div class="px-4 py-2 text-xs text-blue-700 font-semibold border-b border-gray-100">
                                    {{ __('Loyalty Points:') }} {{ auth()->user()->loyalty_points ?? 0 }}
                                </div>
                                <a href="{{ route('wishlist.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    My Wishlist
                                </a>
                                <a href="{{ route('orders.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    My Orders
                                </a>
                                <hr class="my-1">
                                <form action="{{ route('logout') }}" method="POST" class="block">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center space-x-4">
                            <a href="{{ route('login') }}" class="text-gray-700 hover:text-primary-600 text-sm font-medium">
                                {{ __('app.login') }}
                            </a>
                            <a href="{{ route('register') }}" class="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700">
                                {{ __('app.register') }}
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <span class="font-bold text-lg">iruali</span> &copy; {{ date('Y') }}
            </div>
            <div class="flex space-x-4">
                <a href="https://facebook.com/iruali" target="_blank" aria-label="Facebook">
                    <svg class="w-6 h-6" fill="#1877F3" viewBox="0 0 24 24"><path d="M22 12c0-5.522-4.478-10-10-10S2 6.478 2 12c0 5 3.657 9.127 8.438 9.877v-6.987h-2.54v-2.89h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.242 0-1.632.771-1.632 1.562v1.875h2.773l-.443 2.89h-2.33v6.987C18.343 21.127 22 17 22 12z"/></svg>
                </a>
                <a href="https://twitter.com/iruali" target="_blank" aria-label="Twitter">
                    <svg class="w-6 h-6" fill="#1DA1F2" viewBox="0 0 24 24"><path d="M24 4.557a9.93 9.93 0 01-2.828.775 4.932 4.932 0 002.165-2.724c-.951.564-2.005.974-3.127 1.195a4.92 4.92 0 00-8.384 4.482C7.691 8.095 4.066 6.13 1.64 3.161c-.542.929-.855 2.01-.855 3.17 0 2.188 1.115 4.116 2.813 5.247a4.904 4.904 0 01-2.229-.616c-.054 2.281 1.581 4.415 3.949 4.89a4.936 4.936 0 01-2.224.084c.627 1.956 2.444 3.377 4.6 3.417A9.867 9.867 0 010 19.54a13.94 13.94 0 007.548 2.209c9.058 0 14.009-7.513 14.009-14.009 0-.213-.005-.425-.014-.636A10.012 10.012 0 0024 4.557z"/></svg>
                </a>
                <a href="https://instagram.com/iruali" target="_blank" aria-label="Instagram">
                    <svg class="w-6 h-6" fill="#E4405F" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.366.062 2.633.334 3.608 1.308.974.974 1.246 2.242 1.308 3.608.058 1.266.07 1.646.07 4.85s-.012 3.584-.07 4.85c-.062 1.366-.334 2.633-1.308 3.608-.974.974-2.242 1.246-3.608 1.308-1.266.058-1.646.07-4.85.07s-3.584-.012-4.85-.07c-1.366-.062-2.633-.334-3.608-1.308-.974-.974-1.246-2.242-1.308-3.608C2.175 15.647 2.163 15.267 2.163 12s.012-3.584.07-4.85c.062-1.366.334-2.633 1.308-3.608.974-.974 2.242-1.246 3.608-1.308C8.416 2.175 8.796 2.163 12 2.163zm0-2.163C8.741 0 8.332.013 7.052.072 5.775.131 4.602.425 3.635 1.392 2.668 2.359 2.374 3.532 2.315 4.809.013 8.332 0 8.741 0 12c0 3.259.013 3.668.072 4.948.059 1.277.353 2.45 1.32 3.417.967.967 2.14 1.261 3.417 1.32C8.332 23.987 8.741 24 12 24s3.668-.013 4.948-.072c1.277-.059 2.45-.353 3.417-1.32.967-.967 1.261-2.14 1.32-3.417.059-1.28.072-1.689.072-4.948 0-3.259-.013-3.668-.072-4.948-.059-1.277-.353-2.45-1.32-3.417-.967-.967-2.14-1.261-3.417-1.32C15.668.013 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zm0 10.162a3.999 3.999 0 110-7.998 3.999 3.999 0 010 7.998zm6.406-11.845a1.44 1.44 0 11-2.88 0 1.44 1.44 0 012.88 0z"/></svg>
                </a>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html> 