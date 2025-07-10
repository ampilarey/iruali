@extends('layouts.app')

@section('title', __('Order Status'))

@section('content')
<div class="max-w-2xl mx-auto py-12">
    <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold mb-6 text-center">{{ __('Order Status') }}</h1>
        <div class="mb-4">
            <span class="font-semibold">{{ __('Order Number:') }}</span>
            <span>{{ $order->order_number }}</span>
        </div>
        <div class="mb-4">
            <span class="font-semibold">{{ __('Status:') }}</span>
            <span>{{ ucfirst($order->status) }}</span>
        </div>
        <div class="mb-4">
            <span class="font-semibold">{{ __('Total:') }}</span>
            <span>ރ{{ number_format($order->total_amount, 2) }}</span>
        </div>
        <div class="mb-6">
            <span class="font-semibold">{{ __('Items:') }}</span>
            <ul class="list-disc ml-6">
                @foreach($order->items as $item)
                    <li>{{ $item->product->name }} x{{ $item->quantity }}</li>
                @endforeach
            </ul>
        </div>
        <div class="text-center">
            <a href="/track" class="text-primary-600 hover:underline">{{ __('Track another order') }}</a>
        </div>
    </div>
</div>
@endsection 