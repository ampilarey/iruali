{{-- Checkout: "Send with a boat" (App\Support\BoatDelivery). The boat, the jetty it leaves from, when, the island
     it goes to, and who to call; filled in from the customer's last boat order. Shown when the boat card is chosen
     (resources/js/checkout-address.js); StoreOrderRequest turns it into a Greater Malé delivery to the jetty.
     Needs $field, $user, $byBoat, $boatLast, $boatFrom (the island it leaves from), $islandsByAtoll. --}}
@php
    $boatValue = fn (string $key, ?string $fallback = null) => old('boat_'.$key, $boatLast[$key] ?? $fallback);
    // A boat leaving soon: the form suggests no time, but refuses one less than two hours away
    $earliest = now()->addHours(\App\Support\BoatDelivery::MIN_HOURS)->format('Y-m-d\TH:i');
    $departs = old('boat_departs_at');
@endphp
<div class="space-y-4 {{ $byBoat ? '' : 'hidden' }}" data-boat-panel>
    <p class="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-900">{{ __('Tell us which boat to give it to. The shop hands your order to the boat before it leaves :island, and we text you when it is on board. The boat\'s freight charge is paid to the boat when you collect.', ['island' => $boatFrom?->localized_name]) }}</p>
    @if($user && trim((string) $user->name) === '')
        <div>
            <label for="boat_customer_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name to collect it') }}</label>
            <input type="text" id="boat_customer_name" name="customer_name" required autocomplete="name" maxlength="120" class="{{ $field }}" value="{{ old('customer_name') }}" @disabled(! $byBoat)>
            @error('customer_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
    @endif
    <div>
        <label for="boat_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Boat name') }}</label>
        <input type="text" id="boat_name" name="boat_name" required maxlength="80" class="{{ $field }}" value="{{ $boatValue('name') }}" placeholder="{{ __('e.g. Express 3, Dhonfulhu Ferry') }}" @disabled(! $byBoat)>
        @error('boat_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="boat_phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Boat or captain\'s phone') }}</label>
        <input type="tel" id="boat_phone" name="boat_phone" required inputmode="tel" dir="ltr" maxlength="20" class="{{ $field }}" value="{{ $boatValue('phone') }}" placeholder="7771234" @disabled(! $byBoat)>
        <p class="mt-1 text-xs text-gray-500">{{ __('The shop calls this number to hand it over.') }}</p>
        @error('boat_phone')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="boat_jetty" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Leaves from') }}</label>
        <select id="boat_jetty" name="boat_jetty" required class="{{ $field }}" data-boat-jetty @disabled(! $byBoat)>
            <option value="">{{ __('Choose the jetty') }}</option>
            @foreach(\App\Support\BoatDelivery::jetties($boatFrom) as $key => $label)
                <option value="{{ $key }}" @selected($boatValue('jetty') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('boat_jetty')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        <input type="text" name="boat_jetty_other" maxlength="100" class="mt-2 {{ $field }} {{ $boatValue('jetty') === 'other' ? '' : 'hidden' }}" value="{{ $boatValue('jetty_other') }}" placeholder="{{ __('Where exactly?') }}" aria-label="{{ __('Where exactly?') }}" data-boat-jetty-other @disabled(! $byBoat)>
        @error('boat_jetty_other')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="boat_departs_at" class="block text-sm font-medium text-gray-700 mb-1">{{ __('When the boat leaves') }}</label>
        <input type="datetime-local" id="boat_departs_at" name="boat_departs_at" required min="{{ $earliest }}" class="{{ $field }}" value="{{ $departs }}" @disabled(! $byBoat)>
        <p class="mt-1 text-xs text-gray-500">{{ __('At least :hours hours from now, while the shop is open.', ['hours' => \App\Support\BoatDelivery::MIN_HOURS]) }}</p>
        @error('boat_departs_at')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="boat_island" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Island the boat goes to') }}</label>
        <input type="text" id="boat_island" name="boat_island" required maxlength="100" list="boat-islands" class="{{ $field }}" value="{{ $boatValue('island') }}" placeholder="{{ __('Your island') }}" @disabled(! $byBoat)>
        <datalist id="boat-islands">
            @foreach($islandsByAtoll as $atollName => $islands)
                @foreach($islands as $isl)<option value="{{ $isl->localized_name }}, {{ $atollName }}">@endforeach
            @endforeach
        </datalist>
        @error('boat_island')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="boat_shipping_phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Your phone') }}</label>
        <input type="tel" id="boat_shipping_phone" name="shipping_phone" required inputmode="tel" autocomplete="tel" dir="ltr" class="{{ $field }}" value="{{ old('shipping_phone', $user?->phone) }}" placeholder="7771234" @disabled(! $byBoat)>
        @error('shipping_phone')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="boat_notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Note for the shop') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
        <input type="text" id="boat_notes" name="boat_notes" maxlength="200" class="{{ $field }}" value="{{ $boatValue('notes') }}" placeholder="{{ __('e.g. Give it to Ahmed on board') }}" @disabled(! $byBoat)>
    </div>
    <input type="hidden" name="shipping_country" value="Maldives" @disabled(! $byBoat)>
</div>
