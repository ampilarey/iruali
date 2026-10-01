@extends('layouts.app')

@section('title', 'Checkout - iruali')

@php
    $field = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500';
    $user = auth()->user();
    $selectedZone = old('delivery_zone', app(\App\Services\DeliveryService::class)->zoneFor(null, $user->city));
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Checkout') }}</h1>
        <p class="text-gray-600">{{ __('Complete your order') }}</p>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc ps-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Loyalty-point forms live outside the order form (forms can't nest); their controls point at them with form="" --}}
    <form id="redeem-points-form" action="{{ route('checkout.redeemPoints') }}" method="POST">@csrf</form>
    <form id="remove-points-form" action="{{ route('checkout.removePoints') }}" method="POST">@csrf</form>

    <form id="checkout-form" action="{{ route('orders.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <div class="lg:col-span-3 space-y-6">
                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Delivery address') }}</h2>
                    <div class="space-y-4">
                        <div>
                            <label for="shipping_address" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                            <input type="text" id="shipping_address" name="shipping_address" required autocomplete="shipping address-line1" class="{{ $field }}" value="{{ old('shipping_address', $user->address) }}" placeholder="{{ __('House name, street') }}" @error('shipping_address') aria-invalid="true" aria-describedby="shipping_address-error" @enderror>
                            @error('shipping_address')<p id="shipping_address-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="shipping_city" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Island') }}</label>
                                <input type="text" id="shipping_city" name="shipping_city" required autocomplete="shipping address-level2" class="{{ $field }}" value="{{ old('shipping_city', $user->city) }}" placeholder="{{ __('e.g. Malé') }}" @error('shipping_city') aria-invalid="true" aria-describedby="shipping_city-error" @enderror>
                                @error('shipping_city')<p id="shipping_city-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="shipping_state" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Atoll') }}</label>
                                <input type="text" id="shipping_state" name="shipping_state" required autocomplete="shipping address-level1" class="{{ $field }}" value="{{ old('shipping_state', $user->state) }}" placeholder="{{ __('e.g. Kaafu') }}" @error('shipping_state') aria-invalid="true" aria-describedby="shipping_state-error" @enderror>
                                @error('shipping_state')<p id="shipping_state-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="shipping_phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone for delivery') }}</label>
                                <input type="tel" id="shipping_phone" name="shipping_phone" required inputmode="tel" autocomplete="tel" dir="ltr" class="{{ $field }}" value="{{ old('shipping_phone', $user->phone) }}" placeholder="7771234" @error('shipping_phone') aria-invalid="true" aria-describedby="shipping_phone-error" @enderror>
                                @error('shipping_phone')
                                    <p id="shipping_phone-error" class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @else
                                    <p class="mt-1 text-xs text-gray-500">{{ __('The courier will call this number when your order arrives.') }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="shipping_zip" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Postal code') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                                <input type="text" id="shipping_zip" name="shipping_zip" autocomplete="shipping postal-code" class="{{ $field }}" value="{{ old('shipping_zip', $user->postal_code) }}" placeholder="20026" @error('shipping_zip') aria-invalid="true" aria-describedby="shipping_zip-error" @enderror>
                                @error('shipping_zip')<p id="shipping_zip-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="shipping_country" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Country') }}</label>
                                <input type="text" id="shipping_country" name="shipping_country" required autocomplete="shipping country-name" class="{{ $field }} bg-gray-50" value="{{ old('shipping_country', 'Maldives') }}" readonly>
                                @error('shipping_country')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-1">{{ __('Delivery area') }}</h2>
                    @if($freeDeliveryOver > 0)
                        <p class="text-sm text-gray-600 mb-4">{{ __('Free delivery on orders over :amount.', ['amount' => \App\Support\Money::format($freeDeliveryOver)]) }}</p>
                    @endif
                    <div class="space-y-3">
                        @foreach($deliveryZones as $zone => $label)
                            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                                <span class="flex items-center gap-3">
                                    <input type="radio" name="delivery_zone" value="{{ $zone }}" data-fee="{{ $deliveryQuotes[$zone] }}" @checked($selectedZone === $zone) class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm font-medium text-gray-900">{{ $label }}</span>
                                </span>
                                <span class="text-sm font-semibold text-gray-900" dir="ltr">{{ $deliveryQuotes[$zone] > 0 ? \App\Support\Money::format($deliveryQuotes[$zone]) : __('Free') }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Payment Method') }}</h2>
                    @php
                        $paymentMethods = app(\App\Services\PaymentService::class)->methods();
                        $paymentHelp = [
                            'bml' => __('Pay now with Visa, Mastercard, American Express or Maestro on Bank of Maldives\' secure payment page.'),
                        ];
                    @endphp
                    @if($paymentMethods === [])
                        <p class="rounded-xl border border-sun bg-sun-soft px-4 py-3 text-sm text-sun-ink">{{ __('Card payment is not available right now, so orders can\'t be placed. Please try again later.') }}</p>
                    @endif
                    <div class="space-y-3">
                        @foreach($paymentMethods as $method => $label)
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                                <input type="radio" name="payment_method" value="{{ $method }}" @checked(old('payment_method', array_key_first($paymentMethods)) === $method) class="mt-0.5 h-4 w-4 text-primary-600 focus:ring-primary-500">
                                <span>
                                    <span class="flex items-center gap-2 text-sm font-medium text-gray-900">{{ $label }}
                                        @if($method === 'bml')<img src="/images/card-brands.png" alt="{{ __('We accept American Express, Visa, Mastercard and Maestro') }}" width="147" height="30" class="h-8 w-auto">@endif
                                    </span>
                                    <span class="block text-sm text-gray-600">{{ $paymentHelp[$method] ?? '' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-payment-trust class="mt-4" />
                </section>
            </div>

            <aside class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:sticky lg:top-32">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Order Summary') }}</h2>

                    <ul class="space-y-3 mb-6">
                        @foreach($cart->items as $item)
                            <li class="flex justify-between items-center gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img src="{{ $item->product->mainImage->url ?? '/images/product-placeholder.svg' }}" alt="" class="w-12 h-12 object-cover rounded-lg bg-primary-50 shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $item->product->name }}</p>
                                        <p class="text-xs text-gray-500">{{ __('Qty') }}: {{ $item->quantity }}</p>
                                    </div>
                                </div>
                                <span class="text-sm font-medium text-gray-900" dir="ltr">{{ \App\Support\Money::format($item->quantity * $item->product->price) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if(isset($points_balance) && ($points_balance > 0 || $points_redeemed > 0))
                        <div class="mb-6 rounded-xl bg-sun-soft p-4">
                            <h3 class="text-sm font-semibold text-sun-ink mb-2">{{ __('Loyalty Points') }}: {{ $points_balance }}</h3>
                            @if($points_redeemed > 0)
                                <div class="flex items-center justify-between text-sm">
                                    <span dir="ltr">{{ __('Points Redeemed') }}: {{ $points_redeemed }}</span>
                                    <button type="submit" form="remove-points-form" class="text-danger hover:underline">{{ __('Remove') }}</button>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <input type="number" name="points" form="redeem-points-form" min="1" max="{{ $points_balance }}" class="{{ $field }} w-32 bg-white" placeholder="{{ __('Points to redeem') }}">
                                    <button type="submit" form="redeem-points-form" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Redeem') }}</button>
                                </div>
                            @endif
                        </div>
                    @endif

                    <dl class="border-t border-gray-200 pt-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Subtotal') }}</dt>
                            <dd class="font-medium" dir="ltr">{{ \App\Support\Money::format($cart->total) }}</dd>
                        </div>
                        @if($voucherDiscount > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Voucher Discount') }} ({{ $voucherCode }})</dt>
                                <dd class="font-medium text-coral" dir="ltr">−{{ \App\Support\Money::format($voucherDiscount) }}</dd>
                            </div>
                        @endif
                        @if($points_redeemed_discount > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Loyalty Points Discount') }}</dt>
                                <dd class="font-medium text-coral" dir="ltr">−{{ \App\Support\Money::format($points_redeemed_discount) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Delivery') }}</dt>
                            <dd class="font-medium" dir="ltr" id="delivery-fee">{{ \App\Support\Money::format($deliveryQuotes[$selectedZone] ?? 0) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-3 text-base">
                            <dt class="font-semibold">{{ __('Total') }}</dt>
                            <dd class="font-semibold text-primary-700" dir="ltr" id="order-total">{{ \App\Support\Money::format($goodsTotal + ($deliveryQuotes[$selectedZone] ?? 0)) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600 space-y-1">
                        <p class="font-semibold text-dark">{{ __('Before you order') }}</p>
                        <p>{{ __('Pending orders can be cancelled from My Orders. Damaged, faulty or wrong items can be returned within :days days of delivery. Food, opened personal care items, underwear, swimwear and custom items cannot be returned unless they arrive damaged or wrong. Card refunds go back to the same card.', ['days' => \App\Support\Company::returnWindowDays()]) }}</p>
                        <p>{{ __('We deliver within the Maldives only; delivery times depend on boat and flight schedules to your island.') }}</p>
                    </div>
                    <label class="mt-4 flex items-start gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="agree_terms" value="1" required @checked(old('agree_terms')) class="mt-0.5 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
                        <span>{!! __('I have read and accept the :terms, :refunds, :delivery and :privacy.', [
                            'terms' => '<a href="'.route('policies.terms').'" target="_blank" class="text-primary font-medium underline">'.e(__('Terms & Conditions')).'</a>',
                            'refunds' => '<a href="'.route('policies.refunds').'" target="_blank" class="text-primary font-medium underline">'.e(__('Returns, Refunds & Cancellations')).'</a>',
                            'delivery' => '<a href="'.route('policies.delivery').'" target="_blank" class="text-primary font-medium underline">'.e(__('Delivery Policy')).'</a>',
                            'privacy' => '<a href="'.route('policies.privacy').'" target="_blank" class="text-primary font-medium underline">'.e(__('Privacy Policy')).'</a>',
                        ]) !!}</span>
                    </label>
                    @error('agree_terms')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror

                    <button type="submit" @disabled($paymentMethods === []) class="w-full bg-primary text-white py-3 px-4 rounded-xl font-semibold hover:bg-primary-hover transition mt-4 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('Place Order') }}
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
    (function () {
        var goods = @json((float) $goodsTotal);
        var prefix = @json(\App\Support\Money::prefix());
        var fmt = function (n) { return prefix + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        document.querySelectorAll('input[name="delivery_zone"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var fee = parseFloat(radio.dataset.fee) || 0;
                document.getElementById('delivery-fee').textContent = fmt(fee);
                document.getElementById('order-total').textContent = fmt(goods + fee);
            });
        });
    })();
</script>
@endsection
