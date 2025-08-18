@props(['category'])

<div class="group bg-white rounded-lg shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 overflow-hidden">
    <!-- Category Image/Icon -->
    <div class="relative aspect-square overflow-hidden bg-gradient-to-br from-primary/10 to-accent/10">
        @if($category->image)
            <img src="{{ $category->image }}" 
                 alt="{{ $category->name }}" 
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <!-- Default Category Icon -->
            <div class="w-full h-full flex items-center justify-center">
                <svg class="w-12 h-12 text-primary group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
        @endif

        <!-- Overlay -->
        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300"></div>
    </div>

    <!-- Category Info -->
    <div class="p-4 text-center">
        <h3 class="font-semibold text-dark text-lg mb-2 group-hover:text-primary transition-colors">
            <a href="{{ route('categories.show', $category->slug) }}" class="hover:underline">
                {{ $category->name }}
            </a>
        </h3>
        
        @if($category->description)
            <p class="text-gray-600 text-sm mb-3 line-clamp-2">{{ $category->description }}</p>
        @endif

        <div class="flex items-center justify-center space-x-4 text-sm text-gray-500">
            <span>{{ $category->products_count ?? 0 }} Products</span>
            <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
            <span>Shop Now</span>
        </div>
    </div>
</div> 