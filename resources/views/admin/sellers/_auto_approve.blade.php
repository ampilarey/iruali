{{-- Admin → Sellers → a shop's page: trusted shop (auto-approval of listings). Admins only
     (admin.sellers.auto-approve is on no staff role's list); off for every shop until turned on here. --}}
@if(\App\Support\StaffAccess::can('admin.sellers.auto-approve'))
    @php $trusted = (bool) $seller->auto_approve_listings; @endphp
    <div class="rounded-lg bg-white p-5 shadow" data-auto-approve="{{ $trusted ? 'on' : 'off' }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Trusted shop: publish without review') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('Normally every new product and every change to a live product waits for iruali to approve it. Turn this on for a shop you trust and its new listings and product changes go live at once, with no review. Stock changes never need review. Turn it off at any time to review the shop\'s listings again.') }}</p>
                @if($trusted)
                    <p class="mt-2 text-xs text-gray-500">{{ __('On since :date', ['date' => $seller->auto_approve_listings_at?->format('d M Y')]) }}@if($by = \App\Models\User::find($seller->auto_approve_listings_by)) · {{ __('by :name', ['name' => $by->name]) }}@endif</p>
                    @unless($seller->isSeller())<p class="mt-1 text-xs font-medium text-amber-700">{{ __('Not in effect while the shop is not an approved, active seller.') }}</p>@endunless
                @endif
            </div>
            <form method="POST" action="{{ route('admin.sellers.auto-approve', $seller) }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $trusted ? 0 : 1 }}">
                <button class="rounded-lg px-4 py-2 text-sm font-medium {{ $trusted ? 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' : 'bg-primary-600 text-white hover:bg-primary-700' }}">{{ $trusted ? __('Turn off: review again') : __('Turn on: trust this shop') }}</button>
            </form>
        </div>
    </div>
@endif
