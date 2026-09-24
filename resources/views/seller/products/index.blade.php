@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => 'My products'])
        @slot('action')
            <a href="{{ route('seller.products.create') }}" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">+ Add product</a>
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

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
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
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500">SKU {{ $product->sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $product->category->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">ރ&#x200E;{{ number_format($product->price, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm {{ $product->stock_quantity <= $product->reorder_point ? 'font-semibold text-red-600' : 'text-gray-900' }}">{{ $product->stock_quantity }}</td>
                                <td class="px-4 py-3">
                                    @if($product->is_active)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">Live</span>
                                    @else
                                        <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800">Awaiting approval</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                    <a href="{{ route('seller.products.edit', $product) }}" class="font-medium text-primary-600 hover:text-primary-700">Edit</a>
                                    <form action="{{ route('seller.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Delete this product?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ml-3 font-medium text-red-600 hover:text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No products found.</td>
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
