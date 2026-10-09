@extends('layouts.app')

@section('title', __('Order') . ' #' . $order->order_number . ' - iruali')

@php
    $steps = ['pending' => __('Order placed'), 'processing' => __('Preparing'), 'shipped' => __('On its way'), 'delivered' => __('Delivered')];
    $parts = $order->sellerOrders->keyBy(fn ($p) => (string) $p->seller_id);
    $payments = app(\App\Services\PaymentService::class);
    $contactEmail = \App\Support\Company::email();
    $contactPhone = \App\Support\Company::phone();
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 lg:px-0 py-6 lg:py-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-1">{{ __('Order') }} #{{ $order->order_number }}</h1>
            <p class="text-gray-600">{{ __('Placed on :date', ['date' => $order->created_at->translatedFormat('j F Y, H:i')]) }} · {{ $order->guest_name }} <span dir="ltr">({{ $order->guest_email }})</span></p>
        </div>
        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $order->status_badge }}">{{ \App\Enums\OrderStatus::labelFor($order->status) }}</span>
    </div>

    @if($order->payment_status === 'paid')
        <div class="mb-6 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800 flex items-center gap-2"><x-icon name="check" class="w-4 h-4" />{{ __('Thank you! Your order is confirmed and the shop is preparing it. We emailed a copy of this page to :email.', ['email' => $order->guest_email]) }}</div>
    @else
        <div class="mb-6 rounded-xl bg-sun-soft border border-sun px-4 py-3 text-sm text-sun-ink">{{ __('Keep this page: it is the link to your order. We also emailed it to :email.', ['email' => $order->guest_email]) }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Order Items') }}</h2>
                <div class="space-y-4">
                    @foreach($order->items->groupBy(fn ($i) => (string) $i->product?->seller_id) as $sellerId => $shopItems)
                        @php $part = $parts[$sellerId] ?? null; $rank = \App\Enums\SellerOrderStatus::rankFor($part?->status); @endphp
                        <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-gray-900">{{ __('From :shop', ['shop' => $part?->shopName() ?? ($shopItems->first()->product?->seller?->business_name ?: 'iruali')]) }}</p>
                                @if($part)<span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $part->status_badge }}">{{ $steps[$part->status] ?? \App\Enums\SellerOrderStatus::labelFor($part->status) }}</span>@endif
                            </div>
                            @if($part && $part->status !== 'cancelled' && ! $part->isPickup())
                                <ol class="grid grid-cols-4 gap-1 text-[11px] text-center" aria-label="{{ __('Progress') }}">
                                    @foreach($steps as $key => $label)
                                        @php $done = $rank >= \App\Enums\SellerOrderStatus::rankFor($key); @endphp
                                        <li><span class="block h-1.5 rounded-full {{ $done ? 'bg-primary' : 'bg-gray-200' }}"></span><span class="mt-1 block {{ $done ? 'text-gray-900 font-medium' : 'text-gray-400' }}">{{ $label }}</span></li>
                                    @endforeach
                                </ol>
                            @endif
                            @if($part?->tracking_note)
                                <p class="text-sm text-gray-700"><span class="font-medium">{{ __('Tracking') }}:</span> {{ $part->tracking_note }}</p>
                            @endif
                            @if($part)
                                @include('orders._pickup', ['part' => $part, 'showCode' => true])
                            @endif
                            @foreach($shopItems as $item)
                                <div class="flex items-center gap-4">
                                    <img src="{{ $item->product?->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="w-14 h-14 object-cover rounded bg-primary-50 shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $item->product?->name ?? __('Product') }}</p>
                                        @if($item->variant_name)<p class="text-sm text-gray-600">{{ $item->variant_name }}</p>@endif
                                        <p class="text-sm text-gray-600">{{ __('Quantity') }}: {{ $item->quantity }}</p>
                                    </div>
                                    <p class="font-semibold text-gray-900" dir="ltr">{{ \App\Support\Money::format($item->price * $item->quantity) }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Shipping Information') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-1">{{ __('Shipping Address') }}</h3>
                        <p class="text-gray-600">{{ $order->guest_name }}<br>{{ $order->shipping_address }}<br>{{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}<br>{{ $order->shipping_country }}</p>
                    </div>
                    @if($order->shipping_phone)
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Phone for delivery') }}</h3>
                            <p class="text-gray-600" dir="ltr">{{ $order->shipping_phone }}</p>
                        </div>
                    @endif
                </div>
                @include('orders._delivery_details', ['order' => $order])
            </div>

            <div class="rounded-lg border border-primary-200 bg-primary-50 p-6">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Create an account to track your orders') }}</h2>
                <p class="mt-1 text-sm text-gray-700">{{ __('Sign up with :email and this order appears under My Orders, with returns, loyalty points and saved addresses for next time.', ['email' => $order->guest_email]) }}</p>
                @guest
                    <a href="{{ route('register', ['email' => $order->guest_email]) }}" class="mt-3 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Create an account') }}</a>
                @else
                    <a href="{{ route('orders') }}" class="mt-3 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('My Orders') }}</a>
                @endguest
            </div>

            <div class="rounded-lg bg-gray-50 border border-gray-200 p-6 text-sm text-gray-700">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Need help with this order?') }}</h2>
                <p class="mt-1">{{ __('For a return, a refund or a problem with a guest order, contact us with your order number and we will sort it out.') }}</p>
                <ul class="mt-2 space-y-1">
                    @if($contactEmail)<li><x-icon name="mail" class="inline w-4 h-4 text-primary me-1" /><a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode(__('Order').' '.$order->order_number) }}" class="text-primary hover:underline" dir="ltr">{{ $contactEmail }}</a></li>@endif
                    @if($contactPhone)<li><x-icon name="phone" class="inline w-4 h-4 text-primary me-1" /><a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}" class="text-primary hover:underline" dir="ltr">{{ $contactPhone }}</a></li>@endif
                    <li><a href="{{ route('policies.refunds') }}" class="text-primary hover:underline">{{ __('Returns, Refunds & Cancellations') }}</a></li>
                </ul>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 lg:sticky lg:top-32">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Order Summary') }}</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('Subtotal') }}</span><span class="text-gray-900" dir="ltr">{{ \App\Support\Money::format($order->total_amount - $order->shipping_amount + $order->voucher_discount) }}</span></div>
                    @if($order->voucher_code && $order->voucher_discount > 0)
                        <div class="flex justify-between"><span class="text-green-700">{{ __('Voucher Discount') }} ({{ $order->voucher_code }})</span><span class="text-green-700" dir="ltr">-{{ \App\Support\Money::format($order->voucher_discount) }}</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('Delivery') }}</span><span class="text-gray-900" dir="ltr">{{ $order->shipping_amount > 0 ? \App\Support\Money::format($order->shipping_amount) : __('Free') }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('Payment') }}</span><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ \App\Services\PaymentService::statusBadge($order->payment_status) }}">{{ \App\Services\PaymentService::statusLabel($order->payment_status) }}</span></div>
                    @if($order->refund_status === 'due')
                        <p class="rounded-lg bg-sun-soft px-3 py-2 text-xs text-sun-ink">{{ __('A refund of :amount is on its way to you. We email you when it is sent.', ['amount' => \App\Support\Money::format($order->refund_amount)]) }}</p>
                    @elseif($order->refund_status === 'refunded')
                        <p class="rounded-lg bg-green-50 px-3 py-2 text-xs text-green-800">{{ __('Refunded :amount on :date. Reference: :ref', ['amount' => \App\Support\Money::format($order->refund_amount), 'date' => $order->refunded_at?->translatedFormat('j M Y'), 'ref' => $order->refund_reference]) }}</p>
                    @endif
                    <hr class="my-3">
                    <div class="flex justify-between"><span class="text-lg font-semibold text-gray-900">{{ __('Total') }}</span><span class="text-lg font-semibold text-primary-600" dir="ltr">{{ \App\Support\Money::format($order->total_amount) }}</span></div>
                </div>

                @if($payments->canPayOnline($order))
                    @php $lastAttempt = $order->paymentTransactions->sortByDesc('id')->first(); @endphp
                    <div class="mt-6 rounded-xl border border-primary-200 bg-primary-50 p-4 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('Pay by card') }}</h3>
                        @if($lastAttempt?->hasFailed())
                            <p class="text-sm text-danger">{{ __('Your last payment attempt did not go through. You can try again.') }}</p>
                        @elseif($lastAttempt && ! $lastAttempt->isConfirmed())
                            <p class="text-sm text-gray-600">{{ __('If you already paid, it can take a minute for the bank to confirm. Otherwise, pay below.') }}</p>
                        @else
                            <p class="text-sm text-gray-600">{{ __('Your order is waiting for payment.') }}</p>
                        @endif
                        <form method="POST" action="{{ $order->guestUrl('pay') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Pay :amount now', ['amount' => \App\Support\Money::format($order->total_amount)]) }}</button>
                        </form>
                        <p class="text-xs text-gray-500 flex items-center gap-1.5"><x-icon name="shield" class="w-4 h-4 text-primary" />{{ __('You pay on Bank of Maldives\' secure page. iruali never sees your card details.') }}</p>
                    </div>
                @elseif($order->payment_method === 'bml' && $order->payment_status === 'paid')
                    <p class="mt-6 rounded-xl bg-green-50 text-green-800 text-sm font-medium p-4 flex items-center gap-2"><x-icon name="check" class="w-4 h-4" />{{ __('Paid by card on :date.', ['date' => $order->paid_at?->format('j M Y, H:i')]) }}</p>
                @endif

                <a href="{{ $order->guestUrl('receipt') }}" target="_blank" rel="noopener" class="mt-6 w-full inline-flex items-center justify-center gap-2 border border-gray-300 text-dark px-4 py-2 rounded-lg font-semibold hover:bg-gray-50">{{ __('View / print receipt') }}</a>
                <p class="mt-4 text-xs text-gray-500">{{ __('Please keep a copy of your order confirmation, payment receipt and our policies for your records.') }}</p>
                <p class="mt-4 text-center"><a href="{{ route('order.track.form') }}" class="text-primary-600 hover:underline text-sm">{{ __('Track another order') }}</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
