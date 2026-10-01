@extends('layouts.app')

@php use App\Support\Money; $order = $return->order; $part = $return->sellerOrder; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Return on order #'.$order->order_number, 'back' => route('admin.returns')])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg bg-white p-5 shadow grid gap-3 sm:grid-cols-2 text-sm">
                <div><p class="text-gray-500">Status</p><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $return->status_badge }}">{{ $return->statusLabel() }}</span></div>
                <div><p class="text-gray-500">Requested</p><p>{{ $return->created_at->format('d M Y, H:i') }}</p></div>
                <div><p class="text-gray-500">Customer</p><p class="font-medium">{{ $return->user?->name }}</p><p class="text-xs text-gray-500">{{ $return->user?->email }} @if($return->user?->phone)· {{ $return->user->phone }}@endif</p></div>
                <div><p class="text-gray-500">Shop</p><p class="font-medium">{{ $part?->shopName() }}</p></div>
                <div><p class="text-gray-500">Order</p><a href="{{ route('admin.orders.show', $order) }}" class="text-primary-700 hover:underline">#{{ $order->order_number }}</a><p class="text-xs text-gray-500">Paid by {{ \App\Services\PaymentService::methodLabel($order->payment_method) }} · {{ \App\Services\PaymentService::statusLabel($order->payment_status) }} · total {{ Money::format($order->total_amount) }}</p></div>
                <div><p class="text-gray-500">Delivered</p><p>{{ $part?->delivered_at?->format('d M Y') ?? '—' }}</p></div>
                <div class="sm:col-span-2"><p class="text-gray-500">Reason</p><p class="font-medium">{{ $return->reasonLabel() }}</p>@if($return->details)<p class="mt-1 whitespace-pre-line text-gray-700">{{ $return->details }}</p>@endif</div>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><tr><th class="px-4 py-2 text-left">Item</th><th class="px-4 py-2 text-right">Returning</th><th class="px-4 py-2 text-right">Price</th><th class="px-4 py-2 text-right">Value</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($return->items as $line)
                            <tr>
                                <td class="px-4 py-2">{{ $line->orderItem?->displayName() ?? 'Product' }} <span class="text-xs text-gray-500">(ordered {{ $line->orderItem?->quantity }})</span></td>
                                <td class="px-4 py-2 text-right">{{ $line->quantity }}</td>
                                <td class="px-4 py-2 text-right">{{ Money::format($line->orderItem?->price) }}</td>
                                <td class="px-4 py-2 text-right font-semibold">{{ Money::format($line->orderItem?->price * $line->quantity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="flex justify-end border-t border-gray-100 px-4 py-2 text-sm font-semibold">Items value: <span class="ms-2">{{ Money::format($return->items_value) }}</span></div>
            </div>

            @if($return->photo_path)
                <div class="rounded-lg bg-white p-5 shadow">
                    <h2 class="text-sm font-semibold text-gray-900">Customer's photo</h2>
                    <a href="{{ route('returns.photo', $return) }}" target="_blank" rel="noopener"><img src="{{ route('returns.photo', $return) }}" alt="Photo of the returned item" class="mt-3 max-h-96 rounded-lg border border-gray-200"></a>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            @if($return->status === 'requested')
                <form method="POST" action="{{ route('admin.returns.approve', $return) }}" class="rounded-lg bg-white p-5 shadow space-y-3 text-sm">
                    @csrf
                    <h2 class="font-semibold text-gray-900">Approve</h2>
                    <div>
                        <label for="refund_amount" class="block font-medium text-gray-700">Refund to customer (MVR)</label>
                        <input id="refund_amount" name="refund_amount" type="number" step="0.01" min="0" max="{{ $maxRefund }}" value="{{ old('refund_amount', number_format($suggested, 2, '.', '')) }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        <p class="mt-1 text-xs text-gray-500">Suggested: the items{{ in_array($return->reason, \App\Models\ReturnRequest::SHOP_FAULT, true) && $order->sellerOrders()->count() === 1 ? ' plus delivery (shop at fault)' : '' }}. At most {{ Money::format($maxRefund) }} is left on this order.</p>
                    </div>
                    <label class="flex items-center gap-2"><input type="checkbox" name="restock" value="1" class="rounded" @checked(old('restock', ! in_array($return->reason, ['damaged', 'faulty'], true)))> Put the items back in stock</label>
                    <div>
                        <label for="approve_note" class="block font-medium text-gray-700">Note to customer (optional)</label>
                        <textarea id="approve_note" name="admin_note" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="e.g. Our courier will collect the item on Sunday.">{{ old('admin_note') }}</textarea>
                    </div>
                    <p class="text-xs text-gray-500">The shop's share of the items ({{ Money::format(round($return->items_value * (1 - ($part?->commission_rate ?? 0) / 100), 2)) }}, after {{ rtrim(rtrim(number_format((float) $part?->commission_rate, 2), '0'), '.') }}% commission) is taken from its next payout.</p>
                    <button class="w-full rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700" onclick="return confirm('Approve this return?')">Approve return</button>
                </form>

                <form method="POST" action="{{ route('admin.returns.reject', $return) }}" class="rounded-lg bg-white p-5 shadow space-y-3 text-sm">
                    @csrf
                    <h2 class="font-semibold text-gray-900">Reject</h2>
                    <label for="reject_note" class="block font-medium text-gray-700">Reason (sent to the customer)</label>
                    <textarea id="reject_note" name="admin_note" rows="2" maxlength="1000" required class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                    <button class="w-full rounded-lg border border-red-300 px-4 py-2 font-semibold text-red-700 hover:bg-red-50">Reject return</button>
                </form>
            @elseif($return->status === 'approved')
                <div class="rounded-lg bg-white p-5 shadow space-y-3 text-sm">
                    <h2 class="font-semibold text-gray-900">Send the refund</h2>
                    <p>Refund <strong>{{ Money::format($return->refund_amount) }}</strong> to the customer.</p>
                    @if($order->payment_method === 'bml')
                        <p class="text-gray-600">This order was paid by card. Refund it in the BML merchant portal (Transactions → find order {{ $order->order_number }} → Refund), then record the refund reference here.</p>
                    @else
                        <p class="text-gray-600">Send a bank transfer to an account in the customer's name, then record the transfer reference here.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.returns.refunded', $return) }}" class="space-y-2">
                        @csrf
                        <label for="refund_reference" class="block font-medium text-gray-700">Refund reference</label>
                        <input id="refund_reference" name="refund_reference" required maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <button class="w-full rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Mark as refunded</button>
                    </form>
                    <form method="POST" action="{{ route('admin.returns.refund-wallet', $return) }}" class="border-t border-gray-100 pt-3" onsubmit="return confirm('Credit {{ Money::format($return->refund_amount) }} to the customer\'s wallet instead of refunding the card?')">
                        @csrf
                        <p class="text-gray-600 mb-2">Or credit it to the customer's iruali wallet right now (store credit they can spend on any order).</p>
                        <button class="w-full rounded-lg border border-primary-300 px-4 py-2 font-semibold text-primary-700 hover:bg-primary-50">Refund to wallet</button>
                    </form>
                </div>
            @endif

            @if($return->resolved_at)
                <div class="rounded-lg bg-white p-5 shadow space-y-1 text-sm">
                    <h2 class="font-semibold text-gray-900">History</h2>
                    <p>{{ ucfirst($return->status === 'rejected' ? 'rejected' : 'approved') }} {{ $return->resolved_at->format('d M Y, H:i') }}</p>
                    @if($return->refund_amount !== null && $return->status !== 'rejected')
                        <p>Refund: {{ Money::format($return->refund_amount) }}{{ $return->restocked ? ' · items restocked' : '' }}</p>
                    @endif
                    @if($return->refunded_at)<p>Refunded {{ $return->refunded_at->format('d M Y') }} · ref {{ $return->refund_reference }}</p>@endif
                    @if($return->admin_note)<p class="text-gray-600">Note: {{ $return->admin_note }}</p>@endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
