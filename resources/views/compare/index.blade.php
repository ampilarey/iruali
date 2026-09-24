@extends('layouts.app')

@php
    $rows = [
        'price' => __('Price'),
        'rating' => __('Customer rating'),
        'stock' => __('Availability'),
        'brand' => __('Brand'),
        'model' => __('Model'),
        'sku' => __('SKU'),
        'department' => __('Department'),
        'seller' => __('Sold by'),
        'city' => __('Ships from'),
        'weight' => __('Weight'),
        'dimensions' => __('Dimensions'),
    ];
    // Leave out rows no product fills in
    $fields = ['model' => 'model', 'weight' => 'weight', 'dimensions' => 'dimensions'];
    foreach ($fields as $key => $column) {
        if ($products->every(fn ($p) => blank($p->{$column}))) {
            unset($rows[$key]);
        }
    }
@endphp

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-5 lg:py-8">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h1 class="font-display text-2xl lg:text-3xl font-bold text-dark">{{ __('Compare products') }}</h1>
            @if($products->isNotEmpty())
                <form action="{{ route('compare.clear') }}" method="POST">@csrf @method('DELETE')<button type="submit" class="text-sm font-semibold text-gray-600 hover:text-danger hover:underline">{{ __('Clear all') }}</button></form>
            @endif
        </div>

        @if($products->isEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-10 text-center">
                <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="columns" class="w-7 h-7" /></span>
                <p class="font-semibold text-lg">{{ __('Nothing to compare yet') }}</p>
                <p class="text-gray-600 text-sm mt-1">{{ __('Tick “Compare” on up to four products to see them side by side.') }}</p>
                <a href="{{ route('shop') }}" class="inline-block mt-5 px-5 py-2.5 rounded-lg bg-primary text-white font-semibold">{{ __('Shop all products') }}</a>
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
                <table class="w-full text-sm table-fixed" style="min-width: {{ 10 + $products->count() * 12 }}rem">
                    <thead>
                        <tr class="align-top">
                            <th scope="col" class="w-40 p-4 text-start text-gray-500 font-medium sticky start-0 bg-white">{{ trans_choice(':count product|:count products', $products->count(), ['count' => $products->count()]) }}</th>
                            @foreach($products as $product)
                                <td class="p-4 border-s border-gray-100">
                                    <form action="{{ route('compare.toggle', $product) }}" method="POST" class="text-end">@csrf<button type="submit" class="text-gray-400 hover:text-danger" aria-label="{{ __('Remove') }}"><x-icon name="x" class="w-5 h-5" /></button></form>
                                    <a href="{{ route('products.show', $product) }}" class="block aspect-square rounded-lg overflow-hidden bg-primary-50 mb-3"><img src="{{ $product->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="{{ $product->name }}" class="w-full h-full object-cover"></a>
                                    <a href="{{ route('products.show', $product) }}" class="font-semibold text-dark hover:text-primary hover:underline line-clamp-3">{{ $product->name }}</a>
                                    <x-add-to-cart :product="$product" class="mt-3" small />
                                </td>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $key => $label)
                            <tr class="align-top odd:bg-gray-50">
                                <th scope="row" class="p-4 text-start font-medium text-gray-600 sticky start-0 bg-inherit">{{ $label }}</th>
                                @foreach($products as $product)
                                    <td class="p-4 border-s border-gray-100 break-words">
                                        @switch($key)
                                            @case('price') <x-price :product="$product" /> @break
                                            @case('rating')
                                                @if($product->rating_count)<x-rating :value="round((float) $product->rating_avg, 1)" :count="$product->rating_count" />@else<span class="text-gray-400">{{ __('No reviews yet') }}</span>@endif
                                                @break
                                            @case('stock') <x-stock :quantity="(int) $product->stock_quantity" /> @break
                                            @case('brand') {{ $product->brand ?: '—' }} @break
                                            @case('model') {{ $product->model ?: '—' }} @break
                                            @case('sku') {{ $product->sku ?: '—' }} @break
                                            @case('department') {{ $product->category?->localized_name ?: '—' }} @break
                                            @case('seller') {{ $product->seller ? ($product->seller->business_name ?: $product->seller->name) : '—' }} @break
                                            @case('city') {{ $product->seller?->city ?: '—' }} @break
                                            @case('weight') {{ $product->weight ? rtrim(rtrim(number_format((float) $product->weight, 2), '0'), '.').' kg' : '—' }} @break
                                            @case('dimensions') {{ $product->dimensions ?: '—' }} @break
                                        @endswitch
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
