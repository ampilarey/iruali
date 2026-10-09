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
                                <p class="text-sm font-medium text-gray-900">{{ $item->product ? $item->displayName() : 'Deleted product' }}@if($item->variant_sku) <span class="text-xs text-gray-500" dir="ltr">({{ $item->variant_sku }})</span>@endif</p>
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
                @php $fulfilment = app(\App\Services\FulfilmentService::class); @endphp
                <div class="border-t border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Shops in this order</h3>
                    <div class="mt-3 space-y-3">
                        @foreach($order->sellerOrders()->with('seller')->get() as $part)
                            <div class="rounded-lg border border-gray-200 p-3 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-gray-900">{{ $part->shopName() }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $part->status_badge }}">{{ \App\Support\OrderStatus::label($part->status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Items {{ \App\Support\Money::format($part->subtotal) }}@if((float) $part->shop_discount > 0) · shop's discounts &minus;{{ \App\Support\Money::format($part->shop_discount) }}@endif · commission {{ rtrim(rtrim(number_format((float) $part->commission_rate, 2), '0'), '.') }}% ({{ \App\Support\Money::format($part->commission_amount) }}) · shop earns {{ \App\Support\Money::format($part->seller_earnings) }} · {{ str_replace('_', ' ', $part->earningsState()) }}</p>
                                <div class="mt-2">@include('orders._tracking', ['part' => $part])</div>
                                @include('preorders._part', ['part' => $part, 'for' => 'admin', 'class' => 'mt-2'])
                                @include('admin.orders._part_delivery', ['order' => $order, 'part' => $part])
                                @if($next = $fulfilment->nextStatuses($part))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($next as $status)
                                            <form method="POST" action="{{ route('admin.orders.parts.status', [$order, $part]) }}" class="flex gap-2">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $status }}">
                                                @if($status === 'shipped')<input name="tracking_note" placeholder="Note to customer (optional)" class="rounded-lg border border-gray-300 px-2 py-1 text-xs">@endif
                                                <button class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">Mark {{ str_replace('_', ' ', $status) }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @endif
                                @if($part->status !== 'cancelled')
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-medium text-primary-700">{{ $part->hasTrackingDetails() ? 'Edit delivery details' : 'Add delivery details' }}</summary>
                                        <form method="POST" action="{{ route('admin.orders.parts.tracking', [$order, $part]) }}" class="mt-2 space-y-2">
                                            @csrf
                                            @include('orders._tracking_form', ['part' => $part])
                                            <button class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">Save details</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="border-t border-gray-100 px-5 py-4 space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Conversations</h3>
                    @foreach($order->sellerOrders as $part)
                        @include('messaging._thread', [
                            'part' => $part,
                            'conversation' => $part->conversation,
                            'role' => 'admin',
                            'action' => route('admin.orders.messages.store', [$order, $part]),
                            'canReply' => true,
                            'closedNote' => null,
                        ])
                        @if($part->conversation)
                            <form method="POST" action="{{ route('admin.conversations.status', $part->conversation) }}" class="text-end -mt-1">
                                @csrf
                                <input type="hidden" name="status" value="{{ $part->conversation->isOpen() ? 'closed' : 'open' }}">
                                <button class="text-xs text-gray-500 underline hover:text-gray-800">{{ $part->conversation->isOpen() ? 'Close this conversation' : 'Reopen this conversation' }}</button>
                            </form>
                        @endif
                    @endforeach
                </div>
                <dl class="space-y-1 border-t border-gray-100 px-5 py-4 text-sm">
                    @include('orders._shop_deals', ['order' => $order, 'as' => 'dl'])
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
                    <span class="mt-2 mb-4 inline-block rounded-full px-2 py-1 text-xs font-medium {{ $order->status_badge }}">{{ \App\Support\OrderStatus::label($order->status) }}</span>
                    @include('partials.order-status-actions', ['action' => route('admin.orders.status', $order), 'nextStatuses' => $nextStatuses])
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Payment</h2>
                    <p class="mt-2 text-sm text-gray-900">{{ \App\Services\PaymentService::methodLabel($order->payment_method) }}</p>
                    <span class="mt-2 inline-block rounded-full px-2 py-1 text-xs font-medium {{ \App\Services\PaymentService::statusBadge($order->payment_status) }}">{{ \App\Services\PaymentService::statusLabel($order->payment_status) }}</span>
                    @if($order->paid_at)
                        <p class="mt-1 text-xs text-gray-500">Paid {{ $order->paid_at->format('d M Y, H:i') }}</p>
                    @endif
                    @if((float) $order->wallet_amount > 0)
                        <p class="mt-1 text-xs text-gray-600">{{ \App\Support\Money::format($order->wallet_amount) }} paid from the customer's wallet{{ $order->cardAmount() > 0 ? ', '.\App\Support\Money::format($order->cardAmount()).' by card' : '' }}{{ $order->wallet_refunded_at ? ' · wallet part returned '.$order->wallet_refunded_at->format('d M Y') : '' }}</p>
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
                    @if($order->refund_status === 'due')
                        <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm space-y-2">
                            <p class="font-semibold text-red-800">Refund due: {{ \App\Support\Money::format($order->refund_amount) }}</p>
                            <p class="text-red-700">{{ $order->refund_reason }}. Refund it in the BML merchant portal (Transactions → {{ $order->order_number }} → Refund), then record the reference here.</p>
                            <form method="POST" action="{{ route('admin.orders.refunded', $order) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <label for="refund_reference" class="sr-only">Refund reference</label>
                                <input id="refund_reference" name="refund_reference" required maxlength="100" placeholder="Refund reference" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                                <button class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover">Mark as refunded</button>
                            </form>
                            @if($order->user && auth()->user()->hasRole('admin')) {{-- store credit is for admins only (routes/web/wallet.php) --}}
                                <form method="POST" action="{{ route('admin.orders.refund-wallet', $order) }}" class="border-t border-red-200 pt-2" onsubmit="return confirm('Credit {{ \App\Support\Money::format($order->refund_amount) }} to the customer\'s wallet instead of refunding the card?')">
                                    @csrf
                                    <button class="rounded-lg border border-primary-300 bg-white px-3 py-1.5 text-sm font-semibold text-primary-700 hover:bg-primary-50">Refund to wallet</button>
                                    <span class="ms-2 text-xs text-red-700">Instant store credit instead of a card refund.</span>
                                </form>
                            @endif
                        </div>
                    @elseif($order->refund_status === 'refunded')
                        <p class="mt-3 rounded-md bg-green-50 px-3 py-2 text-xs text-green-800">Refunded {{ \App\Support\Money::format($order->refund_amount) }} on {{ $order->refunded_at?->format('d M Y') }} · ref {{ $order->refund_reference }} ({{ $order->refund_reason }})</p>
                    @endif
                    @if($order->payment_status !== 'paid' && $order->payment_method !== 'bml')
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.orders.payment', $order) }}">
                                @csrf
                                <input type="hidden" name="action" value="confirm">
                                <button class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover">Confirm payment</button>
                            </form>
                        </div>
                    @endif
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Customer</h2>
                    <p class="mt-2 text-sm text-gray-900">{{ $order->customerName() ?? 'Deleted user' }}@if($order->isGuest()) <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-semibold">Guest</span>@endif</p>
                    <p class="text-sm text-gray-600">{{ $order->customerEmail() ?? '' }}</p>
                    <p class="mt-3 text-sm text-gray-700">{{ $order->shipping_address }}</p>
                    <p class="text-sm text-gray-700">{{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}</p>
                    <p class="text-sm text-gray-700">{{ $order->shipping_country }}</p>
                    @if($order->shipping_phone)
                        <p class="mt-2 text-sm text-gray-700">Phone: <a href="tel:{{ $order->shipping_phone }}" dir="ltr" class="font-medium hover:underline">{{ $order->shipping_phone }}</a></p>
                    @endif
                    <p class="mt-3 text-xs text-gray-500">Placed {{ $order->created_at->format('d M Y, H:i') }}</p>
                </div>
                @include('admin.orders._delivery', ['order' => $order])
                @include('admin.tax._order', ['order' => $order])
            </div>
        </div>
    </div>
</div>
@endsection
