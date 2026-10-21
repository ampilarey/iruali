{{-- Admin → Sellers → a shop's page: pre-orders, and "coming soon", allowed each on its own (User::allowsPreorders(),
     User::allowsComingSoon()). Admins only (the routes are on no staff role's list); off for every shop until
     turned on here, and a shop sees only what it is allowed. --}}
@if(\App\Support\StaffAccess::can('admin.sellers.preorders'))
    @php
        $presales = [
            'preorders' => [
                'route' => 'admin.sellers.preorders', 'title' => __('Pre-orders'), 'allow' => __('Allow pre-orders'),
                'help' => __('Taking orders for items that are out of stock or still to come: paid in full, with a booking fee, or once the shop confirms it can get the item. Turning it off switches pre-orders off on its products; orders already placed carry on.'),
            ],
            'coming_soon' => [
                'route' => 'admin.sellers.coming-soon', 'title' => __('Coming soon'), 'allow' => __('Allow "coming soon"'),
                'help' => __('Listing products before they go on sale, with "Email me when it goes on sale". Turning it off makes those products ordinary listings.'),
            ],
        ];
    @endphp
    <div class="rounded-lg bg-white p-5 shadow" data-presales-allowed>
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Pre-orders and "coming soon"') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Only shops you allow can use these, each on its own. A shop sees only what you allow it.') }}</p>
        <div class="mt-4 divide-y divide-gray-100">
            @foreach($presales as $what => $item)
                @php $isOn = (bool) $seller->getAttribute($what.'_allowed'); @endphp
                <div class="flex flex-wrap items-start justify-between gap-4 py-3" data-{{ str_replace('_', '-', $what) }}-allowed="{{ $isOn ? 'on' : 'off' }}">
                    <div class="max-w-3xl">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $item['title'] }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $item['help'] }}</p>
                        @if($isOn)
                            <p class="mt-1 text-xs text-gray-500">{{ __('On since :date', ['date' => $seller->getAttribute($what.'_allowed_at')?->format('d M Y')]) }}@if($by = \App\Models\User::find($seller->getAttribute($what.'_allowed_by'))) · {{ __('by :name', ['name' => $by->name]) }}@endif</p>
                            @unless($seller->isSeller())<p class="mt-1 text-xs font-medium text-amber-700">{{ __('Not in effect while the shop is not an approved, active seller.') }}</p>@endunless
                        @endif
                    </div>
                    <form method="POST" action="{{ route($item['route'], $seller) }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $isOn ? 0 : 1 }}">
                        <button class="rounded-lg px-4 py-2 text-sm font-medium {{ $isOn ? 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' : 'bg-primary-600 text-white hover:bg-primary-700' }}">{{ $isOn ? __('Turn off') : $item['allow'] }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif
