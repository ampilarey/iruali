{{-- A card order waiting for payment, at the top of its page (as on Bake & Grill): red "Payment didn't go
     through" with "Pay again" after a failed attempt, otherwise the amount and "Pay now". An order not paid at all
     can go back to the cart to change ("Change my order", OrderController::backToCart()). Needs $order. --}}
@php $payments = app(\App\Services\PaymentService::class); @endphp
@if($payments->canPayOnline($order))
    @php
        $lastAttempt = $order->paymentTransactions()->latest('id')->first();
        $failed = (bool) $lastAttempt?->hasFailed();
        $amount = \App\Support\Money::format($order->cardAmount());
        $part = \App\Services\BookingFeeService::nextPart($order);
    @endphp
    <div class="mb-6 rounded-2xl border p-4 sm:p-5 {{ $failed ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}" data-pay-banner="{{ $failed ? 'failed' : 'waiting' }}" role="{{ $failed ? 'alert' : 'status' }}">
        <p class="text-base font-semibold {{ $failed ? 'text-red-800' : 'text-amber-900' }}">
            {{ $failed ? __('Your payment didn\'t go through') : __('Your order is waiting for payment') }}
        </p>
        <p class="mt-1 text-sm {{ $failed ? 'text-red-700' : 'text-amber-900' }}">
            @if($failed)
                {{ __('Nothing was charged. Try again, or use another card.') }}
            @elseif($lastAttempt && ! $lastAttempt->isConfirmed())
                {{ __('If you just paid, it can take a minute for the bank to confirm. Otherwise, pay below.') }}
            @else
                {{ __('The shop starts on it once it is paid.') }}
            @endif
        </p>
        <form method="POST" action="{{ route('payments.bml.pay', $order) }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover" data-pay-again>{{ match ($part) {
                'deposit' => __('Pay the booking fee of :amount now', ['amount' => $amount]),
                'balance' => __('Pay the balance of :amount now', ['amount' => $amount]),
                default => $failed ? __('Pay again :amount', ['amount' => $amount]) : __('Pay :amount now', ['amount' => $amount]),
            } }}</button>
        </form>
        @if($order->payment_status === 'unpaid' && $order->status === 'pending' && $order->user_id === auth()->id())
            <form method="POST" action="{{ route('orders.backToCart', $order) }}" class="mt-2" onsubmit="return confirm('{{ __('Cancel this order and put its items back in your cart?') }}')">
                @csrf
                <button type="submit" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50" data-back-to-cart>{{ __('Change my order') }}</button>
            </form>
        @endif
        <p class="mt-3 flex items-center gap-1.5 text-xs text-gray-600"><x-icon name="shield" class="h-4 w-4 text-primary" />{{ __('You pay on Bank of Maldives\' secure page. iruali never sees your card details.') }}</p>
    </div>
@endif
