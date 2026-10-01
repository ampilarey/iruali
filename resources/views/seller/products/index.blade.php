@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => 'My products'])
        @slot('action')
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('seller.products.export') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Export CSV') }}</a>
                <a href="{{ route('seller.products.import') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Import CSV') }}</a>
                <a href="{{ route('seller.products.create') }}" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">+ Add product</a>
            </div>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="GET" class="mb-4 flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or SKU"
                   class="w-full sm:w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
            <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Live</option>
                <option value="pending" @selected(request('status') === 'pending')>Awaiting approval</option>
            </select>
            <button class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
        </form>

        {{-- Bulk edit: the row checkboxes below belong to this form (form="bulk-form") --}}
        <form id="bulk-form" method="POST" action="{{ route('seller.products.bulk') }}" class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" data-bulk-bar>
            @csrf
            <span class="text-gray-600"><span data-bulk-count>0</span> {{ __('selected') }}:</span>
            <select name="action" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" data-bulk-action>
                <option value="price_percent">{{ __('Change price by %') }}</option>
                <option value="stock_set">{{ __('Set stock to') }}</option>
                <option value="activate">{{ __('Activate') }}</option>
                <option value="deactivate">{{ __('Deactivate') }}</option>
            </select>
            <input type="number" name="value" step="0.01" placeholder="{{ __('e.g. -10 or 5') }}" class="w-32 rounded-lg border border-gray-300 px-3 py-1.5 text-sm" dir="ltr" data-bulk-value>
            <button class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-900">{{ __('Preview changes') }}</button>
            @error('ids')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            @error('value')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3"><input type="checkbox" data-bulk-all class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" aria-label="{{ __('Select all') }}"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Stock</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($products as $product)
                            <tr>
                                <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $product->id }}" form="bulk-form" data-bulk-row class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" aria-label="{{ __('Select') }}"></td>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500">SKU {{ $product->sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $product->category->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ \App\Support\Money::format($product->price) }}</td>
                                <td class="px-4 py-3 text-right text-sm {{ $product->isLowStock() ? 'font-semibold text-red-600' : 'text-gray-900' }}">
                                    {{ $product->stock_quantity }}
                                    @if($product->has_variants)<span class="block text-xs font-normal text-gray-500">{{ trans_choice(':count variant|:count variants', $product->variants->count(), ['count' => $product->variants->count()]) }}</span>@endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($product->is_active)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">Live</span>
                                    @else
                                        <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800">Awaiting approval</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                    <a href="{{ route('seller.products.edit', $product) }}" class="font-medium text-primary-600 hover:text-primary-700">Edit</a>
                                    <form action="{{ route('seller.products.duplicate', $product) }}" method="POST" class="inline">
                                        @csrf
                                        <button class="ml-3 font-medium text-gray-600 hover:text-gray-800">{{ __('Duplicate') }}</button>
                                    </form>
                                    <form action="{{ route('seller.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Delete this product?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ml-3 font-medium text-red-600 hover:text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var bar = document.querySelector('[data-bulk-bar]');
        if (!bar) return;
        var rows = document.querySelectorAll('[data-bulk-row]'), all = document.querySelector('[data-bulk-all]');
        var count = bar.querySelector('[data-bulk-count]'), action = bar.querySelector('[data-bulk-action]'), value = bar.querySelector('[data-bulk-value]');
        function refresh() {
            var n = document.querySelectorAll('[data-bulk-row]:checked').length;
            count.textContent = n;
            var needsValue = action.value === 'price_percent' || action.value === 'stock_set';
            value.classList.toggle('hidden', !needsValue);
            value.required = needsValue;
            value.step = action.value === 'stock_set' ? '1' : '0.01';
        }
        rows.forEach(function (r) { r.addEventListener('change', refresh); });
        if (all) all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); refresh(); });
        action.addEventListener('change', refresh);
        refresh();
    })();
</script>
@endpush
