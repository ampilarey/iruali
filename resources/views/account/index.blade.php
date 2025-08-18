@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-8">My Account</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center space-x-4 mb-6">
                        <img class="w-16 h-16 rounded-full" src="{{ $user->avatar ?? 'https://ui-avatars.com/api/?name=' . $user->name }}" alt="{{ $user->name }}">
                        <div>
                            <h2 class="text-lg font-semibold text-dark">{{ $user->name }}</h2>
                            <p class="text-sm text-gray-500">{{ $user->email }}</p>
                        </div>
                    </div>
                    
                    <nav class="space-y-2">
                        <a href="{{ route('account') }}" class="block px-4 py-2 text-primary bg-primary/10 rounded-lg font-medium">Profile</a>
                        <a href="{{ route('orders') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">My Orders</a>
                        <a href="{{ route('wishlist') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">Wishlist</a>
                        <a href="#" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">Addresses</a>
                        <a href="#" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">Settings</a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h3 class="text-xl font-semibold text-dark mb-6">Profile Information</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <p class="text-gray-900">{{ $user->name }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <p class="text-gray-900">{{ $user->email }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <p class="text-gray-900">{{ $user->phone ?? 'Not provided' }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <p class="text-gray-900">
                                {{ $user->address ?? 'Not provided' }}
                                @if($user->city)
                                    <br>{{ $user->city }}, {{ $user->state }} {{ $user->postal_code }}
                                @endif
                                @if($user->country)
                                    <br>{{ $user->country }}
                                @endif
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Member Since</label>
                            <p class="text-gray-900">{{ $user->created_at->format('F j, Y') }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Loyalty Points</label>
                            <p class="text-gray-900">{{ $user->loyalty_points ?? 0 }} points</p>
                        </div>
                    </div>
                    
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <a href="#" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                            Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 