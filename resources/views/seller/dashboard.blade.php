@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => 'Dashboard'])
        @slot('action')
            <a href="{{ route('seller.products.create') }}" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">+ Add product</a>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if(! \App\Support\CurrentShop::get()->seller_approved)
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Your seller account is awaiting admin approval. You can prepare listings now; they go live once approved.
            </div>
        @endif

        @if($onboarding)
            @php $done = collect($onboarding)->where('done', true)->count(); $all = count($onboarding); @endphp
            <div class="rounded-lg border border-primary-100 bg-white p-5 shadow" data-onboarding>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('Set up your shop') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Finish every step so your products can go live. :done of :all done.', ['done' => $done, 'all' => $all]) }}</p>
                    </div>
                    @if(\Illuminate\Support\Facades\Route::has('seller.help.show'))<a href="{{ route('seller.help.show', 'getting-approved') }}" class="text-sm font-medium text-primary-600 hover:underline">{{ __('How approval works') }}</a>@endif
                </div>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100"><div class="h-2 rounded-full bg-primary-500" style="width: {{ $all ? round($done / $all * 100) : 0 }}%"></div></div>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach($onboarding as $item)
                        <li class="flex items-start gap-2 text-sm">
                            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $item['done'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">{{ $item['done'] ? '✓' : '·' }}</span>
                            <span>
                                @if($item['done'])<span class="text-gray-500 line-through">{{ $item['label'] }}</span>
                                @elseif(\App\Support\CurrentShop::canOpenUrl($item['url']))<a href="{{ $item['url'] }}" class="font-medium text-primary-700 hover:underline">{{ $item['label'] }}</a><span class="block text-xs text-gray-500">{{ $item['hint'] }}</span>
                                @else<span class="font-medium text-gray-900">{{ $item['label'] }}</span><span class="block text-xs text-gray-500">{{ __('The shop owner does this step.') }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach([
                ['Revenue', \App\Support\Money::format($stats['total_revenue']), 'From non-cancelled orders'],
                ['Orders', $stats['total_orders'], $stats['pending_orders'] . ' pending'],
                ['Products', $stats['total_products'], $stats['active_products'] . ' live · ' . $stats['pending_products'] . ' awaiting approval'],
                ['Low stock', $stats['low_stock'], 'At or below reorder point'],
            ] as [$label, $value, $hint])
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $value }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Recent orders</h2>
                    <a href="{{ route('seller.orders') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">View all</a>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse($recent_orders as $order)
                        <li>
                            <a href="{{ route('seller.orders.show', $order) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">#{{ $order->order_number }}</p>
                                    <p class="text-xs text-gray-500">{{ $order->customerName() ?? 'Customer' }}@if($order->isGuest()) (Guest)@endif · {{ $order->created_at->format('d M Y') }}</p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $order->status_badge }}">{{ \App\Support\OrderStatus::label($order->status) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">No orders yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-lg bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Recent products</h2>
                    <a href="{{ route('seller.products.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">View all</a>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse($recent_products as $product)
                        <li>
                            <a href="{{ route('seller.products.edit', $product) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $product->category->name ?? '—' }} · Stock {{ $product->stock_quantity }}</p>
                                </div>
                                <span class="text-sm font-medium text-gray-900">{{ \App\Support\Money::format($product->price) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">
                            No products yet. <a href="{{ route('seller.products.create') }}" class="font-medium text-primary-600">Add your first product</a>.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
