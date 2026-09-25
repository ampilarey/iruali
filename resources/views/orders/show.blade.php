@extends('layouts.app')

@section('title', 'Order #' . $order->order_number . ' - iruali')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Order #{{ $order->order_number }}</h1>
                <p class="text-gray-600">Placed on {{ $order->created_at->format('F d, Y \a\t g:i A') }}</p>
            </div>
            <div class="text-right">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full
                    @if($order->status === 'completed') bg-green-100 text-green-800
                    @elseif($order->status === 'pending') bg-yellow-100 text-yellow-800
                    @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                    @else bg-gray-100 text-gray-800 @endif">
                    {{ ucfirst($order->status) }}
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
                    @foreach($order->items as $item)
                    <div class="flex items-center space-x-4">
                        <div class="shrink-0">
                            @if($item->product->mainImage)
                                <img src="{{ $item->product->mainImage->url }}" alt="{{ $item->product->name }}" class="w-16 h-16 object-cover rounded">
                            @else
                                <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $item->product->name }}</h3>
                            <p class="text-sm text-gray-600">Quantity: {{ $item->quantity }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold text-gray-900 force-ltr" dir="ltr">{{ \App\Support\Money::format($item->price * $item->quantity) }}</p>
                            <p class="text-sm text-gray-600 force-ltr" dir="ltr">{{ \App\Support\Money::format($item->price) }} each</p>
                        </div>
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
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                            {{ $order->shipping_country }}
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">{{ __('Order Status') }}</h3>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm text-gray-600">{{ __('Order Placed') }}</span>
                                <span class="text-xs text-gray-400 ml-auto">{{ $order->created_at->format('M d, Y') }}</span>
                            </div>
                            @if($order->status === 'completed')
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm text-gray-600">{{ __('Order Completed') }}</span>
                                <span class="text-xs text-gray-400 ml-auto">{{ $order->updated_at->format('M d, Y') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
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
                @if($order->payment_method === 'bank_transfer' && $order->payment_status !== 'paid' && $order->status !== 'cancelled')
                    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('Pay by bank transfer') }}</h3>
                        @php $bank = $payments->bankDetails(); @endphp
                        <dl class="text-sm space-y-1">
                            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Bank') }}</dt><dd class="text-gray-900">{{ $bank['bank'] }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Account name') }}</dt><dd class="text-gray-900">{{ $bank['name'] }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Account number') }}</dt><dd class="font-semibold text-gray-900 select-all" dir="ltr">{{ $bank['number'] }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Amount') }}</dt><dd class="font-semibold text-gray-900" dir="ltr">{{ \App\Support\Money::format($order->total_amount) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Reference') }}</dt><dd class="text-gray-900 select-all" dir="ltr">{{ $order->order_number }}</dd></div>
                        </dl>
                        @if($order->payment_status === 'rejected')
                            <p class="text-sm text-danger">{{ __('We couldn\'t match your last slip to a payment. Please check the amount and upload it again.') }}</p>
                        @elseif($order->payment_status === 'submitted')
                            <p class="text-sm text-gray-600">{{ __('Slip received. We\'ll confirm your payment shortly.') }}</p>
                        @endif
                        @if($payments->canUploadSlip($order))
                            <form method="POST" action="{{ route('orders.payment-slip.store', $order) }}" enctype="multipart/form-data" class="space-y-2">
                                @csrf
                                <label for="payment_slip" class="block text-sm font-medium text-gray-700">{{ $order->payment_status === 'submitted' ? __('Replace slip') : __('Upload transfer slip') }}</label>
                                <input id="payment_slip" name="payment_slip" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required class="block w-full text-sm text-gray-700">
                                @error('payment_slip')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                                <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Send slip') }}</button>
                            </form>
                        @endif
                    </div>
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

                <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="mt-3 w-full inline-flex items-center justify-center gap-2 border border-gray-300 text-dark px-4 py-2 rounded-lg font-semibold hover:bg-gray-50">{{ __('View / print receipt') }}</a>
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