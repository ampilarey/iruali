<!-- Brands -->
@if(($brands ?? collect())->count() >= 2)
    <section>
        <div class="flex items-end justify-between gap-4 mb-3">
            <h2 class="font-display text-xl lg:text-2xl font-bold text-dark">{{ __('Shop by brand') }}</h2>
            <a href="{{ route('brands.index') }}" class="shrink-0 whitespace-nowrap text-sm font-semibold text-primary hover:underline">{{ __('See all brands') }}</a>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 lg:gap-3">
            @foreach($brands as $brand)
                @include('brands._tile', ['brand' => $brand])
            @endforeach
        </div>
    </section>
@endif
