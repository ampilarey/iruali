{{-- Delivery details of one shop part (courier, tracking, boat/flight, expected date). Needs $part. --}}
@if($part->hasTrackingDetails() || $part->tracking_note || $part->out_for_delivery_at)
    <dl class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
        @if($part->courier || $part->tracking_number)
            <div>
                <dt class="text-xs text-gray-500">{{ __('Courier') }}</dt>
                <dd class="text-gray-900">{{ $part->courier }}@if($part->tracking_number) <span dir="ltr" class="font-mono">{{ $part->tracking_number }}</span>@endif</dd>
            </div>
        @endif
        @if($part->tracking_url)
            <div>
                <dt class="text-xs text-gray-500">{{ __('Track the parcel') }}</dt>
                <dd><a href="{{ $part->tracking_url }}" target="_blank" rel="noopener nofollow" class="text-primary hover:underline break-all">{{ __('Open tracking page') }}</a></dd>
            </div>
        @endif
        @if($part->vessel_or_flight)
            <div>
                <dt class="text-xs text-gray-500">{{ __('Boat or flight') }}</dt>
                <dd class="text-gray-900">{{ $part->vessel_or_flight }}</dd>
            </div>
        @endif
        @if($part->expected_delivery_date)
            <div>
                <dt class="text-xs text-gray-500">{{ __('Expected delivery') }}</dt>
                <dd class="text-gray-900">{{ $part->expected_delivery_date->translatedFormat('l j F') }}</dd>
            </div>
        @endif
        @if($part->out_for_delivery_at)
            <div>
                <dt class="text-xs text-gray-500">{{ __('Out for delivery since') }}</dt>
                <dd class="text-gray-900">{{ $part->out_for_delivery_at->translatedFormat('j M, H:i') }}</dd>
            </div>
        @endif
        @if($part->tracking_note)
            <div class="sm:col-span-2">
                <dt class="text-xs text-gray-500">{{ __('Note from the shop') }}</dt>
                <dd class="text-gray-900">{{ $part->tracking_note }}@if($part->shipped_at) <span class="text-xs text-gray-500">({{ $part->shipped_at->translatedFormat('j M') }})</span>@endif</dd>
            </div>
        @endif
    </dl>
@endif
