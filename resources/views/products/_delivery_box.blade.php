{{-- Product page: "Delivery to <island>" with the area's fee, the bulky-item charge and an arrival
     estimate (the shop's usual dispatch days plus the transit days). The island is picked from the
     list and kept in the session; it starts at the customer's default address, else Greater Malé.
     Needs $product. --}}
@php
    $box = app(\App\Services\DeliveryService::class)->productBox($product, auth()->user());
    $destination = $box['destination'];
@endphp
<div id="delivery-box" class="flex gap-2 scroll-mt-40" data-delivery-box>
    <x-icon name="truck" class="w-5 h-5 shrink-0 text-primary" />
    <div class="min-w-0 flex-1 space-y-1">
        <p class="flex flex-wrap items-baseline justify-between gap-x-2">
            <span class="font-semibold text-dark">{{ __('Delivery to :island', ['island' => $destination['label']]) }}</span>
            <span class="font-semibold text-dark" dir="ltr">{{ $box['fee'] > 0 ? \App\Support\Money::format($box['fee']) : __('Free') }}</span>
        </p>
        <p class="text-gray-700">{{ $box['arrival'] }}</p>
        @if($box['surcharge'] > 0)
            <p class="text-gray-700">{{ __('Plus :amount delivery per item (bulky item), even with free delivery.', ['amount' => \App\Support\Money::format($box['surcharge'])]) }}</p>
        @endif
        @if($box['freeOver'] > 0)
            <p class="text-success font-medium">{{ __('Free delivery on orders over :amount', ['amount' => \App\Support\Money::format($box['freeOver'])]) }}</p>
        @endif
        @if($box['pickup'])
            <p class="text-gray-700 flex items-start gap-1.5"><x-icon name="store" class="w-4 h-4 mt-0.5 shrink-0 text-primary" /><span>{{ __('Or pick it up from the shop in :island, with no delivery fee.', ['island' => $box['pickup']['island']]) }}</span></p>
        @endif
        @if($box['islandsByAtoll']->isNotEmpty())
            <form method="POST" action="{{ route('delivery.island') }}" class="pt-1 flex items-center gap-2">
                @csrf
                <label for="delivery-island" class="text-xs text-gray-500 shrink-0">{{ __('Deliver to') }}</label>
                <select id="delivery-island" name="island_id" onchange="this.form.submit()" class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white ps-2 pe-8 py-1 text-sm focus:border-primary focus:ring-primary">
                    @if(! $destination['island_id'])
                        <option value="" disabled selected>{{ $destination['label'] }}</option>
                    @endif
                    @foreach($box['islandsByAtoll'] as $atoll => $islands)
                        <optgroup label="{{ $atoll }}">
                            @foreach($islands as $island)
                                <option value="{{ $island->id }}" @selected($destination['island_id'] === $island->id)>{{ $island->localized_name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <noscript><button type="submit" class="text-sm font-semibold text-primary">{{ __('Change') }}</button></noscript>
            </form>
        @endif
    </div>
</div>
