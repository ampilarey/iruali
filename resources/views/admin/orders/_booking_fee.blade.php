{{-- Admin order page → Payment: a pre-order with a booking fee (App\Services\BookingFeeService). The fee and the
     balance, the transfer slip for the fee, whether cancelling keeps the fee, and more time for the balance. Needs $order. --}}
@if($order->hasBookingFee())
    @php $money = fn ($amount) => \App\Support\Money::format($amount); @endphp
    <div class="mt-3 rounded-md border border-primary-200 bg-primary-50 p-3 text-sm space-y-2" data-admin-booking-fee>
        <p class="font-semibold text-gray-900">Pre-order with a booking fee</p>
        <dl class="space-y-1 text-xs">
            <div class="flex justify-between gap-2"><dt class="text-gray-600">Booking fee</dt>
                <dd class="text-end"><span class="font-semibold" dir="ltr">{{ $money($order->deposit_amount) }}</span>
                    · {{ $order->bookingFeePaid() ? 'paid '.$order->deposit_paid_at?->format('d M Y, H:i') : 'not paid' }}
                    @if($order->deposit_slip_path && \App\Support\StaffAccess::can('admin.orders.payment-slip'))
                        · <a href="{{ route('admin.orders.payment-slip', ['order' => $order, 'part' => 'deposit']) }}" target="_blank" rel="noopener" class="font-semibold text-primary underline">slip</a>
                    @endif
                </dd></div>
            <div class="flex justify-between gap-2"><dt class="text-gray-600">Balance</dt>
                <dd class="text-end"><span class="font-semibold" dir="ltr">{{ $money($order->balanceAmount()) }}</span>
                    · @if($order->payment_status === 'paid') paid {{ $order->paid_at?->format('d M Y, H:i') }}
                    @elseif($order->balanceDue()) <span class="{{ $order->balance_due_on?->lt(today()) ? 'font-semibold text-red-700' : '' }}">due {{ $order->balance_due_on?->format('d M Y') }}{{ $order->balance_overdue_at ? ' (overdue)' : '' }}</span>
                    @elseif($order->status === 'cancelled') —
                    @else asked for when the stock arrives
                    @endif
                </dd></div>
        </dl>
        @if((float) $order->deposit_kept_amount > 0)
            <p class="rounded bg-white px-2 py-1 text-xs text-gray-700">Booking fee kept on cancelling: {{ $money($order->deposit_kept_amount) }}, credited to the shop less commission.</p>
        @elseif($order->deposit_refundable_at && $order->status !== 'cancelled')
            <p class="rounded bg-white px-2 py-1 text-xs text-gray-700">Refundable since {{ $order->deposit_refundable_at->format('d M Y') }} (the date moved later, or the order is late): cancelling refunds everything.</p>
        @elseif(app(\App\Services\BookingFeeService::class)->keepable($order))
            <p class="text-xs text-gray-600">The customer cancelling keeps the fee for the shop. To cancel and keep it (e.g. the balance was not paid), tick "Keep the booking fee" by Cancel order.</p>
        @endif
        @if($order->balanceDue() && \App\Support\StaffAccess::can('admin.orders.balance-due'))
            <form method="POST" action="{{ route('admin.orders.balance-due', $order) }}" class="flex flex-wrap items-end gap-2 border-t border-primary-200 pt-2" data-balance-due-form>
                @csrf
                <label class="text-xs text-gray-700">More time for the balance
                    <input type="date" name="balance_due_on" required min="{{ today()->toDateString() }}" max="{{ today()->addDays(90)->toDateString() }}" value="{{ today()->addDays(\App\Services\BookingFeeService::BALANCE_DAYS)->toDateString() }}" class="mt-1 block rounded-lg border border-gray-300 px-2 py-1 text-sm">
                </label>
                <button class="rounded-lg border border-primary-300 bg-white px-3 py-1.5 text-sm font-semibold text-primary-700 hover:bg-primary-50">Give more time</button>
            </form>
            @error('balance_due_on')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
        @endif
    </div>
@endif
