{{-- Admin → Sellers → a shop's page: its home island and where it delivers (App\Services\ShopCoverage). --}}
@if(\App\Support\StaffAccess::can('admin.sellers.coverage'))
    @php $coverageService = app(\App\Services\ShopCoverage::class); @endphp
    <div class="rounded-lg bg-white p-5 shadow" id="coverage" data-admin-coverage>
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Home island and delivery area') }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Based in') }}: <strong>{{ $seller->homeIsland?->label() ?? __('not set yet') }}</strong>
            · {{ __('Delivers to') }}: <strong>{{ $coverageService->summary($seller) }}</strong>
        </p>
        <form method="POST" action="{{ route('admin.sellers.coverage', $seller) }}" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            @include('seller.partials.coverage-fields', ['shop' => $seller, 'islandsByAtoll' => app(\App\Services\DeliveryService::class)->islandsByAtoll(), 'homeRequired' => false, 'prefix' => 'admin'])
            <div class="flex justify-end">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save delivery area') }}</button>
            </div>
        </form>
    </div>
@endif
