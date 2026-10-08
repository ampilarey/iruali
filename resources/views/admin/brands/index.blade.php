@extends('layouts.app')

@section('title', 'Brands')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Brands'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600 max-w-3xl">Shops add a brand by typing it on a product; names that differ only in capitals, spaces or punctuation are matched to the same brand. Check new ones here: fix the spelling, add a logo and description, and merge duplicates such as "Samsung Electronics" into "Samsung".</p>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex rounded-lg border border-gray-300 bg-white text-sm overflow-hidden" role="group" aria-label="Show">
                <a href="{{ route('admin.brands', array_filter(['q' => $q])) }}" class="px-4 py-2 {{ $show === 'all' ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-gray-50' }}" @if($show === 'all') aria-current="true" @endif>All ({{ $total }})</a>
                <a href="{{ route('admin.brands', array_filter(['show' => 'review', 'q' => $q])) }}" class="px-4 py-2 {{ $show === 'review' ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-gray-50' }}" @if($show === 'review') aria-current="true" @endif>To review ({{ $toReview }})</a>
            </div>
            <form method="GET" action="{{ route('admin.brands') }}" class="flex items-center gap-2 text-sm">
                @if($show === 'review')<input type="hidden" name="show" value="review">@endif
                <label for="brand-search" class="sr-only">Search brands</label>
                <input id="brand-search" type="search" name="q" value="{{ $q }}" placeholder="Search brands" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 font-medium text-white hover:bg-primary-700">Search</button>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start">Brand</th>
                            <th class="px-4 py-3 text-end">On sale</th>
                            <th class="px-4 py-3 text-end">All products</th>
                            <th class="px-4 py-3 text-end">{{ __('Followers') }}</th>
                            <th class="px-4 py-3 text-start">Added by</th>
                            <th class="px-4 py-3 text-start">Status</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($brands as $brand)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @include('brands._logo', ['brand' => $brand, 'class' => 'w-9 h-9 text-sm'])
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.brands.edit', $brand) }}" class="font-medium text-primary-700 hover:underline">{{ $brand->name }}</a>
                                            @if($brand->name_dv)<span class="ms-1 text-sm text-gray-600" lang="dv" dir="rtl">{{ $brand->name_dv }}</span>@endif
                                            <div class="text-xs text-gray-500">/brands/{{ $brand->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ $brand->active_products_count }}</td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ $brand->products_count }}</td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ $brand->followers_count }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $brand->creator ? ($brand->creator->business_name ?: $brand->creator->name) : 'Existing listings' }}</td>
                                <td class="px-4 py-3">
                                    @if($brand->reviewed_at)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Reviewed</span>
                                    @else
                                        <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">To review</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end whitespace-nowrap">
                                    @unless($brand->reviewed_at)
                                        <form action="{{ route('admin.brands.review', $brand) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-700 hover:underline">Looks right</button>
                                        </form>
                                    @endunless
                                    <a href="{{ route('admin.brands.edit', $brand) }}" class="ms-3 text-primary-700 hover:underline">Edit</a>
                                    @if($brand->active_products_count > 0)
                                        <a href="{{ route('brands.show', $brand) }}" class="ms-3 text-gray-600 hover:underline" target="_blank" rel="noopener">View page</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ $q !== '' ? 'No brand matches that search.' : ($show === 'review' ? 'Nothing to review.' : 'No brands yet. They appear as shops add products with a brand.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($brands->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $brands->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
