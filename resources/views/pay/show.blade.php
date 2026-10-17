@extends('layouts.app')

@section('title', __('Pay for order :number', ['number' => $order->order_number]))

@push('styles')<meta name="robots" content="noindex, nofollow">@endpush

@section('content')
{{-- Payment link page (App\Support\PayLink): pay an unpaid order by card or bank transfer, no sign-in --}}
@php
    $amount = \App\Support\Money::format($order->cardAmount());
    $bank = \App\Services\ManualPaymentService::transferDetails();
@endphp
<div class="max-w-xl mx-auto px-4 py-8 space-y-5" data-pay-page>
    <div>
        <h1 class="font-display text-2xl font-bold text-dark">{{ __('Pay for order :number', ['number' => $order->order_number]) }}</h1>
        <p class="mt-1 text-gray-600">{{ $order->customerName() }}</p>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
        <ul class="divide-y divide-gray-100">
            @foreach($order->items as $item)
                <li class="flex justify-between gap-3 py-2"><span class="min-w-0 text-gray-800">{{ $item->quantity }} × {{ $item->product ? $item->displayName() : __('Item') }}</span><span class="shrink-0 text-gray-700" dir="ltr">{{ \App\Support\Money::format($item->price * $item->quantity) }}</span></li>
            @endforeach
        </ul>
        <div class="mt-2 flex justify-between border-t border-gray-100 pt-2 text-base font-semibold text-dark"><span>{{ __('To pay') }}</span><span dir="ltr">{{ $amount }}</span></div>
    </section>

    @if($order->payment_status === 'paid')
        <p class="rounded-xl bg-green-50 p-4 font-medium text-green-800" data-pay-paid>{{ __('This order is paid. Thank you!') }}</p>
    @elseif($order->status === 'cancelled')
        <p class="rounded-xl bg-gray-100 p-4 text-gray-700" data-pay-cancelled>{{ __('This order was cancelled, so there is nothing to pay.') }}</p>
    @elseif(! $payable)
        <p class="rounded-xl bg-gray-100 p-4 text-gray-700">{{ __('This order is not waiting for payment.') }}</p>
    @elseif($order->payment_slip_uploaded_at && $order->payment_method === 'transfer')
        <p class="rounded-xl bg-green-50 p-4 text-green-800" data-slip-received>{{ __('We have your transfer slip (sent :date). We are checking the payment and will email you once it is confirmed.', ['date' => $order->payment_slip_uploaded_at->translatedFormat('j M, H:i')]) }}</p>
    @else
        @if($card)
            <form method="POST" action="{{ $cardAction }}" data-pay-card>
                @csrf
                <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-semibold text-white hover:bg-primary-hover">{{ __('Pay :amount by card', ['amount' => $amount]) }}</button>
                <p class="mt-1 text-center text-xs text-gray-500">{{ __('On the secure Bank of Maldives payment page.') }}</p>
            </form>
        @endif

        @if($transfer)
            <section class="rounded-xl border border-primary-200 bg-primary-50 p-4 space-y-3 text-sm" data-pay-transfer>
                <h2 class="font-semibold text-gray-900">{{ $card ? __('Or pay by bank transfer') : __('Pay by bank transfer') }}</h2>
                <p class="text-gray-700">{{ __('Transfer :amount to this account, with your order number as the reference:', ['amount' => $amount]) }}</p>
                <dl class="rounded-lg bg-white p-3 space-y-1">
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Bank') }}</dt><dd class="font-medium text-dark">{{ $bank['bank'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Account name') }}</dt><dd class="font-medium text-dark">{{ $bank['name'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Account number') }}</dt><dd class="font-semibold text-dark select-all" dir="ltr">{{ $bank['number'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Reference') }}</dt><dd class="font-semibold text-dark select-all" dir="ltr">{{ $order->order_number }}</dd></div>
                </dl>
                <form method="POST" action="{{ $slipAction }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <label for="slip" class="block font-medium text-gray-900">{{ __('Then send us the transfer slip') }}</label>
                    <input id="slip" name="slip" type="file" required accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm">
                    @error('slip')<p class="text-danger text-xs">{{ $message }}</p>@enderror
                    <button type="submit" class="w-full rounded-lg border border-primary-300 bg-white px-4 py-2.5 font-semibold text-primary-700 hover:bg-primary-50">{{ __('Send transfer slip') }}</button>
                </form>
            </section>
        @endif

        @unless($card || $transfer)
            <p class="rounded-xl bg-amber-50 p-4 text-amber-900">{{ __('Online payment is not available right now. Please contact us to pay.') }}</p>
        @endunless
    @endif

    <p class="text-center text-xs text-gray-500">{{ __('Questions? Contact iruali customer service.') }} <a href="{{ route('policies.about') }}" class="text-primary hover:underline">{{ __('Contact us') }}</a></p>
</div>
@endsection
