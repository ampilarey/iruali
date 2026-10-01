@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => __('Stock')])
        @slot('action')
            @if($lowCount > 0)
                <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-800">{{ trans_choice(':count product low on stock|:count products low on stock', $lowCount, ['count' => $lowCount]) }}</span>
            @endif
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name or SKU') }}"
                   class="w-full sm:w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="low" value="1" @checked($lowOnly) class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                {{ __('Low stock only') }}
            </label>
            <button class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">{{ __('Filter') }}</button>
        </form>

        <form method="POST" action="{{ route('seller.stock.update', request()->only('low', 'q')) }}">
            @csrf
            @method('PUT')
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Product') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('SKU') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Price') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Low-stock alert at') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Stock') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($products as $product)
                                @php $low = $product->isLowStock(); @endphp
                                <tr class="{{ $low ? 'bg-red-50' : '' }}">
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                        @unless($product->is_active)<span class="text-xs text-yellow-800">{{ __('Awaiting approval') }}</span>@endunless
                                        @if($product->has_variants)<span class="text-xs text-gray-500">{{ trans_choice(':count variant|:count variants', $product->variants->count(), ['count' => $product->variants->count()]) }}</span>@endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700" dir="ltr">{{ $product->sku }}</td>
                                    <td class="px-4 py-3 text-end text-sm text-gray-900">{{ \App\Support\Money::format($product->price) }}</td>
                                    <td class="px-4 py-3 text-end text-sm text-gray-500">{{ $product->reorder_point }}</td>
                                    <td class="px-4 py-3 text-end">
                                        @if($product->has_variants)
                                            <span class="text-sm {{ $low ? 'font-semibold text-red-700' : 'text-gray-700' }}" title="{{ __('With variants, stock is the total of the variants below.') }}">{{ $product->stock_quantity }}</span>
                                        @else
                                            <input type="number" name="products[{{ $product->id }}]" value="{{ old('products.'.$product->id, $product->stock_quantity) }}" min="0" max="999999" dir="ltr"
                                                   class="w-24 rounded-md border px-2 py-1 text-end text-sm focus:border-primary-500 focus:ring-primary-500 {{ $low ? 'border-red-300 font-semibold text-red-700' : 'border-gray-300' }}">
                                        @endif
                                    </td>
                                </tr>
                                @if($product->has_variants)
                                    @foreach($product->variants as $variant)
                                        @php $variant->setRelation('product', $product); $vLow = $variant->is_active && $variant->isLowStock(); @endphp
                                        <tr class="{{ $vLow ? 'bg-red-50' : '' }} {{ $variant->is_active ? '' : 'opacity-60' }}">
                                            <td class="px-4 py-2 ps-10 text-sm text-gray-700">
                                                <span class="text-gray-400 me-1">↳</span>{{ $variant->displayNameWithKeys() }}
                                                @unless($variant->is_active)<span class="ms-1 text-xs text-gray-500">({{ __('Inactive') }})</span>@endunless
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700" dir="ltr">{{ $variant->sku }}</td>
                                            <td class="px-4 py-2 text-end text-sm text-gray-900">{{ \App\Support\Money::format($variant->effectivePrice()) }}</td>
                                            <td class="px-4 py-2 text-end text-sm text-gray-500">{{ $variant->low_stock_threshold ?? $product->reorder_point }}</td>
                                            <td class="px-4 py-2 text-end">
                                                <input type="number" name="variants[{{ $variant->id }}]" value="{{ old('variants.'.$variant->id, $variant->stock_quantity) }}" min="0" max="999999" dir="ltr"
                                                       class="w-24 rounded-md border px-2 py-1 text-end text-sm focus:border-primary-500 focus:ring-primary-500 {{ $vLow ? 'border-red-300 font-semibold text-red-700' : 'border-gray-300' }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">{{ $lowOnly ? __('Nothing is low on stock.') : __('No products found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($products->isNotEmpty())
                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-4 py-3">
                        <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save stock levels') }}</button>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
