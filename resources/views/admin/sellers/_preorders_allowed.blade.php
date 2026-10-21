{{-- Admin → Sellers → a shop's page: pre-orders and "coming soon" allowed (User::allowsPreorders()). Admins only
     (admin.sellers.preorders is on no staff role's list); off for every shop until turned on here. --}}
@if(\App\Support\StaffAccess::can('admin.sellers.preorders'))
    @php $preordersOn = (bool) $seller->preorders_allowed; @endphp
    <div class="rounded-lg bg-white p-5 shadow" data-preorders-allowed="{{ $preordersOn ? 'on' : 'off' }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Pre-orders and "coming soon"') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('Only shops you allow can take pre-orders (with or without a booking fee, or paid once they confirm they can get the item) and list products as "coming soon". Turning it off stops new pre-orders at once; orders already placed carry on.') }}</p>
                @if($preordersOn)
                    <p class="mt-2 text-xs text-gray-500">{{ __('On since :date', ['date' => $seller->preorders_allowed_at?->format('d M Y')]) }}@if($by = \App\Models\User::find($seller->preorders_allowed_by)) · {{ __('by :name', ['name' => $by->name]) }}@endif</p>
                    @unless($seller->isSeller())<p class="mt-1 text-xs font-medium text-amber-700">{{ __('Not in effect while the shop is not an approved, active seller.') }}</p>@endunless
                @endif
            </div>
            <form method="POST" action="{{ route('admin.sellers.preorders', $seller) }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $preordersOn ? 0 : 1 }}">
                <button class="rounded-lg px-4 py-2 text-sm font-medium {{ $preordersOn ? 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' : 'bg-primary-600 text-white hover:bg-primary-700' }}">{{ $preordersOn ? __('Turn off') : __('Allow pre-orders') }}</button>
            </form>
        </div>
    </div>
@endif
