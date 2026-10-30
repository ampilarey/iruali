{{-- An order sent with a boat (App\Support\BoatDelivery): the boat, its phone, the jetty, when it leaves and the
     island it goes to. Shown to the customer, the shop and admins. Needs $order. --}}
@if($order->isByBoat())
    @php
        $boat = $order->boat;
        $jetty = ($boat['jetty'] ?? '') === 'other' ? ($boat['jetty_other'] ?? '') : (\App\Support\BoatDelivery::jetties()[$boat['jetty'] ?? ''] ?? '');
        $departs = ! empty($boat['departs_at']) ? \Illuminate\Support\Carbon::parse($boat['departs_at']) : null;
    @endphp
    <div class="mt-3 rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm text-gray-800" data-order-boat>
        <p class="flex items-center gap-1.5 font-semibold text-primary-900"><x-icon name="truck" class="h-4 w-4" />{{ __('Send with a boat') }}</p>
        <dl class="mt-2 grid grid-cols-[auto,1fr] gap-x-3 gap-y-1">
            <dt class="text-gray-500">{{ __('Boat') }}</dt><dd class="font-medium">{{ $boat['name'] }}</dd>
            <dt class="text-gray-500">{{ __('Phone') }}</dt><dd><a href="tel:{{ $boat['phone'] ?? '' }}" dir="ltr" class="font-medium hover:underline">{{ $boat['phone'] ?? '' }}</a></dd>
            <dt class="text-gray-500">{{ __('Leaves from') }}</dt><dd>{{ $jetty }}</dd>
            @if($departs)<dt class="text-gray-500">{{ __('Leaves') }}</dt><dd class="font-semibold {{ $departs->isPast() ? 'text-gray-500' : 'text-danger' }}">{{ $departs->translatedFormat('l j M, H:i') }}</dd>@endif
            <dt class="text-gray-500">{{ __('Goes to') }}</dt><dd>{{ $boat['island'] ?? '' }}</dd>
            @if(! empty($boat['notes']))<dt class="text-gray-500">{{ __('Note') }}</dt><dd>{{ $boat['notes'] }}</dd>@endif
        </dl>
        <p class="mt-2 text-xs text-gray-600">{{ __('The boat\'s freight charge is paid to the boat on collection.') }}</p>
    </div>
@endif
