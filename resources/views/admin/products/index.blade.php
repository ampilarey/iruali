@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-100">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-2 py-3 lg:py-4">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-900 lg:text-2xl">Manage Products</h1>
                </div>
                <div class="flex items-center space-x-4">
                    @php $changesWaiting = \App\Models\ProductChangeRequest::pending()->whereHas('product')->count(); @endphp
                    <a href="{{ route('admin.product-changes') }}" class="border border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 px-4 py-2 rounded-lg text-sm font-medium" data-changes-waiting="{{ $changesWaiting }}">
                        {{ __('Changes waiting') }} ({{ $changesWaiting }})
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-3 py-4 sm:px-6 lg:px-8 lg:py-6">
        @if(session('success'))<div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>@endif
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seller</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($products ?? [] as $product)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gray-300 rounded-lg flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">P</span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $product->name ?? 'N/A' }}</div>
                                            @if($product->source_url)
                                                {{-- Where the shop buys this pre-order abroad (ProductImportService) --}}
                                                <a href="{{ $product->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-0.5 block max-w-xs truncate text-xs text-primary-700 underline" data-source-url>Bought from: {{ parse_url($product->source_url, PHP_URL_HOST) }} ↗</a>
                                            @endif
                                            @if($product->has_variants)
                                                <div class="mt-1 text-xs text-gray-500">
                                                    {{ trans_choice(':count variant|:count variants', $product->variants->count(), ['count' => $product->variants->count()]) }}:
                                                    @foreach($product->variants as $variant)
                                                        <span class="inline-block rounded bg-gray-100 px-1.5 py-0.5 {{ $variant->is_active ? '' : 'line-through' }}" dir="ltr">{{ $variant->displayName() }} · {{ $variant->sku }} · {{ $variant->stock_quantity }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $product->category->name ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $product->seller->name ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ \App\Support\Money::format($product->price ?? 0) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ ($product->is_active ?? false) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ($product->is_active ?? false) ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    @if(!($product->is_active ?? false))
                                    <form method="POST" action="{{ route('admin.products.approve', $product->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-900 mr-2">Approve</button>
                                    </form>
                                    @endif
                                    <a href="{{ route('products.show', $product) }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No products found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if(isset($products) && $products->hasPages())
                <div class="mt-4">
                    {{ $products->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
