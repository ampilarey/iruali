{{-- Product page, under the price: the shop's multi-buy offer ("Buy 2, save 5%"), applied in the cart by itself. --}}
@php
    $multibuyOffer = $product->seller_id ? $product->multibuyOffer : null;
    $multibuyTiers = $multibuyOffer && (int) $multibuyOffer->seller_id === (int) $product->seller_id ? $multibuyOffer->tierList() : [];
    $multibuyOthers = $multibuyTiers && $multibuyOffer->isShared()
        ? $multibuyOffer->products()->where('is_active', true)->whereKeyNot($product->id)->orderBy('id')->take(6)->get()
        : collect();
@endphp
@if($multibuyTiers)
    <div class="mt-3 rounded-lg bg-coral-soft px-3 py-2 text-sm" data-multibuy-offer>
        <p class="flex items-center gap-1.5 font-semibold text-coral"><x-icon name="tag" class="w-4 h-4 shrink-0" />{{ __('Multi-buy offer') }}</p>
        <ul class="mt-1 space-y-0.5 text-dark">
            @foreach($multibuyTiers as $tier)
                <li>{{ \App\Services\MultiBuyService::tierLabel($tier) }}</li>
            @endforeach
        </ul>
        @if($multibuyOthers->isNotEmpty())
            <p class="mt-1 text-xs text-gray-700">{{ __('Mix and match: these count together too:') }}
                @foreach($multibuyOthers as $other)<a href="{{ route('products.show', $other) }}" class="font-medium text-primary hover:underline">{{ $other->name }}</a>@if(! $loop->last), @endif @endforeach
            </p>
        @endif
        <p class="mt-1 text-xs text-gray-600">{{ __('Taken off in your cart.') }}</p>
    </div>
@endif
