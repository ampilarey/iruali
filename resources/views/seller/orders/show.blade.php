@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => 'Order #' . $order->order_number])
        @slot('action')
            <a href="{{ route('seller.orders') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">← Back to orders</a>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Your items in this order</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($order->items as $item)
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $item->product ? $item->displayName() : 'Deleted product' }}@if($item->variant_sku) <span class="text-xs text-gray-500" dir="ltr">({{ $item->variant_sku }})</span>@endif</p>
                            <p class="text-xs text-gray-500">{{ $item->quantity }} × {{ \App\Support\Money::format($item->price) }}</p>
                        </div>
                        <span class="text-sm font-medium text-gray-900">{{ \App\Support\Money::format($item->price * $item->quantity) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="flex justify-between border-t border-gray-100 px-5 py-4 text-sm font-semibold text-gray-900">
                <span>Your total</span>
                <span>{{ \App\Support\Money::format($order->items->sum(fn ($i) => $i->price * $i->quantity)) }}</span>
            </div>
            @if($disputes->isNotEmpty())
                <div class="border-t border-gray-100 px-5 py-4 space-y-2">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Disputes</h3>
                    @foreach($disputes as $dispute)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium text-gray-900">{{ \App\Models\Dispute::TYPES[$dispute->type] ?? $dispute->type }} · claiming {{ \App\Support\Money::format($dispute->amount_claimed) }}</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $dispute->status_badge }}">{{ $dispute->statusLabel() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-600">Opened {{ $dispute->opened_at->format('d M Y') }}.
                                @if($dispute->isOpen())Reply to the customer in the conversation below; iruali decides within 5 business days.
                                @elseif($dispute->amount_resolved > 0)Refund of {{ \App\Support\Money::format($dispute->amount_resolved) }} agreed; your share is taken from your next payout.
                                @else The claim was not upheld.@endif
                                @if($dispute->resolution_note)<span class="block">Note from iruali: {{ $dispute->resolution_note }}</span>@endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="border-t border-gray-100 px-5 py-4">
                @include('messaging._thread', [
                    'part' => $part,
                    'conversation' => $conversation,
                    'role' => 'seller',
                    'action' => route('seller.orders.messages.store', [$order, $part]),
                    'canReply' => $messagingOpen && (! $conversation || $conversation->isOpen()),
                    'closedNote' => 'This conversation is closed.',
                ])
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Your part of this order</h2>
                <span class="mt-2 inline-block rounded-full px-2 py-1 text-xs font-medium {{ $part->status_badge }}">{{ \App\Support\OrderStatus::label($part->status) }}</span>
                <div class="mt-2">@include('orders._tracking', ['part' => $part])</div>
                <p class="mt-2 text-xs text-gray-500">Placed {{ $order->created_at->format('d M Y, H:i') }}@if($otherShops) · also has items from {{ $otherShops }} other {{ \Illuminate\Support\Str::plural('shop', $otherShops) }}@endif</p>
                <p class="mt-2 text-xs text-gray-600">Payment: {{ strtolower(\App\Services\PaymentService::methodLabel($order->payment_method)) }} ·
                    <span class="rounded-full px-2 py-0.5 font-medium {{ \App\Services\PaymentService::statusBadge($order->payment_status) }}">{{ \App\Services\PaymentService::statusLabel($order->payment_status) }}</span>
                </p>
                <div class="mt-4 space-y-2">
                    @if($order->status === 'cancelled')
                        <p class="text-sm text-gray-600">This order was cancelled. Don't send these items.</p>
                    @elseif(empty($nextStatuses) && $part->isPickup())
                        <p class="text-sm text-gray-600">{{ __('The customer picks this part up from your shop: see Pickup below.') }}</p>
                    @elseif(empty($nextStatuses))
                        <p class="text-sm text-gray-600">Your part is {{ str_replace('_', ' ', $part->status) }}. Nothing more to update.</p>
                    @else
                        @foreach($nextStatuses as $status)
                            <form method="POST" action="{{ route('seller.orders.status', $order) }}" class="space-y-2 @if($status === 'shipped') rounded-lg border border-gray-200 p-3 @endif">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                @if($status === 'shipped')
                                    <p class="text-xs text-gray-600">Tell the customer how it travels: a courier with a tracking number or link, or the boat / flight and the day it should arrive.</p>
                                    @include('orders._tracking_form', ['part' => $part])
                                    <label for="tracking_note" class="block text-xs text-gray-600">Note for the customer (optional)</label>
                                    <input id="tracking_note" name="tracking_note" maxlength="255" placeholder="e.g. Collect from the jetty office after 4pm" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @endif
                                <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ ['processing' => 'Start preparing', 'shipped' => 'Mark as sent', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Mark as delivered'][$status] ?? ucfirst($status) }}</button>
                            </form>
                        @endforeach
                    @endif
                </div>
                @if(in_array($part->status, ['shipped', 'out_for_delivery'], true))
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-xs font-medium text-primary-700">Edit delivery details</summary>
                        <form method="POST" action="{{ route('seller.orders.tracking', [$order, $part]) }}" class="mt-2 space-y-2">
                            @csrf
                            @include('orders._tracking_form', ['part' => $part])
                            <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Save details</button>
                        </form>
                    </details>
                @endif
            </div>
            @include('seller.orders._delivery', ['order' => $order, 'part' => $part])
            <div class="rounded-lg bg-white p-5 shadow text-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Your earnings</h2>
                <dl class="mt-2 space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-600">Your items</dt><dd>{{ \App\Support\Money::format($part->subtotal) }}</dd></div>
                    @if((float) $part->shop_discount > 0)<div class="flex justify-between"><dt class="text-gray-600">{{ __('Your discounts (multi-buy, codes)') }}</dt><dd>&minus;{{ \App\Support\Money::format($part->shop_discount) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-gray-600">Commission ({{ rtrim(rtrim(number_format((float) $part->commission_rate, 2), '0'), '.') }}%)</dt><dd>&minus;{{ \App\Support\Money::format($part->commission_amount) }}</dd></div>
                    <div class="flex justify-between font-semibold text-gray-900 border-t border-gray-100 pt-1"><dt>You earn</dt><dd>{{ \App\Support\Money::format($part->seller_earnings) }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-gray-500">{{ ['pending' => 'Payable once your part is delivered and the customer has paid.', 'available' => 'Ready for the next payout.', 'paid_out' => 'Paid to you.', 'cancelled' => 'Cancelled: nothing is payable.'][$part->earningsState()] }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Ship to</h2>
                <p class="mt-2 text-sm text-gray-900">{{ $order->customerName() ?? 'Customer' }}@if($order->isGuest()) <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-semibold">Guest</span> <span class="block text-xs text-gray-500">{{ $order->guest_email }}</span>@endif</p>
                <p class="text-sm text-gray-700">{{ $order->shipping_address }}</p>
                <p class="text-sm text-gray-700">{{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}</p>
                <p class="text-sm text-gray-700">{{ $order->shipping_country }}</p>
                @if($order->shipping_phone)
                    <p class="mt-2 text-sm text-gray-700">Phone: <a href="tel:{{ $order->shipping_phone }}" dir="ltr" class="font-medium hover:underline">{{ $order->shipping_phone }}</a></p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
