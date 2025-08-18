@props(['banner'])

<div class="relative overflow-hidden rounded-lg shadow-lg">
    <!-- Banner Image -->
    <img src="{{ $banner->image ?? 'https://via.placeholder.com/1200x400?text=Banner' }}" 
         alt="{{ $banner->title ?? 'Promotional Banner' }}" 
         class="w-full h-full object-cover">
    
    <!-- Content Overlay -->
    <div class="absolute inset-0 bg-gradient-to-r from-black/50 to-transparent flex items-center">
        <div class="px-8 py-6 text-white">
            @if($banner->subtitle)
                <p class="text-sm font-medium text-primary-200 mb-2">{{ $banner->subtitle }}</p>
            @endif
            
            @if($banner->title)
                <h2 class="text-2xl md:text-3xl font-bold mb-3">{{ $banner->title }}</h2>
            @endif
            
            @if($banner->description)
                <p class="text-sm md:text-base text-gray-200 mb-4 max-w-md">{{ $banner->description }}</p>
            @endif
            
            @if($banner->button_text && $banner->button_url)
                <a href="{{ $banner->button_url }}" 
                   class="inline-flex items-center px-6 py-3 bg-primary hover:bg-primary/90 text-white font-medium rounded-lg transition-colors">
                    {{ $banner->button_text }}
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            @endif
        </div>
    </div>
</div> 