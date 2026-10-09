{{-- Admin order page, one shop part: delivered or picked up, the pickup progress and code (staff can
     read it to a customer who lost it), confirming a collection for the shop, and the packing slip.
     Needs $order and $part. --}}
<div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
    <span class="rounded-full px-2 py-0.5 font-semibold {{ $part->isPickup() ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700' }}">{{ $part->isPickup() ? __('Pickup from the shop') : __('Delivery') }}</span>
    @if((float) $part->delivery_surcharge > 0)
        <span class="text-gray-500">{{ __('Bulky-item charges: :amount', ['amount' => \App\Support\Money::format($part->delivery_surcharge)]) }}</span>
    @endif
    <a href="{{ route('admin.orders.parts.packing-slip', [$order, $part]) }}" target="_blank" rel="noopener" class="font-medium text-primary-700 hover:underline">{{ __('Packing slip') }}</a>
</div>
@if($part->isPickup())
    <div class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-gray-700 space-y-1">
        <p>{{ collect([$part->pickup_address, $part->pickup_island])->filter()->join(', ') }}@if($part->pickup_hours) · {{ $part->pickup_hours }}@endif</p>
        @if($part->pickup_collected_at)
            <p class="font-semibold text-green-800">{{ __('Collected on :date.', ['date' => $part->pickup_collected_at->format('d M Y, H:i')]) }}</p>
        @elseif($part->pickup_ready_at && $part->status !== 'cancelled')
            <p>{{ __('Ready for pickup since :date.', ['date' => $part->pickup_ready_at->format('d M Y, H:i')]) }} {{ __('Pickup code') }}: <span class="font-mono font-semibold" dir="ltr">{{ $part->pickup_code }}</span>@if($part->pickup_code_attempts > 0) · {{ trans_choice(':count wrong code entered|:count wrong codes entered', (int) $part->pickup_code_attempts, ['count' => (int) $part->pickup_code_attempts]) }}@endif</p>
            @if($order->status !== 'cancelled')
                <form method="POST" action="{{ route('admin.orders.parts.collected', [$order, $part]) }}" onsubmit="return confirm(@js(__('Confirm the customer has collected these items?')))">
                    @csrf
                    <button class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('Mark as collected') }}</button>
                </form>
            @endif
        @elseif($part->status !== 'cancelled')
            <p>{{ __('Not ready for pickup yet.') }}</p>
        @endif
    </div>
@endif
