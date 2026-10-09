@extends('layouts.app')

{{-- "Rather not wait?": cancel an order with pre-order items before anything in it is sent (the normal
     cancellation of the whole order, with its refund). From My Orders or a guest's signed link. --}}

@section('title', __('Cancel order :number', ['number' => $order->order_number]).' - iruali')

@section('content')
<div class="max-w-2xl mx-auto px-4 lg:px-0 py-6 lg:py-10">
    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">{{ __('Cancel order :number?', ['number' => $order->order_number]) }}</h1>

    @if($canCancel)
        <p class="mt-2 text-gray-700">{{ __('This cancels the whole order, including any items that are already in stock.') }}</p>

        <ul class="mt-5 divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white" aria-label="{{ __('Order Items') }}">
            @foreach($order->items as $item)
                <li class="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                    <span class="min-w-0">
                        <span class="font-medium text-gray-900">{{ $item->displayName() }}</span> <span class="text-gray-600">× {{ $item->quantity }}</span>
                        @if($item->is_preorder)
                            <span class="mt-0.5 block text-xs text-primary-700">{{ $item->isPreorderWaiting() && $item->preorder_ship_date ? __('Pre-order: ships around :date', ['date' => $item->preorder_ship_date->translatedFormat('j M')]) : __('Pre-order: in stock now') }}</span>
                        @endif
                    </span>
                    <span class="shrink-0 font-medium text-gray-900" dir="ltr">{{ \App\Support\Money::format($item->price * $item->quantity) }}</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 space-y-1.5" data-refund-summary>
            @if($order->payment_status === 'paid' && $order->cardAmount() > 0)
                <p class="font-medium text-gray-900">{{ __('We refund :amount to the card you paid with.', ['amount' => \App\Support\Money::format($order->cardAmount())]) }}</p>
                <p>{{ __('Refunds usually reach you within 5–7 business days, depending on your bank.') }}</p>
            @elseif($order->payment_status !== 'paid')
                <p>{{ __('This order has not been paid for, so there is nothing to refund.') }}</p>
            @endif
            @if((float) $order->wallet_amount > 0)
                <p>{{ __(':amount goes back to your wallet straight away.', ['amount' => \App\Support\Money::format($order->wallet_amount)]) }}</p>
            @endif
            @if($order->points_redeemed > 0)
                <p>{{ __('The loyalty points you used are returned.') }}</p>
            @endif
        </div>

        <form method="POST" action="{{ $action }}" class="mt-6 flex flex-wrap items-center gap-3">
            @csrf
            <button type="submit" class="rounded-lg bg-danger px-5 py-2.5 text-sm font-semibold text-white hover:bg-danger-hover">{{ __('Yes, cancel my order') }}</button>
            <a href="{{ $back }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Keep my order') }}</a>
        </form>
    @else
        <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-700">
            @if($order->status === 'cancelled')
                <p>{{ __('This order has already been cancelled.') }}</p>
            @else
                <p>{{ __('This order can no longer be cancelled here: part of it has already been sent. Message the shop from your order if you need help.') }}</p>
            @endif
            <a href="{{ $back }}" class="mt-4 inline-flex rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">{{ __('Back to my order') }}</a>
        </div>
    @endif
</div>
@endsection
