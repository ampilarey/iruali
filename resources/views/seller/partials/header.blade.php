@php
    $tabs = [
        'seller.dashboard' => ['Dashboard', 'seller.dashboard'],
        'seller.products.index' => ['Products', 'seller.products.*'],
        'seller.stock' => [__('Stock'), 'seller.stock*'],
        'seller.preorders' => [__('Pre-orders'), 'seller.preorders*'],
        'seller.campaigns' => [__('Campaigns'), 'seller.campaigns*'],
        'seller.discounts' => [__('Discount codes'), 'seller.discounts*'],
        'seller.quotes' => [__('Quote requests'), 'seller.quotes*'],
        'seller.orders' => ['Orders', 'seller.orders*'],
        'seller.returns' => ['Returns', 'seller.returns'],
        'seller.questions' => ['Questions', 'seller.questions'],
        'seller.reviews' => [__('Reviews'), 'seller.reviews'],
        'seller.earnings' => ['Earnings', 'seller.earnings'],
        'seller.analytics' => ['Analytics', 'seller.analytics'],
        'seller.performance' => [__('Performance'), 'seller.performance'],
        'seller.profile' => ['Profile', 'seller.profile*'],
        'seller.settings.bank' => [__('Settings'), 'seller.settings.*'],
        'seller.help' => [__('Help'), 'seller.help*'],
    ];
    // Pre-orders: only for a shop an admin allowed them, or one with pre-orders still to finish
    if (! app(\App\Services\PreorderService::class)->shopSees(\App\Support\CurrentShop::get())) {
        unset($tabs['seller.preorders']);
    }
@endphp
<div class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3 py-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-primary-600">Seller Centre</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
                @if(\App\Support\CurrentShop::get()->business_name)
                    <p class="text-sm text-gray-500">{{ \App\Support\CurrentShop::get()->business_name }}</p>
                @endif
                @include('seller.staff._signed_in_as')
            </div>
            <div class="flex flex-wrap items-center gap-3">
                {{-- An admin account that is also iruali's own shop goes back to Admin from here (User::maySell()) --}}
                @if(auth()->user()?->isStaff())
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50" data-switch-to-admin><x-icon name="shield" class="w-4 h-4" />{{ __('Switch to Admin') }}</a>
                @endif
                @isset($action)
                    <div>{{ $action }}</div>
                @endisset
            </div>
        </div>
        <nav class="flex gap-1 overflow-x-auto scrollbar-hide -mb-px">
            @foreach(\App\Support\ShopStaffAccess::visibleTabs($tabs + ['seller.staff' => [__('Staff'), 'seller.staff*']]) as $route => [$label, $pattern])
                <a href="{{ route($route) }}" data-seller-tab="{{ $route }}"
                   class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium {{ request()->routeIs($pattern) ? 'border-primary-500 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $label }}@if($route === 'seller.orders' && ($messageBadges['seller'] ?? 0) > 0) <span class="ms-1 rounded-full bg-primary px-1.5 text-[11px] font-bold text-white" title="Unread messages">{{ $messageBadges['seller'] }}</span>@endif
                </a>
            @endforeach
        </nav>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @include('seller.partials._holiday_reminder')
</div>
