{{-- Checkout: the delivery area and its fee, bulky-item charges and the Malé time slot.
     Needs $deliveryCheckout (DeliveryService::checkout()), $deliveryZones and $selectedZone from checkout/index. --}}
@php
    $dc = $deliveryCheckout;
    $area = $dc['area'];
    $islandsArea = $area === \App\Services\DeliveryService::AREA_GREATER_MALE ? \App\Services\DeliveryService::AREA_ISLANDS : $area;
    $zoneFees = [
        \App\Services\DeliveryService::AREA_GREATER_MALE => $dc['delivered'] ? $dc['areaFees'][\App\Services\DeliveryService::AREA_GREATER_MALE] + $dc['surcharge'] : 0.0,
        \App\Services\DeliveryService::AREA_ISLANDS => $dc['delivered'] ? ($dc['areaFees'][$islandsArea] ?? 0) + $dc['surcharge'] : 0.0,
    ];
    $feeText = fn (float $fee) => $fee > 0 ? \App\Support\Money::format($fee) : __('Free');
@endphp
<section class="bg-white rounded-2xl border border-gray-200 p-6" data-delivery-section>
    <h2 class="text-xl font-semibold text-gray-900 mb-1">{{ __('Delivery area') }}</h2>
    @if($dc['freeOver'] > 0)
        <p class="text-sm text-gray-600 mb-4">{{ __('Free delivery on orders over :amount.', ['amount' => \App\Support\Money::format($dc['freeOver'])]) }}</p>
    @endif

    <div class="space-y-3 {{ $dc['delivered'] ? '' : 'hidden' }}" data-delivery-zones>
        @foreach($deliveryZones as $zone => $label)
            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                <span class="flex items-center gap-3">
                    <input type="radio" name="delivery_zone" value="{{ $zone }}" data-fee="{{ $zoneFees[$zone] ?? 0 }}" @checked($selectedZone === $zone) class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-gray-900">{{ $label }}@if($zone === \App\Services\DeliveryService::AREA_ISLANDS)<span class="block text-xs font-normal text-gray-500" data-islands-area>{{ $islandsArea !== \App\Services\DeliveryService::AREA_ISLANDS ? $dc['areas'][$islandsArea] ?? '' : '' }}</span>@endif</span>
                </span>
                <span class="text-sm font-semibold text-gray-900" dir="ltr" data-zone-fee="{{ $zone }}">{{ $feeText($zoneFees[$zone] ?? 0) }}</span>
            </label>
        @endforeach
    </div>

    <p class="mt-3 text-sm text-gray-700 {{ $dc['delivered'] ? '' : 'hidden' }}" data-area-note>{{ __('Delivery to :area: :fee', ['area' => $dc['areas'][$area] ?? $area, 'fee' => $feeText($dc['delivered'] ? ($dc['areaFees'][$area] ?? 0) : 0)]) }}</p>
    <p class="mt-1 text-sm text-gray-700 {{ $dc['delivered'] && $dc['surcharge'] > 0 ? '' : 'hidden' }}" data-surcharge-note>{{ __('Bulky items add :amount to delivery. Free delivery does not cover this charge.', ['amount' => \App\Support\Money::format($dc['surcharge'])]) }}</p>
    <p class="text-sm text-gray-700 {{ $dc['delivered'] ? 'hidden' : '' }}" data-all-pickup-note>{{ __('You are collecting everything from the shops, so there is no delivery fee and no address is needed.') }}</p>

    @if($dc['slotsEnabled'])
        @php $chosenSlot = (string) old('delivery_slot', ''); $showSlots = $dc['delivered'] && $area === \App\Services\DeliveryService::AREA_GREATER_MALE; @endphp
        <div class="mt-5 border-t border-gray-100 pt-4 {{ $showSlots ? '' : 'hidden' }}" data-delivery-slots>
            <h3 class="text-base font-semibold text-gray-900">{{ __('Delivery time in Malé') }}</h3>
            <p class="text-sm text-gray-600">{{ __('Pick a time for the delivery in Greater Malé, or leave it at any time.') }}</p>
            <label class="mt-3 inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                <input type="radio" name="delivery_slot" value="" @checked($chosenSlot === '') @disabled(! $showSlots) class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                <span class="font-medium text-gray-900">{{ __('Any time') }}</span>
            </label>
            <div class="mt-3 space-y-3">
                @foreach($dc['slotDays'] as $date => $daySlots)
                    @continue($daySlots->every(fn ($slot) => $slot['too_late']))
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l j M') }}</legend>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach($daySlots as $slot)
                                <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm {{ $slot['available'] ? 'cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50' : 'cursor-not-allowed bg-gray-50 text-gray-400' }}">
                                    <input type="radio" name="delivery_slot" value="{{ $slot['key'] }}" data-unavailable="{{ $slot['available'] ? '0' : '1' }}" @checked($chosenSlot === $slot['key'] && $slot['available']) @disabled(! $slot['available'] || ! $showSlots) class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                                    <span dir="ltr">{{ $slot['time'] }}</span>
                                    @if($slot['full'])<span class="text-xs font-medium">({{ __('Full') }})</span>@elseif($slot['too_late'])<span class="text-xs font-medium">({{ __('Too late to book') }})</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            @error('delivery_slot')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
    @endif
</section>

@include('checkout._delivery_script')
