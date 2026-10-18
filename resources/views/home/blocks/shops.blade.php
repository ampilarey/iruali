<!-- Shops -->
@if($shops->isNotEmpty())
    <section>
        <h2 class="font-display text-xl lg:text-2xl font-bold text-dark mb-3">{{ __('Shops on iruali') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 lg:gap-4">
            @foreach($shops as $shop)
                @php $shopName = $shop->business_name ?: $shop->name; @endphp
                <a href="{{ route('sellers.show', $shop) }}" class="group bg-white border border-gray-200 rounded-xl p-3 lg:p-4 flex items-center gap-3 hover:border-primary hover:shadow-sm">
                    <span class="w-11 h-11 shrink-0 rounded-lg bg-primary text-white font-display font-bold text-lg flex items-center justify-center">{{ mb_strtoupper(mb_substr($shopName, 0, 1)) }}</span>
                    <span class="min-w-0">
                        <span class="block font-semibold text-sm text-dark truncate group-hover:text-primary">{{ $shopName }}</span>
                        <span class="block text-xs text-gray-500 truncate">{{ $shop->city ? $shop->city.' · ' : '' }}{{ trans_choice(':count product|:count products', $shop->products_count, ['count' => $shop->products_count]) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
