@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Dispute #'.$dispute->id.' on order #'.$order->order_number, 'back' => route('admin.disputes')])

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg bg-white p-5 shadow grid gap-3 sm:grid-cols-2 text-sm">
                <div><p class="text-gray-500">Status</p><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $dispute->status_badge }}">{{ $dispute->statusLabel() }}</span></div>
                <div><p class="text-gray-500">Opened</p><p>{{ $dispute->opened_at->format('d M Y, H:i') }}</p></div>
                <div><p class="text-gray-500">Type</p><p class="font-medium">{{ \App\Models\Dispute::TYPES[$dispute->type] ?? $dispute->type }}</p></div>
                <div><p class="text-gray-500">Claimed</p><p class="font-semibold">{{ Money::format($dispute->amount_claimed) }}</p></div>
                <div><p class="text-gray-500">Customer</p><p class="font-medium">{{ $dispute->customer?->name }}</p><p class="text-xs text-gray-500">{{ $dispute->customer?->email }} @if($dispute->customer?->phone)· {{ $dispute->customer->phone }}@endif</p></div>
                <div><p class="text-gray-500">Shop</p><p class="font-medium">{{ $part?->shopName() }}</p><p class="text-xs text-gray-500">{{ $dispute->seller?->email }}</p></div>
                <div><p class="text-gray-500">Order</p><a href="{{ route('admin.orders.show', $order) }}" class="text-primary-700 hover:underline">#{{ $order->order_number }}</a><p class="text-xs text-gray-500">{{ \App\Services\PaymentService::statusLabel($order->payment_status) }} · total {{ Money::format($order->total_amount) }} · part {{ Money::format($part?->subtotal) }} ({{ ucfirst($part?->status ?? '') }}@if($part?->delivered_at), delivered {{ $part->delivered_at->format('d M') }}@endif)</p></div>
                <div><p class="text-gray-500">Linked return</p>@if($dispute->returnRequest)<a href="{{ route('admin.returns.show', $dispute->returnRequest) }}" class="text-primary-700 hover:underline">Return #{{ $dispute->returnRequest->id }} ({{ $dispute->returnRequest->status }})</a>@else<p>—</p>@endif</div>
                <div class="sm:col-span-2"><p class="text-gray-500">Customer's account</p><p class="whitespace-pre-line text-gray-800">{{ $dispute->details }}</p></div>
                @if($dispute->resolved_at)
                    <div class="sm:col-span-2 rounded-md bg-gray-50 p-3"><p class="text-gray-500">Decision ({{ $dispute->resolved_at->format('d M Y, H:i') }}@if($dispute->admin) by {{ $dispute->admin->name }}@endif)</p><p class="font-medium">{{ $dispute->statusLabel() }} @if($dispute->amount_resolved > 0)· {{ Money::format($dispute->amount_resolved) }}@endif</p>@if($dispute->resolution_note)<p class="text-gray-700">{{ $dispute->resolution_note }}</p>@endif</div>
                @endif
            </div>

            @if($part)
                @include('messaging._thread', [
                    'part' => $part,
                    'conversation' => $dispute->conversation,
                    'role' => 'admin',
                    'action' => route('admin.orders.messages.store', [$order, $part]),
                    'canReply' => true,
                    'closedNote' => null,
                ])
            @endif
        </div>

        <div class="space-y-6">
            @if($dispute->isOpen())
                <form method="POST" action="{{ route('admin.disputes.resolve', $dispute) }}" class="rounded-lg bg-white p-5 shadow space-y-3 text-sm">
                    @csrf
                    <h2 class="font-semibold text-gray-900">Decide</h2>
                    <label class="flex items-start gap-2"><input type="radio" name="outcome" value="full" class="mt-0.5" @checked(old('outcome', 'full') === 'full')> <span><span class="font-medium">Full refund</span> of {{ Money::format($dispute->amount_claimed) }}</span></label>
                    <label class="flex items-start gap-2"><input type="radio" name="outcome" value="partial" class="mt-0.5" @checked(old('outcome') === 'partial')> <span><span class="font-medium">Partial refund</span> of <input name="amount" type="number" step="0.01" min="0.01" max="{{ $dispute->amount_claimed }}" value="{{ old('amount') }}" class="w-28 rounded border border-gray-300 px-2 py-0.5" placeholder="MVR"></span></label>
                    <label class="flex items-start gap-2"><input type="radio" name="outcome" value="reject" class="mt-0.5" @checked(old('outcome') === 'reject')> <span><span class="font-medium">Reject</span> the claim</span></label>
                    @error('amount')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    <div>
                        <label for="note" class="block font-medium text-gray-700">Note to both sides (required when rejecting)</label>
                        <textarea id="note" name="note" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('note') }}</textarea>
                        @error('note')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                    @if($part?->seller_id)
                        <p class="text-xs text-gray-500">On a refund the shop's share ({{ rtrim(rtrim(number_format(100 - (float) $part->commission_rate, 2), '0'), '.') }}% of the refunded items) is taken from its next payout; the refund itself is flagged on the order for you to send through BML.</p>
                    @endif
                    <button class="w-full rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700" onclick="return confirm('Decide this dispute? Both sides are told at once.')">Record decision</button>
                </form>

                <form method="POST" action="{{ route('admin.disputes.request-info', $dispute) }}" class="rounded-lg bg-white p-5 shadow space-y-3 text-sm">
                    @csrf
                    <h2 class="font-semibold text-gray-900">Ask for more information</h2>
                    <select name="from" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="customer">Ask the customer</option>
                        <option value="seller">Ask the shop</option>
                    </select>
                    <textarea name="note" rows="2" maxlength="2000" required placeholder="Your question goes into the conversation as iruali support." class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                    <button class="w-full rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">Post question</button>
                </form>
            @else
                <div class="rounded-lg bg-white p-5 shadow text-sm text-gray-600">This dispute is decided. @if($dispute->amount_resolved > 0)The refund is tracked on the <a href="{{ route('admin.orders.show', $order) }}" class="text-primary-700 hover:underline">order</a> (send it through BML and record the reference there).@endif</div>
            @endif
        </div>
    </div>
</div>
@endsection
