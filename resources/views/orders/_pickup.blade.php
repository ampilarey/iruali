{{-- Customer order and tracking pages: a shop part the customer collects. Where and when, the steps, and
     the pickup code once the shop has it ready. The code is shown only with $showCode (the customer's
     own order page or the guest's signed page), never on the public tracking page. Needs $part. --}}
@if($part->isPickup() && $part->status !== 'cancelled')
    @php
        $showCode = $showCode ?? false;
        $ready = $part->pickup_ready_at !== null;
        $collected = $part->status === 'delivered';
        $pickupSteps = [__('Order placed') => true, __('Ready for pickup') => $ready || $collected, __('Collected') => $collected];
    @endphp
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm space-y-2" data-pickup>
        <p class="font-semibold text-gray-900 flex items-center gap-1.5"><x-icon name="store" class="w-4 h-4 text-primary" />{{ __('Pick up from :shop', ['shop' => $part->shopName()]) }}</p>
        <ol class="grid grid-cols-3 gap-1 text-[11px] text-center" aria-label="{{ __('Progress') }}">
            @foreach($pickupSteps as $label => $done)
                <li><span class="block h-1.5 rounded-full {{ $done ? 'bg-primary' : 'bg-gray-200' }}"></span><span class="mt-1 block {{ $done ? 'text-gray-900 font-medium' : 'text-gray-400' }}">{{ $label }}</span></li>
            @endforeach
        </ol>
        @if($part->pickup_address || $part->pickup_island)
            <p class="text-gray-700 flex items-start gap-1.5"><x-icon name="map-pin" class="w-4 h-4 mt-0.5 shrink-0 text-primary" /><span>{{ collect([$part->pickup_address, $part->pickup_island])->filter()->join(', ') }}</span></p>
        @endif
        @if($part->pickup_hours)
            <p class="text-gray-700 flex items-start gap-1.5"><x-icon name="clock" class="w-4 h-4 mt-0.5 shrink-0 text-primary" /><span>{{ $part->pickup_hours }}</span></p>
        @endif

        @if($collected)
            <p class="font-medium text-green-800">{{ __('Collected on :date.', ['date' => ($part->pickup_collected_at ?? $part->delivered_at)?->translatedFormat('j M Y, H:i')]) }}</p>
        @elseif($ready)
            @if($showCode)
                <div class="rounded-md bg-white px-3 py-2">
                    <p class="text-xs text-gray-600">{{ __('Your pickup code') }}</p>
                    <p class="font-mono text-2xl font-bold tracking-[0.3em] text-gray-900" dir="ltr">{{ $part->pickup_code }}</p>
                    <p class="text-xs text-gray-600">{{ __('Show this code at the shop. They need it to hand over your order, so do not share it with anyone else.') }}</p>
                </div>
            @else
                <p class="text-gray-700">{{ __('Ready for pickup. Your pickup code is in the email we sent you and on your order page.') }}</p>
            @endif
        @else
            <p class="text-gray-700">{{ __('The shop is getting it ready. We will send you a pickup code when you can collect it.') }}</p>
        @endif
    </div>
@endif
