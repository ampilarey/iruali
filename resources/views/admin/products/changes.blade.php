@extends('layouts.admin')

@section('title', __('Product changes waiting'))

@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => __('Product changes waiting'), 'back' => route('admin.products')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600 max-w-3xl">{{ __('Edits shops made to products that are already live. Shoppers keep seeing the approved version until you approve an edit. Stock changes are not listed: they go live at once. Oldest first.') }}</p>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ __('Product') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Shop') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Sent') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($changes as $change)
                            <tr data-change="{{ $change->id }}">
                                <td class="px-4 py-2">
                                    @php $liveName = $change->product?->getTranslation('name', 'en', false); $newName = $change->payload['product']['name']['en'] ?? null; @endphp
                                    <span class="font-medium text-gray-900">{{ $liveName }}</span>
                                    @if($newName && $newName !== $liveName)<span class="text-gray-600"> → {{ $newName }}</span>@endif
                                    <span class="block text-xs text-gray-500" dir="ltr">SKU {{ $change->product?->sku }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ $change->seller?->shopName() }}</td>
                                <td class="px-4 py-2 text-gray-600">
                                    {{ $change->created_at->format('d M Y, H:i') }}
                                    @if($change->submitter && $change->submitter->id !== $change->seller_id)<span class="block text-xs text-gray-500">{{ __('by :name (shop staff)', ['name' => $change->submitter->name]) }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-end"><a href="{{ route('admin.product-changes.show', $change) }}" class="font-medium text-primary-700 hover:underline">{{ __('Review') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('No product changes waiting.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $changes->links() }}
    </div>
</div>
@endsection
