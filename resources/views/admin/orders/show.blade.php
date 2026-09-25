@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Order</p>
                    <h1 class="text-2xl font-bold text-gray-900">#{{ $order->order_number }}</h1>
                </div>
                <a href="{{ route('admin.orders') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to orders</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Items</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($order->items as $item)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ $item->product->name ?? 'Deleted product' }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $item->quantity }} × {{ \App\Support\Money::format($item->price) }}
                                    @if($item->product?->seller)
                                        · {{ $item->product->seller->business_name ?: $item->product->seller->name }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ \App\Support\Money::format($item->price * $item->quantity) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-1 border-t border-gray-100 px-5 py-4 text-sm">
                    @if($order->voucher_discount > 0)
                        <div class="flex justify-between text-gray-600"><dt>Voucher {{ $order->voucher_code }}</dt><dd>−{{ \App\Support\Money::format($order->voucher_discount) }}</dd></div>
                    @endif
                    @if($order->points_redeemed_discount > 0)
                        <div class="flex justify-between text-gray-600"><dt>Points ({{ $order->points_redeemed }})</dt><dd>−{{ \App\Support\Money::format($order->points_redeemed_discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between text-gray-600"><dt>Delivery ({{ $order->delivery_zone === 'greater_male' ? 'Greater Malé' : 'other islands' }})</dt><dd>{{ \App\Support\Money::format($order->shipping_amount) }}</dd></div>
                    <div class="flex justify-between text-gray-600"><dt>Payment</dt><dd>{{ \App\Services\PaymentService::methodLabel($order->payment_method) }}</dd></div>
                    <div class="flex justify-between font-semibold text-gray-900"><dt>Total</dt><dd>{{ \App\Support\Money::format($order->total_amount) }}</dd></div>
                </dl>
            </div>

            <div class="space-y-6">
                <div class="rounded-lg bg-white p-5 shadow">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Status</h2>
                    <span class="mt-2 mb-4 inline-block rounded-full px-2 py-1 text-xs font-medium {{ $order->status_badge }}">{{ ucfirst($order->status) }}</span>
                    @include('partials.order-status-actions', ['action' => route('admin.orders.status', $order), 'nextStatuses' => $nextStatuses])
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Payment</h2>
                    <p class="mt-2 text-sm text-gray-900">{{ \App\Services\PaymentService::methodLabel($order->payment_method) }}</p>
                    <span class="mt-2 inline-block rounded-full px-2 py-1 text-xs font-medium {{ \App\Services\PaymentService::statusBadge($order->payment_status) }}">{{ \App\Services\PaymentService::statusLabel($order->payment_status) }}</span>
                    @if($order->paid_at)
                        <p class="mt-1 text-xs text-gray-500">Paid {{ $order->paid_at->format('d M Y, H:i') }}</p>
                    @endif
                    @if($order->payment_slip)
                        <p class="mt-3"><a href="{{ route('orders.payment-slip.show', $order) }}" target="_blank" class="text-sm font-medium text-primary hover:text-primary-hover">View transfer slip ↗</a></p>
                    @endif
                    @if($order->payment_method === 'bml')
                        @php $attempts = $order->paymentTransactions()->latest('id')->get(); @endphp
                        <div class="mt-3 space-y-2">
                            @forelse($attempts as $attempt)
                                <div class="rounded-md border border-gray-200 px-3 py-2 text-xs">
                                    <div class="flex justify-between gap-2"><span class="font-mono text-gray-700 truncate">{{ $attempt->transaction_id ?? 'not created' }}</span>
                                        <span class="font-semibold {{ $attempt->isConfirmed() ? 'text-green-700' : ($attempt->hasFailed() || $attempt->state === 'MISMATCH' ? 'text-red-700' : 'text-gray-700') }}">{{ $attempt->state }}</span></div>
                                    <div class="text-gray-500">{{ $attempt->local_id }} · {{ \App\Support\Money::format($attempt->amount / 100) }} · {{ $attempt->created_at->format('d M, H:i') }}</div>
                                </div>
                            @empty
                                <p class="text-xs text-gray-500">The customer has not started a card payment yet.</p>
                            @endforelse
                            @if($attempts->whereNotNull('transaction_id')->isNotEmpty() && $order->payment_status !== 'paid')
                                <form method="POST" action="{{ route('admin.orders.bml-sync', $order) }}">
                                    @csrf
                                    <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Check with BML</button>
                                </form>
                            @endif
                            @if($order->payment_status === 'paid' && $order->status === 'cancelled')
                                <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">This card payment needs a refund. Refund it in the BML merchant portal.</p>
                            @endif
                        </div>
                    @endif
                    @if($order->payment_status !== 'paid' && $order->payment_method !== 'bml')
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.orders.payment', $order) }}">
                                @csrf
                                <input type="hidden" name="action" value="confirm">
                                <button class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ $order->payment_method === 'cod' ? 'Mark as paid' : 'Confirm payment' }}</button>
                            </form>
                            @if($order->payment_status === 'submitted')
                                <form method="POST" action="{{ route('admin.orders.payment', $order) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button class="rounded-lg border border-danger px-3 py-1.5 text-sm font-semibold text-danger hover:bg-danger-50">Reject slip</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Customer</h2>
                    <p class="mt-2 text-sm text-gray-900">{{ $order->user->name ?? 'Deleted user' }}</p>
                    <p class="text-sm text-gray-600">{{ $order->user->email ?? '' }}</p>
                    <p class="mt-3 text-sm text-gray-700">{{ $order->shipping_address }}</p>
                    <p class="text-sm text-gray-700">{{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}</p>
                    <p class="text-sm text-gray-700">{{ $order->shipping_country }}</p>
                    <p class="mt-3 text-xs text-gray-500">Placed {{ $order->created_at->format('d M Y, H:i') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
