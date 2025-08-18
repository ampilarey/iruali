<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicon and Touch Icons -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v={{ time() }}">
    <link rel="manifest" href="/site.webmanifest">

    <x-seo-meta :seo="$seo ?? null" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @stack('styles')
</head>
<body class="font-sans antialiased bg-background text-dark">
    <!-- Top Banner -->
    <div class="bg-primary text-white text-center py-2 px-4 text-sm font-medium">
        <div class="container mx-auto">
            <span>🎉 Free Shipping on Orders Over $49 | Expert Support Available 24/7</span>
        </div>
    </div>

    <!-- Sticky Header -->
    <header class="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-100">
        <!-- Main Header -->
        <div class="bg-white">
            <div class="container mx-auto px-4">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <a href="/" class="text-2xl font-bold text-primary hover:text-primary/80 transition-colors">
                            iruali
                        </a>
                    </div>

                    <!-- Search Bar -->
                    <div class="flex-1 max-w-2xl mx-8">
                        <form action="{{ route('search') }}" method="GET" class="relative">
                            <input type="text" name="q" placeholder="Search for products, brands, and more..." 
                                   class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-sm bg-gray-50 hover:bg-white transition-colors">
                            <button type="submit" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- Right Side Actions -->
                    <div class="flex items-center space-x-6">
                        <!-- Account -->
                        @auth
                            <div class="relative group">
                                <button class="flex items-center space-x-2 text-dark hover:text-primary transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span class="text-sm font-medium">{{ auth()->user()->name }}</span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-xl py-2 z-50 opacity-0 group-hover:opacity-100 group-hover:visible invisible transition-all duration-200 border border-gray-200">
                                    <div class="px-4 py-3 border-b border-gray-100">
                                        <p class="text-sm font-medium text-dark">{{ auth()->user()->name }}</p>
                                        <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                                    </div>
                                    <div class="py-2">
                                        <a href="{{ route('account') }}" class="flex items-center px-4 py-2 text-sm text-dark hover:bg-gray-50 transition-colors">
                                            <svg class="w-3 h-3 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            My Account
                                        </a>
                                        <a href="{{ route('orders') }}" class="flex items-center px-4 py-2 text-sm text-dark hover:bg-gray-50 transition-colors">
                                            <svg class="w-3 h-3 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            My Orders
                                        </a>
                                        <a href="{{ route('wishlist') }}" class="flex items-center px-4 py-2 text-sm text-dark hover:bg-gray-50 transition-colors">
                                            <svg class="w-3 h-3 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                            </svg>
                                            Wishlist
                                        </a>
                                    </div>
                                    <div class="border-t border-gray-100 pt-2">
                                        <form action="{{ route('logout') }}" method="POST" class="block">
                                            @csrf
                                            <button type="submit" class="w-full text-left flex items-center px-4 py-2 text-sm text-danger hover:text-danger/80 hover:bg-red-50 transition-colors">
                                                <svg class="w-3 h-3 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                                </svg>
                                                Sign Out
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="flex items-center space-x-2 text-dark hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span class="text-sm font-medium">Sign In</span>
                            </a>
                        @endauth

                        <!-- Cart -->
                        <a href="{{ route('cart') }}" class="relative flex items-center space-x-2 text-dark hover:text-primary transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                            </svg>
                            <span class="text-sm font-medium">Cart</span>
                            @if(auth()->check() && auth()->user()->cart && auth()->user()->cart->item_count > 0)
                                <span class="absolute -top-2 -right-2 w-5 h-5 bg-danger text-white text-xs rounded-full flex items-center justify-center font-bold">
                                    {{ auth()->user()->cart->item_count }}
                                </span>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mega Menu -->
        <nav class="bg-gray-50 border-b border-gray-200">
            <div class="container mx-auto px-4">
                <div class="flex items-center space-x-8 h-12">
                    <a href="{{ route('home') }}" class="text-dark hover:text-primary transition-colors px-3 py-2 rounded-md text-sm font-medium">
                        Home
                    </a>
                    <a href="{{ route('shop') }}" class="text-dark hover:text-primary transition-colors px-3 py-2 rounded-md text-sm font-medium">
                        Shop
                    </a>
                    <a href="{{ route('products.index') }}" class="text-dark hover:text-primary transition-colors px-3 py-2 rounded-md text-sm font-medium">
                        Products
                    </a>
                    <a href="{{ route('categories.index') }}" class="text-dark hover:text-primary transition-colors px-3 py-2 rounded-md text-sm font-medium">
                        Categories
                    </a>
                    <a href="#" class="text-dark hover:text-primary transition-colors px-3 py-2 rounded-md text-sm font-medium">
                        Deals
                    </a>
                    <a href="#" class="text-dark hover:text-primary transition-colors px-3 py-2 rounded-md text-sm font-medium">
                        Support
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Mobile Header (Hidden on Desktop) -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white shadow-sm border-b border-gray-100">
        <div class="flex items-center justify-between px-4 py-3">
            <button id="mobile-menu-button" class="text-dark hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            
            <a href="/" class="text-xl font-bold text-primary">iruali</a>
            
            <div class="flex items-center space-x-4">
                <a href="{{ route('cart') }}" class="relative text-dark hover:text-primary transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                    </svg>
                    @if(auth()->check() && auth()->user()->cart && auth()->user()->cart->item_count > 0)
                        <span class="absolute -top-2 -right-2 w-5 h-5 bg-danger text-white text-xs rounded-full flex items-center justify-center font-bold">
                            {{ auth()->user()->cart->item_count }}
                        </span>
                    @endif
                </a>
            </div>
        </div>
        
        <!-- Mobile Search -->
        <div class="px-4 pb-3">
            <form action="{{ route('search') }}" method="GET" class="relative">
                <input type="text" name="q" placeholder="Search products..." 
                       class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base bg-gray-50">
                <button type="submit" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden">
        <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out -translate-x-full" id="mobile-menu">
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <span class="text-xl font-bold text-primary">iruali</span>
                <button id="close-mobile-menu" class="text-gray-500 hover:text-dark transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <nav class="p-4 space-y-1">
                <a href="{{ route('home') }}" class="block px-4 py-3 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Home</a>
                <a href="{{ route('shop') }}" class="block px-4 py-3 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Shop</a>
                <a href="{{ route('products.index') }}" class="block px-4 py-3 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Products</a>
                <a href="{{ route('categories.index') }}" class="block px-4 py-3 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Categories</a>
                <a href="#" class="block px-4 py-3 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Deals</a>
                <a href="#" class="block px-4 py-3 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Support</a>
            </nav>
            <div class="p-4 border-t border-gray-200">
                @auth
                    <div class="flex items-center space-x-3 mb-4 p-3 bg-gray-50 rounded-lg">
                        <img class="w-10 h-10 rounded-full" src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . auth()->user()->name }}" alt="{{ auth()->user()->name }}">
                        <div>
                            <p class="text-sm font-medium text-dark">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <a href="{{ route('account') }}" class="block px-4 py-2 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">My Account</a>
                    <a href="{{ route('orders') }}" class="block px-4 py-2 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">My Orders</a>
                    <a href="{{ route('wishlist') }}" class="block px-4 py-2 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Wishlist</a>
                    <form action="{{ route('logout') }}" method="POST" class="block">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-danger hover:text-danger/80 hover:bg-red-50 rounded-lg text-base transition-colors">
                            Sign Out
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="block px-4 py-2 text-dark hover:text-primary hover:bg-gray-50 rounded-lg text-base transition-colors">Sign In</a>
                    <a href="{{ route('register') }}" class="block px-4 py-2 text-primary hover:text-primary/80 hover:bg-primary/10 rounded-lg text-base transition-colors">Sign Up</a>
                @endauth
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="min-h-screen pt-0 lg:pt-0">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-footer text-white">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">iruali</h3>
                    <p class="text-gray-300 mb-4">Your trusted source for premium products and expert support. Shop with confidence knowing you're getting the best deals and service.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22 12c0-5.522-4.478-10-10-10S2 6.478 2 12c0 5 3.657 9.127 8.438 9.877v-6.987h-2.54v-2.89h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.242 0-1.632.771-1.632 1.562v1.875h2.773l-.443 2.89h-2.33v6.987C18.343 21.127 22 17 22 12z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.746-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24.009c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001 12.017.001z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 1.006.128.832.941z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="{{ route('account') }}" class="text-gray-300 hover:text-white transition-colors">My Account</a></li>
                        <li><a href="{{ route('orders') }}" class="text-gray-300 hover:text-white transition-colors">Order History</a></li>
                        <li><a href="{{ route('wishlist') }}" class="text-gray-300 hover:text-white transition-colors">Wishlist</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Track Order</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Returns</a></li>
                    </ul>
                </div>

                <!-- Customer Service -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Contact Us</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Shipping Info</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Returns & Exchanges</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Size Guide</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">FAQ</a></li>
                    </ul>
                </div>

                <!-- Newsletter -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Stay Updated</h3>
                    <p class="text-gray-300 mb-4">Subscribe to our newsletter for the latest products, deals, and updates.</p>
                    <form class="space-y-3">
                        <input type="email" placeholder="Enter your email" 
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-primary focus:border-primary">
                        <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg transition-colors">
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bottom Footer -->
        <div class="border-t border-gray-700">
            <div class="container mx-auto px-4 py-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-300 text-sm">&copy; 2024 iruali. All rights reserved.</p>
                    <div class="flex space-x-6 mt-4 md:mt-0">
                        <a href="#" class="text-gray-300 hover:text-white text-sm transition-colors">Privacy Policy</a>
                        <a href="#" class="text-gray-300 hover:text-white text-sm transition-colors">Terms of Service</a>
                        <a href="#" class="text-gray-300 hover:text-white text-sm transition-colors">Cookie Policy</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Cart Icon (Mobile Only) -->
    <a href="{{ route('cart') }}"
       class="fixed bottom-5 right-5 z-50 bg-primary shadow-lg rounded-full w-12 h-12 flex items-center justify-center border-2 border-white lg:hidden animate-bounce-gentle"
       style="box-shadow: 0 4px 24px rgba(16, 185, 129, 0.3);">
      <div class="relative w-6 h-6 flex items-center justify-center">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
        </svg>
        @if(auth()->check() && auth()->user()->cart && auth()->user()->cart->item_count > 0)
          <span class="absolute -top-1 -right-1 w-4 h-4 bg-danger text-white text-xs rounded-full flex items-center justify-center font-bold">
            {{ auth()->user()->cart->item_count }}
          </span>
        @endif
      </div>
    </a>

    <!-- JavaScript -->
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu-overlay').classList.remove('hidden');
            document.getElementById('mobile-menu').classList.remove('-translate-x-full');
        });

        document.getElementById('close-mobile-menu').addEventListener('click', function() {
            document.getElementById('mobile-menu-overlay').classList.add('hidden');
            document.getElementById('mobile-menu').classList.add('-translate-x-full');
        });

        // Close mobile menu when clicking overlay
        document.getElementById('mobile-menu-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
                document.getElementById('mobile-menu').classList.add('-translate-x-full');
            }
        });

        // Close mobile menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('mobile-menu-overlay').classList.add('hidden');
                document.getElementById('mobile-menu').classList.add('-translate-x-full');
            }
        });
    </script>

    @stack('scripts')
</body>
</html> 