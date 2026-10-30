@extends('layouts.app')

@section('title', 'Checkout - iruali')

@php
    $field = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500';
    $user = auth()->user();
    $addresses = $addresses ?? collect();
    $selectedAddress = $addresses->firstWhere('id', (int) $selectedAddressId);
    $boatOffer ??= false;
    $boatFrom ??= null;
    $boatLast ??= null;
    $byBoat = $boatOffer && (string) $selectedAddressId === \App\Support\BoatDelivery::CHOICE;
    $selectedZone = $byBoat ? \App\Services\DeliveryService::AREA_GREATER_MALE : old('delivery_zone', $selectedAddress ? $selectedAddress->deliveryZone() : app(\App\Services\DeliveryService::class)->zoneFor(null, $user?->city));
    // Deliver or pickup per shop, area fees, bulky-item charges, Malé time slots (checkout/_fulfilment, _delivery)
    $deliveryCheckout = app(\App\Services\DeliveryService::class)->checkout($cart, (float) $goodsTotal, $selectedAddress, $user, $addresses);
    // Small-order fee (Admin → Settings → Ordering), charged with delivery; and a pause in force
    $smallOrderFee = \App\Support\OrderRules::smallOrderFee((float) $goodsTotal);
    $orderingPause = \App\Support\OrderRules::pause();
    // Pre-order lines (CheckoutController): marked in the summary, remembered for placing the order, and their shops'
    // estimates count from their date. With a booking fee only the fee is paid now (BookingFeeService); a pre-order
    // paid once the shop confirms is paid in full later, nothing now (PreorderConfirmService)
    $preorderLines ??= app(\App\Services\PreorderService::class)->checkoutLines($cart);
    $bookingFee ??= null;
    $confirmFirst ??= null;
    $deliveryCheckout['shops'] = app(\App\Services\PreorderService::class)->withCheckoutEstimates($deliveryCheckout['shops'], $cart, $deliveryCheckout['areas'], $preorderLines);
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-48 lg:pb-8">
    @if($orderingPause)
        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-ordering-paused>{{ $orderingPause['message'] }}</p>
    @endif
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
    @include('cart._holiday_notice', ['cart' => $cart])
    @include('checkout._preorder_notice', ['lines' => $preorderLines, 'bookingFee' => $bookingFee, 'confirmFirst' => $confirmFirst])

    {{-- Loyalty-point forms live outside the order form (forms can't nest); their controls point at them with form="" --}}
    <form id="redeem-points-form" action="{{ route('checkout.redeemPoints') }}" method="POST">@csrf</form>
    <form id="remove-points-form" action="{{ route('checkout.removePoints') }}" method="POST">@csrf</form>

    <form id="checkout-form" action="{{ route('orders.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <div class="lg:col-span-3 space-y-6">
                @include('checkout._fulfilment')
                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Delivery address') }}</h2>
                    @if($addresses->isNotEmpty() || $boatOffer)
                        <div class="space-y-3 mb-5" role="radiogroup" aria-label="{{ __('Where it goes') }}">
                            @foreach($addresses as $address)
                                <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                                    <input type="radio" name="address_id" value="{{ $address->id }}" data-zone="{{ $address->deliveryZone() }}" @checked((string) $selectedAddressId === (string) $address->id) class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500">
                                    <span class="min-w-0 text-sm">
                                        <span class="flex flex-wrap items-center gap-2 font-medium text-gray-900">{{ $address->label ?: __('Address') }} @if($address->is_default)<span class="rounded-full bg-primary-100 text-primary-700 px-2 py-0.5 text-xs font-semibold">{{ __('Default') }}</span>@endif</span>
                                        <span class="block text-gray-700">{{ $address->recipient_name }} · {{ $address->summary() }}</span>
                                        @if($address->phone)<span class="block text-gray-500" dir="ltr">{{ $address->phone }}</span>@endif
                                        <span class="block text-xs text-primary font-medium mt-1">{{ __('Use this address') }}</span>
                                    </span>
                                </label>
                            @endforeach
                            <label class="flex items-center gap-3 rounded-xl border border-dashed border-gray-300 px-4 py-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                                <input type="radio" name="address_id" value="" @checked((string) $selectedAddressId === '') class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-900">{{ $addresses->isNotEmpty() ? __('Deliver to a new address') : __('Deliver to an address') }}</span>
                            </label>
                            @if($boatOffer)
                                {{-- Send with a boat (App\Support\BoatDelivery): the shop takes it to the jetty --}}
                                <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50" data-boat-choice>
                                    <input type="radio" name="address_id" value="{{ \App\Support\BoatDelivery::CHOICE }}" data-zone="{{ \App\Services\DeliveryService::AREA_GREATER_MALE }}" @checked($byBoat) class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500">
                                    <span class="min-w-0 text-sm">
                                        <span class="flex items-center gap-1.5 font-medium text-gray-900"><x-icon name="truck" class="h-4 w-4 text-primary" />{{ __('Send with a boat (from :island)', ['island' => $boatFrom?->localized_name]) }}</span>
                                        <span class="block text-gray-600">{{ __('The shop takes it to the jetty or harbour before your boat leaves. You pay the boat\'s freight when you collect.') }}</span>
                                    </span>
                                </label>
                            @endif
                            @error('address_id')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                        </div>
                        @if($addresses->isNotEmpty())<p class="mb-4 text-xs text-gray-500"><a href="{{ route('account.addresses') }}" class="text-primary hover:underline">{{ __('Manage saved addresses') }}</a></p>@endif
                    @endif
                    <div class="space-y-4 {{ ($addresses->isNotEmpty() || $byBoat) && (string) $selectedAddressId !== '' ? 'hidden' : '' }}" data-new-address>
                        @if($user && trim((string) $user->name) === '')
                            {{-- Signed in with just a mobile number: the courier needs a name (none for a pickup, as on Bake & Grill) --}}
                            <div data-checkout-name>
                                <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name for the delivery') }}</label>
                                <input type="text" id="customer_name" name="customer_name" required autocomplete="name" maxlength="120" class="{{ $field }}" value="{{ old('customer_name') }}" @error('customer_name') aria-invalid="true" @enderror>
                                @error('customer_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                        @endif
                        <div>
                            <label for="shipping_address" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                            <input type="text" id="shipping_address" name="shipping_address" required autocomplete="shipping address-line1" class="{{ $field }}" value="{{ old('shipping_address', $user?->address) }}" placeholder="{{ __('House name, street') }}" @error('shipping_address') aria-invalid="true" aria-describedby="shipping_address-error" @enderror>
                            @error('shipping_address')<p id="shipping_address-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        </div>
                        <x-island-picker :islands-by-atoll="$islandsByAtoll" :selected-id="old('island_id', app(\App\Services\ShoppingIsland::class)->island()?->id)" :island="old('shipping_city', $user?->city)" :atoll="old('shipping_state', $user?->state)" island-name="shipping_city" atoll-name="shipping_state" prefix="ship" />
                        @error('shipping_city')<p id="shipping_city-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        @error('shipping_state')<p id="shipping_state-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        <div>
                            <label for="shipping_phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Phone for delivery') }}</label>
                            <input type="tel" id="shipping_phone" name="shipping_phone" required inputmode="tel" autocomplete="tel" dir="ltr" class="{{ $field }}" value="{{ old('shipping_phone', $user?->phone) }}" placeholder="7771234" @error('shipping_phone') aria-invalid="true" aria-describedby="shipping_phone-error" @enderror>
                            @error('shipping_phone')
                                <p id="shipping_phone-error" class="mt-1 text-sm text-danger">{{ $message }}</p>
                            @else
                                <p class="mt-1 text-xs text-gray-500">{{ __('The courier will call this number when your order arrives.') }}</p>
                            @enderror
                        </div>
                        {{-- Every order is delivered in the Maldives: no country or postal code to type --}}
                        <input type="hidden" name="shipping_country" value="Maldives">
                        @auth
                            <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 space-y-2">
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="save_address" value="1" @checked(old('save_address', $addresses->isEmpty())) class="rounded text-primary-600 focus:ring-primary-500">
                                    {{ __('Save this address to my account for next time') }}
                                </label>
                                <div>
                                    <label for="address_label" class="sr-only">{{ __('Label') }}</label>
                                    <input type="text" id="address_label" name="address_label" maxlength="50" class="{{ $field }} bg-white" value="{{ old('address_label') }}" placeholder="{{ __('Label, e.g. Home (optional)') }}">
                                </div>
                            </div>
                        @endauth
                    </div>
                    @if($boatOffer)
                        @include('checkout._boat')
                    @endif
                </section>

                @include('checkout._delivery')
                @include('checkout._gift')

                @include('checkout._business-details')
                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Payment Method') }}</h2>
                    @php
                        $paymentMethods = app(\App\Services\PaymentService::class)->methods();
                        if (($orderingPause['mode'] ?? null) === 'cod') {
                            unset($paymentMethods[\App\Services\ManualPaymentService::COD]); // cash on delivery paused
                        }
                        if ($bookingFee || $confirmFirst) {
                            // A booking fee, or a pre-order paid once the shop confirms, is paid by card or bank transfer
                            unset($paymentMethods[\App\Services\ManualPaymentService::COD], $paymentMethods[\App\Services\CreditService::METHOD]);
                        }
                        $paymentHelp = [
                            'bml' => __('Pay now with Visa, Mastercard, American Express or Maestro on Bank of Maldives\' secure payment page.'),
                            \App\Services\ManualPaymentService::TRANSFER => __('Transfer to our bank account after placing the order, then upload the transfer slip. The shops send your order once the payment is confirmed.'),
                            \App\Services\ManualPaymentService::COD => __('Pay in cash when your order arrives. For orders from one shop.'),
                            \App\Services\CreditService::METHOD => __('Your business pays the invoice later, within your terms. Credit left: :amount.', ['amount' => \App\Support\Money::format(app(\App\Services\CreditService::class)->available(auth()->user() ?? new \App\Models\User))]),
                        ];
                    @endphp
                    @if($paymentMethods === [])
                        <p class="rounded-xl border border-sun bg-sun-soft px-4 py-3 text-sm text-sun-ink">{{ __('Payment is not available right now, so orders can\'t be placed. Please try again later.') }}</p>
                    @elseif($confirmFirst)
                        <p class="mb-3 text-sm text-gray-700" data-confirm-first-payment>{{ __('Nothing is charged now. Choose how you would like to pay once the shop confirms it can get your item; you can change it then.') }}</p>
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
                                        @if($item->variant)<p class="text-xs text-gray-600">{{ $item->variant->displayName() }}</p>@endif
                                        <p class="text-xs text-gray-500">{{ __('Qty') }}: {{ $item->quantity }}</p>
                                        @include('checkout._preorder_line', ['line' => $preorderLines[$item->id] ?? null])
                                        @include('quotes._checkout_line', ['item' => $item])
                                    </div>
                                </div>
                                <span class="text-sm font-medium text-gray-900" dir="ltr">{{ \App\Support\Money::format($item->quantity * $item->unit_price) }}</span>
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

                    {{-- Nothing is paid now for a pre-order the shop confirms first, so no wallet either --}}
                    @php $walletBalance = $confirmFirst ? 0.0 : round((float) ($user?->wallet_balance ?? 0), 2); @endphp
                    @if($walletBalance > 0)
                        <label class="mb-6 flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                            <input type="checkbox" name="use_wallet" value="1" id="use-wallet" data-balance="{{ $walletBalance }}" @checked(old('use_wallet')) class="mt-0.5 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
                            <span class="text-sm">
                                <span class="block font-medium text-gray-900">{{ __('Use wallet balance') }} <span dir="ltr">({{ \App\Support\Money::format($walletBalance) }})</span></span>
                                <span class="block text-gray-600">{{ $bookingFee ? __('Your store credit pays the booking fee first; anything left is paid by card or transfer.') : __('Your store credit pays first; anything left is paid by card.') }}</span>
                            </span>
                        </label>
                    @endif

                    <dl class="border-t border-gray-200 pt-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Subtotal') }}</dt>
                            <dd class="font-medium" dir="ltr">{{ \App\Support\Money::format($cart->total) }}</dd>
                        </div>
                        @include('checkout._shop_deals')
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
                            <dd class="font-medium" dir="ltr" id="delivery-fee">{{ \App\Support\Money::format($deliveryCheckout['fee']) }}</dd>
                        </div>
                        @if($smallOrderFee > 0)
                            <div class="flex justify-between" data-small-order-fee>
                                <dt class="text-gray-600">{{ __('Small order fee') }}<span class="block text-xs text-gray-500">{{ __('Orders under :amount', ['amount' => \App\Support\Money::format((float) \App\Models\Setting::get('small_order_threshold'))]) }}</span></dt>
                                <dd class="font-medium" dir="ltr">{{ \App\Support\Money::format($smallOrderFee) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 pt-3 text-base">
                            <dt class="font-semibold">{{ __('Total') }}</dt>
                            <dd class="font-semibold text-primary-700" dir="ltr" id="order-total">{{ \App\Support\Money::format($goodsTotal + $smallOrderFee + $deliveryCheckout['fee']) }}</dd>
                        </div>
                        @if($bookingFee)
                            {{-- Pre-orders with a booking fee: the fee now, the rest when the stock arrives (BookingFeeService) --}}
                            <div class="flex justify-between rounded-lg bg-primary-50 px-3 py-2 text-base" data-booking-fee-now>
                                <dt class="font-semibold">{{ __('Booking fee to pay now') }}@if($bookingFee['gst'] ?? false)<span class="block text-xs font-normal text-gray-600" data-booking-fee-gst>{{ __('GST included. You get a tax invoice for it.') }}</span>@endif</dt>
                                <dd class="font-semibold text-primary-700" dir="ltr">{{ \App\Support\Money::format($bookingFee['fee']) }}</dd>
                            </div>
                            <div class="flex justify-between" data-booking-balance>
                                <dt class="text-gray-600">{{ __('Balance when it arrives') }}</dt>
                                <dd class="font-medium" dir="ltr" id="booking-balance">{{ \App\Support\Money::format($goodsTotal + $smallOrderFee + $deliveryCheckout['fee'] - $bookingFee['fee']) }}</dd>
                            </div>
                        @endif
                        @if($confirmFirst)
                            {{-- Paid once the shop confirms it can get the item (PreorderConfirmService) --}}
                            <div class="flex justify-between rounded-lg bg-primary-50 px-3 py-2 text-base" data-confirm-first-now>
                                <dt class="font-semibold">{{ __('To pay now') }}</dt>
                                <dd class="font-semibold text-primary-700" dir="ltr">{{ \App\Support\Money::format(0) }}</dd>
                            </div>
                            <p class="text-xs text-gray-600">{{ trans_choice('You pay the total within :count day of the shop confirming it can get your item.|You pay the total within :count days of the shop confirming it can get your item.', $confirmFirst['pay_days'], ['count' => $confirmFirst['pay_days']]) }}</p>
                        @endif
                        @if($walletBalance > 0)
                            <div class="flex justify-between hidden" id="wallet-row">
                                <dt class="text-gray-600">{{ __('From your wallet') }}</dt>
                                <dd class="font-medium text-coral" dir="ltr" id="wallet-amount"></dd>
                            </div>
                            <div class="flex justify-between hidden" id="card-row">
                                <dt class="text-gray-600">{{ __('To pay by card') }}</dt>
                                <dd class="font-medium" dir="ltr" id="card-amount"></dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-6 rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600 space-y-1">
                        <p class="font-semibold text-dark">{{ __('Before you order') }}</p>
                        <p>{{ __('Pending orders can be cancelled from My Orders. Damaged, faulty or wrong items can be returned within :days days of delivery. Food, opened personal care items, underwear, swimwear and custom items cannot be returned unless they arrive damaged or wrong. Card refunds go back to the same card.', ['days' => \App\Support\Company::returnWindowDays()]) }}</p>
                        <p>{{ __('We deliver within the Maldives only; delivery times depend on boat and flight schedules to your island.') }}</p>
                    </div>
                    @if($bookingFee)
                        <label class="mt-3 flex items-start gap-3 rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm text-gray-800" data-agree-booking-fee>
                            <input type="checkbox" name="agree_booking_fee" value="1" required @checked(old('agree_booking_fee')) class="mt-0.5 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
                            <span>{{ __('I pay the booking fee of :fee now and the balance within :balance_days days of my order arriving, before it is delivered. If I cancel, the booking fee is not refunded. If the shop cancels, can\'t get the item, moves the date later or is more than :days days late, I get everything back.', ['fee' => \App\Support\Money::format($bookingFee['fee']), 'balance_days' => $bookingFee['balance_days'], 'days' => $bookingFee['late_days']]) }}</span>
                        </label>
                        @error('agree_booking_fee')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    @endif

                    {{-- The terms and the button: on a phone a bar fixed at the bottom of the screen (as on Bake & Grill),
                         saying what the tap does ("Pay MVR 275 by card"); on a computer at the end of the summary --}}
                    <div class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white px-4 pt-3 shadow-[0_-4px_16px_rgba(15,42,58,0.12)] lg:static lg:z-auto lg:mt-0 lg:border-0 lg:p-0 lg:shadow-none" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));" data-pay-bar>
                        <label class="flex items-start gap-3 text-xs text-gray-700 lg:mt-4 lg:text-sm">
                            <input type="checkbox" name="agree_terms" value="1" required @checked(old('agree_terms')) class="mt-0.5 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
                            <span>{!! __('I have read and accept the :terms, :refunds, :delivery and :privacy.', [
                                'terms' => '<a href="'.route('policies.terms').'" target="_blank" rel="noopener" class="text-primary font-medium underline">'.e(__('Terms & Conditions')).'</a>',
                                'refunds' => '<a href="'.route('policies.refunds').'" target="_blank" rel="noopener" class="text-primary font-medium underline">'.e(__('Returns, Refunds & Cancellations')).'</a>',
                                'delivery' => '<a href="'.route('policies.delivery').'" target="_blank" rel="noopener" class="text-primary font-medium underline">'.e(__('Delivery Policy')).'</a>',
                                'privacy' => '<a href="'.route('policies.privacy').'" target="_blank" rel="noopener" class="text-primary font-medium underline">'.e(__('Privacy Policy')).'</a>',
                            ]) !!}</span>
                        </label>
                        @error('agree_terms')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        <button type="submit" @disabled($paymentMethods === []) class="mt-2 w-full bg-primary text-white py-3 px-4 rounded-xl font-semibold hover:bg-primary-hover transition lg:mt-4 disabled:cursor-not-allowed disabled:opacity-50" data-pay-button data-label-card="{{ __('Pay :amount by card') }}" data-label-other="{{ __('Place order · :amount') }}" data-label-free="{{ __('Place order') }}">
                            {{ __('Place Order') }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
    // The pay button says what a tap does: "Pay MVR 275.00 by card" (what goes to the card: after the wallet,
    // or the booking fee), "Place order · MVR 275.00" for other ways to pay, "Place order" when nothing is due now
    (function () {
        var form = document.getElementById('checkout-form');
        var button = form && form.querySelector('[data-pay-button]');
        if (!button) return;
        var text = function (sel) { var el = form.querySelector(sel); return el && !el.closest('.hidden') ? el.textContent.trim() : ''; };
        function label() {
            var method = form.querySelector('input[name=payment_method]:checked');
            if (form.querySelector('[data-confirm-first-now]')) { button.textContent = button.dataset.labelFree; return; }
            var amount = text('#card-amount') || text('[data-booking-fee-now] dd') || text('#order-total');
            var key = method && method.value === 'bml' ? 'labelCard' : 'labelOther';
            button.textContent = amount ? button.dataset[key].replace(':amount', amount) : button.dataset.labelFree;
        }
        form.addEventListener('change', function () { setTimeout(label, 0); });
        ['#order-total', '#card-amount', '#card-row'].forEach(function (sel) {
            var el = form.querySelector(sel);
            if (el && window.MutationObserver) new MutationObserver(label).observe(el, { childList: true, characterData: true, subtree: true, attributes: true });
        });
        label();
    })();
</script>

<script>
    (function () {
        var goods = @json((float) $goodsTotal + $smallOrderFee);
        var bookingFee = @json($bookingFee ? (float) $bookingFee['fee'] : null); // a booking fee: the wallet pays towards it, the rest is the balance
        var prefix = @json(\App\Support\Money::prefix());
        var fmt = function (n) { return prefix + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var checkedZone = document.querySelector('input[name="delivery_zone"]:checked');
        var currentFee = checkedZone ? (parseFloat(checkedZone.dataset.fee) || 0) : 0;
        var wallet = document.getElementById('use-wallet');
        var splitWallet = function () {
            if (! wallet) return;
            var total = bookingFee !== null ? bookingFee : goods + currentFee;
            var show = wallet.checked;
            var fromWallet = Math.min(parseFloat(wallet.dataset.balance) || 0, total);
            document.getElementById('wallet-row').classList.toggle('hidden', ! show);
            document.getElementById('card-row').classList.toggle('hidden', ! show);
            document.getElementById('wallet-amount').textContent = '−' + fmt(fromWallet);
            document.getElementById('card-amount').textContent = fmt(total - fromWallet);
        };
        if (wallet) { wallet.addEventListener('change', splitWallet); splitWallet(); }
        document.querySelectorAll('input[name="delivery_zone"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var fee = parseFloat(radio.dataset.fee) || 0;
                currentFee = fee;
                document.getElementById('delivery-fee').textContent = fmt(fee);
                document.getElementById('order-total').textContent = fmt(goods + fee);
                if (bookingFee !== null) { document.getElementById('booking-balance').textContent = fmt(goods + fee - bookingFee); }
                splitWallet();
            });
        });
    })();
</script>
@endsection
