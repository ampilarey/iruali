{{-- Buttons for the statuses this order can move to next. Needs: $action (POST url), $nextStatuses (array), $order.
     Cancelling a pre-order whose booking fee is paid offers to keep the fee (BookingFeeService). --}}
@php
    $labels = [
        'processing' => 'Mark as processing',
        'shipped' => 'Mark as shipped',
        'out_for_delivery' => 'Mark as out for delivery',
        'delivered' => 'Mark as delivered',
        'cancelled' => 'Cancel order',
    ];
@endphp
@if(count($nextStatuses))
    <div class="flex flex-wrap gap-2">
        @foreach($nextStatuses as $status)
            <form method="POST" action="{{ $action }}"
                  @if($status === 'cancelled') onsubmit="return confirm('Cancel this order? Stock is returned and loyalty points are reversed.')" @endif>
                @csrf
                <input type="hidden" name="status" value="{{ $status }}">
                @if($status === 'cancelled' && app(\App\Services\BookingFeeService::class)->keepable($order))
                    <label class="mb-2 flex items-start gap-2 text-xs text-gray-700" data-keep-booking-fee>
                        <input type="checkbox" name="keep_booking_fee" value="1" class="mt-0.5 rounded text-primary-600 focus:ring-primary-500">
                        <span>Keep the booking fee ({{ \App\Support\Money::format($order->deposit_amount) }}): the customer asked to cancel or did not pay the balance. Left unticked, everything is refunded (the shop cancels or can't get it).</span>
                    </label>
                @endif
                <button type="submit"
                        class="rounded-lg px-4 py-2 text-sm font-semibold {{ $status === 'cancelled' ? 'border border-danger text-danger hover:bg-danger-50' : 'bg-primary text-white hover:bg-primary-hover' }}">
                    {{ $labels[$status] ?? ucfirst($status) }}
                </button>
            </form>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-600">This order is {{ str_replace('_', ' ', $order->status) }}. There is nothing more to update.</p>
@endif
