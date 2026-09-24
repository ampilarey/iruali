@php
    $tabs = [
        'seller.dashboard' => ['Dashboard', 'seller.dashboard'],
        'seller.products.index' => ['Products', 'seller.products.*'],
        'seller.orders' => ['Orders', 'seller.orders*'],
        'seller.analytics' => ['Analytics', 'seller.analytics'],
        'seller.profile' => ['Profile', 'seller.profile*'],
    ];
@endphp
<div class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3 py-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-primary-600">Seller Centre</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
                @if(auth()->user()->business_name)
                    <p class="text-sm text-gray-500">{{ auth()->user()->business_name }}</p>
                @endif
            </div>
            @isset($action)
                <div>{{ $action }}</div>
            @endisset
        </div>
        <nav class="flex gap-1 overflow-x-auto scrollbar-hide -mb-px">
            @foreach($tabs as $route => [$label, $pattern])
                <a href="{{ route($route) }}"
                   class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium {{ request()->routeIs($pattern) ? 'border-primary-500 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $label }}
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
</div>
