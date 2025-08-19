@extends('layouts.app')

@section('title', 'Categories - iruali')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Product Categories</h1>
        <p class="text-gray-600">Browse our products by category</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @foreach($categories as $category)
        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
            <div class="relative h-48 bg-gray-200">
                @if($category->image)
                    <img src="{{ $category->image }}" alt="{{ $category->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                @endif
                <div class="absolute inset-0 bg-black bg-opacity-40"></div>
                <div class="absolute bottom-4 left-4 right-4">
                    <h3 class="text-white text-xl font-semibold">{{ $category->name }}</h3>
                    <p class="text-white text-sm opacity-90">{{ $category->products->count() }} products</p>
                </div>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">{{ Str::limit($category->description, 100) }}</p>
                <a href="{{ route('categories.show', $category) }}" class="text-primary-600 hover:text-primary-700 font-semibold">
                    View Category →
                </a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection 