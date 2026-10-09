@extends('layouts.app')

@section('title', __('Order Status'))

@section('content')
<div class="max-w-2xl mx-auto px-4 lg:px-6 py-12">
    <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold mb-6 text-center">{{ __('Order Status') }}</h1>
        <div class="mb-4">
            <span class="font-semibold">{{ __('Order Number:') }}</span>
            <span>{{ $order->order_number }}</span>
        </div>
        <div class="mb-4">
            <span class="font-semibold">{{ __('Status:') }}</span>
            <span class="rounded-full px-2 py-0.5 text-sm font-semibold {{ $order->status_badge }}">{{ __(\App\Support\OrderStatus::label($order->status)) }}</span>
        </div>
        <div class="mb-4">
            <span class="font-semibold">{{ __('Total:') }}</span>
            <span class="force-ltr" dir="ltr">{{ \App\Support\Money::format($order->total_amount) }}</span>
        </div>

        @php $steps = \App\Support\OrderStatus::steps(); @endphp
        <div class="mb-6 space-y-3">
            <span class="font-semibold">{{ __('Delivery') }}</span>
            @forelse($order->sellerOrders()->with('seller')->get() as $part)
                @php $rank = \App\Enums\SellerOrderStatus::rankFor($part->status); @endphp
                <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium text-gray-900">{{ __('From :shop', ['shop' => $part->shopName()]) }}</p>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $part->status_badge }}">{{ $steps[$part->status] ?? __(\App\Support\OrderStatus::label($part->status)) }}</span>
                    </div>
                    @if($part->status !== 'cancelled' && ! $part->isPickup())
                        <ol class="grid grid-cols-5 gap-1 text-[11px] text-center" aria-label="{{ __('Progress') }}">
                            @foreach($steps as $key => $label)
                                @php $done = $rank >= \App\Enums\SellerOrderStatus::rankFor($key); @endphp
                                <li><span class="block h-1.5 rounded-full {{ $done ? 'bg-primary' : 'bg-gray-200' }}"></span><span class="mt-1 block {{ $done ? 'text-gray-900 font-medium' : 'text-gray-400' }}">{{ $label }}</span></li>
                            @endforeach
                        </ol>
                    @endif
                    @include('orders._tracking', ['part' => $part])
                    @include('orders._pickup', ['part' => $part, 'showCode' => false])
                </div>
            @empty
                <p class="text-sm text-gray-600">{{ __(\App\Support\OrderStatus::label($order->status)) }}</p>
            @endforelse
        </div>

        <div class="mb-6">
            <span class="font-semibold">{{ __('Items:') }}</span>
            <ul class="list-disc ms-6">
                @foreach($order->items as $item)
                    <li>{{ $item->displayName() }} x{{ $item->quantity }}</li>
                @endforeach
            </ul>
        </div>
        <div class="text-center">
            <a href="/track" class="text-primary-600 hover:underline">{{ __('Track another order') }}</a>
        </div>
    </div>
</div>
@endsection
