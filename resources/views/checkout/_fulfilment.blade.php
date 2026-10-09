{{-- Checkout: deliver or pick up, for each shop in the cart, with each shop's delivery estimate.
     Needs $deliveryCheckout (DeliveryService::checkout()), $field and $user from checkout/index. --}}
@php
    $shops = $deliveryCheckout['shops'];
    $offersPickup = $shops->contains(fn ($shop) => $shop['pickup'] !== null);
    $initialArea = $deliveryCheckout['area'];
@endphp
<section class="bg-white rounded-2xl border border-gray-200 p-6" data-fulfilment>
    <h2 class="text-xl font-semibold text-gray-900 mb-1">{{ $offersPickup ? __('Delivery or pickup') : __('When it arrives') }}</h2>
    <p class="text-sm text-gray-600 mb-4">{{ $offersPickup ? __('Some shops let you collect your items yourself, with no delivery fee.') : __('Each shop sends its own parcel. Arrival dates are estimates.') }}</p>

    <div class="space-y-3">
        @foreach($shops as $shop)
            <div class="rounded-xl border border-gray-200 p-4" data-shop="{{ $shop['key'] }}">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium text-gray-900">{{ __('From :shop', ['shop' => $shop['name']]) }}</p>
                    <p class="text-xs text-gray-500">{{ trans_choice(':count item|:count items', $shop['units'], ['count' => $shop['units']]) }}</p>
                </div>

                @if($shop['pickup'])
                    <div class="mt-3 grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="{{ __('How to get the items from :shop', ['shop' => $shop['name']]) }}">
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 px-3 py-2.5 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                            <input type="radio" name="fulfilment[{{ $shop['key'] }}]" value="deliver" @checked($shop['method'] === 'deliver') class="mt-0.5 h-4 w-4 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm">
                                <span class="flex items-center gap-1.5 font-medium text-gray-900"><x-icon name="truck" class="w-4 h-4 text-primary" />{{ __('Deliver') }}</span>
                                <span class="block text-gray-600" data-shop-estimate>{{ $shop['estimates'][$initialArea] ?? '' }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 px-3 py-2.5 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                            <input type="radio" name="fulfilment[{{ $shop['key'] }}]" value="pickup" @checked($shop['method'] === 'pickup') class="mt-0.5 h-4 w-4 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm min-w-0">
                                <span class="flex items-center gap-1.5 font-medium text-gray-900"><x-icon name="store" class="w-4 h-4 text-primary" />{{ __('Pick up from :shop', ['shop' => $shop['name']]) }}</span>
                                <span class="block text-gray-600">{{ collect([$shop['pickup']['address'], $shop['pickup']['island']])->filter()->join(', ') }}</span>
                                <span class="block text-xs text-gray-500">{{ $shop['pickup']['ready'] }} · {{ __('No delivery fee') }}</span>
                            </span>
                        </label>
                    </div>
                    @if($shop['pickup']['hours'])
                        <p class="mt-2 text-xs text-gray-500 flex items-start gap-1.5"><x-icon name="clock" class="w-3.5 h-3.5 mt-0.5 shrink-0" /><span><span class="font-medium">{{ __('Opening hours') }}:</span> {{ $shop['pickup']['hours'] }}</span></p>
                    @endif
                    @error('fulfilment.'.$shop['key'])<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                @else
                    <p class="mt-1 text-sm text-gray-700 flex items-center gap-1.5"><x-icon name="truck" class="w-4 h-4 text-primary" /><span data-shop-estimate>{{ $shop['estimates'][$initialArea] ?? '' }}</span></p>
                @endif

                @if($shop['surcharge'] > 0)
                    <p class="mt-1 text-xs text-gray-600">{{ __('Bulky items: :amount extra delivery charge when delivered.', ['amount' => \App\Support\Money::format($shop['surcharge'])]) }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- When every shop's items are collected there is no address to fill in, but the shop still needs a phone number --}}
    <div class="mt-4 hidden" data-pickup-contact>
        <label for="pickup_phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Your phone number') }}</label>
        <input type="tel" id="pickup_phone" name="shipping_phone" disabled inputmode="tel" autocomplete="tel" dir="ltr" class="{{ $field }}" value="{{ old('shipping_phone', $user?->phone) }}" placeholder="7771234">
        <p class="mt-1 text-xs text-gray-500">{{ app(\App\Services\Sms\SmsManager::class)->isLive() ? __('When your order is ready we email your pickup code and text it to this number.') : __('When your order is ready we email you your pickup code. The shop may call this number.') }}</p>
    </div>
</section>
