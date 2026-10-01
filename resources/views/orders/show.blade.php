@extends('layouts.app')

@section('title', 'Order #' . $order->order_number . ' - iruali')

@section('content')
<div class="max-w-4xl mx-auto px-4 lg:px-0 py-6 lg:py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Order') }} #{{ $order->order_number }}</h1>
                <p class="text-gray-600">{{ __('Placed on :date', ['date' => $order->created_at->translatedFormat('j F Y, H:i')]) }}</p>
            </div>
            <div class="text-end">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $order->status_badge }}">
                    {{ __(\App\Support\OrderStatus::label($order->status)) }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Order Details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Order Items') }}</h2>
                <div class="space-y-4">
                    @php
                        $parts = $order->sellerOrders->keyBy(fn ($p) => (string) $p->seller_id);
                        $steps = \App\Support\OrderStatus::steps();
                    @endphp
                    @foreach($order->items->groupBy(fn ($i) => (string) $i->product?->seller_id) as $sellerId => $shopItems)
                        @php $part = $parts[$sellerId] ?? null; $rank = \App\Models\SellerOrder::RANK[$part?->status] ?? -1; @endphp
                        <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-gray-900">{{ __('From :shop', ['shop' => $part?->shopName() ?? ($shopItems->first()->product?->seller?->business_name ?: 'iruali')]) }}</p>
                                @if($part)<span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $part->status_badge }}">{{ $steps[$part->status] ?? __(\App\Support\OrderStatus::label($part->status)) }}</span>@endif
                            </div>
                            @if($part && $part->status !== 'cancelled')
                                <ol class="grid grid-cols-5 gap-1 text-[11px] text-center" aria-label="{{ __('Progress') }}">
                                    @foreach($steps as $key => $label)
                                        @php $done = $rank >= \App\Models\SellerOrder::RANK[$key]; @endphp
                                        <li><span class="block h-1.5 rounded-full {{ $done ? 'bg-primary' : 'bg-gray-200' }}"></span><span class="mt-1 block {{ $done ? 'text-gray-900 font-medium' : 'text-gray-400' }}">{{ $label }}</span></li>
                                    @endforeach
                                </ol>
                            @endif
                            @if($part)
                                @include('orders._tracking', ['part' => $part])
                            @endif
                            @foreach($shopItems as $item)
                            <div class="flex items-center gap-4">
                                <div class="shrink-0">
                                    <img src="{{ $item->product?->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="{{ $item->product?->name }}" class="w-16 h-16 object-cover rounded bg-primary-50">
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900">{{ $item->product?->name ?? __('Product') }}</h3>
                                    <p class="text-sm text-gray-600">{{ __('Quantity') }}: {{ $item->quantity }}</p>
                                </div>
                                <div class="text-end">
                                    <p class="font-semibold text-gray-900 force-ltr" dir="ltr">{{ \App\Support\Money::format($item->price * $item->quantity) }}</p>
                                    <p class="text-xs text-gray-600 force-ltr" dir="ltr">{{ \App\Support\Money::format($item->price) }} {{ __('each') }}</p>
                                </div>
                            </div>
                            @endforeach
                            @if($part)
                                @include('orders._returns', ['part' => $part])
                                @include('orders._disputes', ['part' => $part])
                                @include('messaging._thread', [
                                    'part' => $part,
                                    'conversation' => $part->conversation,
                                    'role' => 'customer',
                                    'action' => route('orders.messages.store', [$order, $part]),
                                    'canReply' => $messagingOpen && (! $part->conversation || $part->conversation->isOpen()),
                                    'closedNote' => __('Messages about this order are closed: it is more than 90 days old and nothing is open on it.'),
                                ])
                            @endif
                        </div>
                    @endforeach
                    @if($order->loyalty_points_earned > 0)
                    <div class="flex justify-between">
                        <span class="text-blue-700">{{ __('Loyalty Points Earned') }}</span>
                        <span class="text-blue-700">+{{ $order->loyalty_points_earned }}</span>
                    </div>
                    @endif
                    @if($order->points_redeemed > 0)
                    <div class="flex justify-between">
                        <span class="text-blue-700">{{ __('Points Redeemed') }}</span>
                        <span class="text-blue-700 force-ltr" dir="ltr">-{{ $order->points_redeemed }} ({{ \App\Support\Money::format($order->points_redeemed_discount) }})</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Shipping Information') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">{{ __('Shipping Address') }}</h3>
                        <p class="text-gray-600">
                            {{ $order->shipping_address }}<br>
                            {{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}<br>
                            {{ $order->shipping_country }}
                        </p>
                    </div>
                    @if($order->shipping_phone)
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-2">{{ __('Phone for delivery') }}</h3>
                            <p class="text-gray-600" dir="ltr"><a href="tel:{{ $order->shipping_phone }}" class="hover:underline">{{ $order->shipping_phone }}</a></p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 lg:sticky lg:top-32">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Order Summary') }}</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Subtotal') }}</span>
                        <span class="text-gray-900 force-ltr" dir="ltr">{{ \App\Support\Money::format($order->total_amount - $order->shipping_amount + $order->voucher_discount + $order->points_redeemed_discount) }}</span>
                    </div>
                    @if($order->voucher_code && $order->voucher_discount > 0)
                    <div class="flex justify-between">
                        <span class="text-green-700">Voucher ({{ $order->voucher_code }})</span>
                        <span class="text-green-700 force-ltr" dir="ltr">-{{ \App\Support\Money::format($order->voucher_discount) }}</span>
                    </div>
                    @endif
                    @if($order->points_redeemed_discount > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Loyalty Points Discount') }}</span>
                        <span class="text-gray-900" dir="ltr">-{{ \App\Support\Money::format($order->points_redeemed_discount) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Delivery') }}</span>
                        <span class="text-gray-900" dir="ltr">{{ $order->shipping_amount > 0 ? \App\Support\Money::format($order->shipping_amount) : __('Free') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Payment Method') }}</span>
                        <span class="text-gray-900">{{ \App\Services\PaymentService::methodLabel($order->payment_method) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Payment') }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ \App\Services\PaymentService::statusBadge($order->payment_status) }}">{{ \App\Services\PaymentService::statusLabel($order->payment_status) }}</span>
                    </div>
                    @if($order->refund_status === 'due')
                        <p class="rounded-lg bg-sun-soft px-3 py-2 text-xs text-sun-ink">{{ __('A refund of :amount is on its way to you. We email you when it is sent.', ['amount' => \App\Support\Money::format($order->refund_amount)]) }}</p>
                    @elseif($order->refund_status === 'refunded')
                        <p class="rounded-lg bg-green-50 px-3 py-2 text-xs text-green-800">{{ __('Refunded :amount on :date. Reference: :ref', ['amount' => \App\Support\Money::format($order->refund_amount), 'date' => $order->refunded_at?->translatedFormat('j M Y'), 'ref' => $order->refund_reference]) }}</p>
                    @endif
                    <hr class="my-3">
                    <div class="flex justify-between">
                        <span class="text-lg font-semibold text-gray-900">{{ __('Total') }}</span>
                        <span class="text-lg font-semibold text-primary-600 force-ltr" dir="ltr">{{ \App\Support\Money::format($order->total_amount) }}</span>
                    </div>
                </div>

                @php $payments = app(\App\Services\PaymentService::class); @endphp
                @if($payments->canPayOnline($order))
                    @php $lastAttempt = $order->paymentTransactions()->latest('id')->first(); @endphp
                    <div class="mt-6 rounded-xl border border-primary-200 bg-primary-50 p-4 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('Pay by card') }}</h3>
                        @if($lastAttempt?->hasFailed())
                            <p class="text-sm text-danger">{{ __('Your last payment attempt did not go through. You can try again.') }}</p>
                        @elseif($lastAttempt && ! $lastAttempt->isConfirmed())
                            <p class="text-sm text-gray-600">{{ __('If you already paid, it can take a minute for the bank to confirm. Otherwise, pay below.') }}</p>
                        @else
                            <p class="text-sm text-gray-600">{{ __('Your order is waiting for payment.') }}</p>
                        @endif
                        <form method="POST" action="{{ route('payments.bml.pay', $order) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Pay :amount now', ['amount' => \App\Support\Money::format($order->total_amount)]) }}</button>
                        </form>
                        <p class="text-xs text-gray-500 flex items-center gap-1.5"><x-icon name="shield" class="w-4 h-4 text-primary" />{{ __('You pay on Bank of Maldives\' secure page. iruali never sees your card details.') }}</p>
                    </div>
                @elseif($order->payment_method === 'bml' && $order->payment_status === 'paid')
                    <p class="mt-6 rounded-xl bg-green-50 text-green-800 text-sm font-medium p-4 flex items-center gap-2"><x-icon name="check" class="w-4 h-4" />{{ __('Paid by card on :date.', ['date' => $order->paid_at?->format('j M Y, H:i')]) }}</p>
                @endif

                <form method="POST" action="{{ route('orders.buyAgain', $order) }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-primary-hover"><x-icon name="repeat" class="w-4 h-4" />{{ __('Buy again') }}</button>
                </form>

                @if($order->status === 'pending')
                <div class="mt-3">
                    <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('{{ __('Cancel this order?') }}')">
                        @csrf
                        <button type="submit" class="w-full border border-danger text-danger px-4 py-2 rounded-lg hover:bg-danger-50 transition duration-300">
                            {{ __('Cancel Order') }}
                        </button>
                    </form>
                </div>
                @endif

                <a href="{{ route('orders.receipt', $order) }}" target="_blank" rel="noopener" class="mt-3 w-full inline-flex items-center justify-center gap-2 border border-gray-300 text-dark px-4 py-2 rounded-lg font-semibold hover:bg-gray-50">{{ __('View / print receipt') }}</a>
                <p class="mt-4 text-xs text-gray-500">{{ __('Please keep a copy of your order confirmation, payment receipt and our policies for your records.') }} <a href="{{ route('policies.refunds') }}" class="text-primary hover:underline">{{ __('Returns, Refunds & Cancellations') }}</a></p>

                <div class="mt-4">
                    <a href="{{ route('orders') }}" class="block text-center text-primary-600 hover:text-primary-700 font-medium">
                        {{ __('← Back to Orders') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 