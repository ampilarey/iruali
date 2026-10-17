{{-- Admin order page: a bank transfer or cash on delivery order (ManualPaymentService). The transfer slip, and
     "Confirm payment received" / "Cash received" once the money is with iruali. Needs $order. --}}
@php $manual = \App\Services\ManualPaymentService::class; @endphp
@if(in_array($order->payment_method, $manual::METHODS, true))
    <div class="mt-3 space-y-2 text-sm" data-manual-payment>
        @if($order->payment_method === $manual::TRANSFER)
            @if($order->payment_slip_uploaded_at && $manual::slipExists($order))
                <p class="text-xs text-gray-600">Transfer slip uploaded {{ $order->payment_slip_uploaded_at->format('d M Y, H:i') }}.
                    @if(\App\Support\StaffAccess::can('admin.orders.payment-slip'))
                        <a href="{{ route('admin.orders.payment-slip', $order) }}" target="_blank" rel="noopener" class="font-semibold text-primary underline">View slip</a>
                    @endif
                </p>
            @elseif($manual::awaiting($order))
                <p class="text-xs text-gray-600">No transfer slip yet. Cancelled automatically {{ $manual::payWithinHours() }} hours after the order if none comes.</p>
            @endif
        @elseif($manual::awaiting($order))
            <p class="text-xs text-gray-600">Cash on delivery: the shop collects {{ \App\Support\Money::format($order->cardAmount()) }}. Record it here once the cash is with iruali; the shop's earnings are paid only after that.</p>
        @endif

        @if($manual::awaiting($order) && \App\Support\StaffAccess::can('admin.orders.confirm-payment'))
            <form method="POST" action="{{ route('admin.orders.confirm-payment', $order) }}" class="space-y-2" onsubmit="return confirm('Record {{ \App\Support\Money::format($order->cardAmount()) }} as received for order {{ $order->order_number }}?')">
                @csrf
                <label for="payment_reference" class="sr-only">Reference</label>
                <input id="payment_reference" name="payment_reference" maxlength="100" placeholder="{{ $order->payment_method === $manual::TRANSFER ? 'Bank reference (optional)' : 'Note, e.g. who brought the cash (optional)' }}" class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                <button class="w-full rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ $order->payment_method === $manual::TRANSFER ? 'Confirm payment received' : 'Cash received' }}</button>
            </form>
        @elseif($order->payment_status === 'paid' && ($order->payment_reference || $order->payment_confirmed_by))
            <p class="text-xs text-gray-600">Confirmed by {{ \App\Models\User::find($order->payment_confirmed_by)?->name ?? 'staff' }}@if($order->payment_reference) · {{ $order->payment_reference }}@endif</p>
        @endif

        @if($order->payment_status === 'paid' && $order->status === 'cancelled')
            <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">This payment needs a refund. Send it back to the customer by bank transfer (or to their wallet), then record it below.</p>
        @endif
    </div>
@endif
