{{-- Order page (account or guest): a pre-order with a booking fee (App\Services\BookingFeeService). The fee and the
     balance, when the balance is due, and what cancelling means for the fee. Needs $order. --}}
@if($order->hasBookingFee())
    @php $money = fn ($amount) => \App\Support\Money::format($amount); @endphp
    <div class="mt-4 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm space-y-2" data-booking-fee-box>
        <h3 class="font-semibold text-gray-900">{{ __('Booking fee and balance') }}</h3>
        <dl class="space-y-1">
            <div class="flex justify-between gap-3">
                <dt class="text-gray-700">{{ __('Booking fee') }}</dt>
                <dd class="text-end"><span class="font-semibold text-dark" dir="ltr">{{ $money($order->deposit_amount) }}</span>
                    <span class="block text-xs {{ $order->bookingFeePaid() ? 'text-green-700' : 'text-gray-600' }}">{{ $order->bookingFeePaid() ? __('Paid :date', ['date' => $order->deposit_paid_at?->translatedFormat('j M Y')]) : __('Not paid yet') }}</span></dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-700">{{ __('Balance') }}</dt>
                <dd class="text-end"><span class="font-semibold text-dark" dir="ltr">{{ $money($order->balanceAmount()) }}</span>
                    <span class="block text-xs {{ $order->payment_status === 'paid' ? 'text-green-700' : ($order->balanceDue() && $order->balance_due_on?->lt(today()) ? 'text-danger' : 'text-gray-600') }}" data-balance-state>
                        @if($order->payment_status === 'paid')
                            {{ __('Paid :date', ['date' => $order->paid_at?->translatedFormat('j M Y')]) }}
                        @elseif($order->balanceDue())
                            {{ __('Due by :date', ['date' => $order->balance_due_on?->translatedFormat('j M Y')]) }}
                        @elseif($order->status !== 'cancelled')
                            {{ __('Due when your item arrives') }}
                        @endif
                    </span></dd>
            </div>
        </dl>
        @if($order->status === 'cancelled' && (float) $order->deposit_kept_amount > 0)
            <p class="rounded-lg bg-white px-3 py-2 text-xs text-gray-700">{{ __('The booking fee of :fee was not refunded when the order was cancelled, as agreed at checkout.', ['fee' => $money($order->deposit_kept_amount)]) }}</p>
        @elseif($order->status !== 'cancelled' && $order->bookingFeePaid() && $order->payment_status !== 'paid')
            <p class="text-xs text-gray-700">{{ $order->deposit_refundable_at
                ? __('The shop moved the date later or is late, so if you cancel now you get everything back.')
                : __('If you cancel, the booking fee is not refunded. If the shop cancels, can\'t get the item, moves the date later or is more than :days days late, you get everything back.', ['days' => \App\Services\PreorderService::LATE_AFTER_DAYS]) }}</p>
        @endif
    </div>
@endif
