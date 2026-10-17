@extends('layouts.admin')

@section('title', __('Review product changes'))

@php
    $product = $change->product;
    $statusLabels = ['pending' => __('Waiting for review'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'withdrawn' => __('Withdrawn or replaced')];
    $image = fn (?string $path) => $path ? \Illuminate\Support\Facades\Storage::url($path) : null;
    $variantLabel = fn (?array $row) => $row ? implode(' / ', array_map(fn ($k, $v) => $k.': '.$v, array_keys((array) $row['attributes']), (array) $row['attributes'])) : '';
    $money = fn ($value) => $value === null || $value === '' ? __('Product price') : $value;
    $rowStyles = ['added' => 'bg-green-50', 'removed' => 'bg-red-50 line-through text-gray-500', 'changed' => 'bg-amber-50', 'same' => ''];
    $rowLabels = ['added' => __('Added'), 'removed' => __('Removed'), 'changed' => __('Changed'), 'same' => __('No change')];
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => __('Review product changes'), 'back' => route('admin.product-changes')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $product?->getTranslation('name', 'en', false) ?? __('Deleted product') }}</h2>
                    <p class="text-sm text-gray-600">
                        {{ $change->seller?->shopName() }}
                        · {{ __('Sent :date', ['date' => $change->created_at->format('d M Y, H:i')]) }}
                        @if($change->submitter) · {{ __('by :name', ['name' => $change->submitter->name]) }}@endif
                        @if($product && $product->is_active) · <a href="{{ route('products.show', $product) }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">{{ __('Live product page') }} ↗</a>@endif
                    </p>
                </div>
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700" data-change-status="{{ $change->status }}">{{ $statusLabels[$change->status] ?? $change->status }}</span>
            </div>
            @if($change->status === 'rejected')
                <p class="mt-3 text-sm text-red-700">{{ __('Reason: :reason', ['reason' => $change->rejection_reason]) }}</p>
            @endif
            @if($newer)
                <p class="mt-3 text-sm text-amber-800">{{ __('The shop has sent a newer edit of this product.') }} <a href="{{ route('admin.product-changes.show', $newer) }}" class="font-medium underline">{{ __('Review the newer edit') }}</a></p>
            @endif
        </div>

        <x-admin.fold :title="__('What changes')" flush :open="true">
            <div class="border-b border-gray-100 px-4 py-2 sm:px-6">
                <p class="text-xs text-gray-500">{{ __('Live now on the left, the shop\'s edit on the right.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-2 text-start">{{ __('Field') }}</th><th class="px-4 py-2 text-start">{{ __('Live now') }}</th><th class="px-4 py-2 text-start">{{ __('After the edit') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($diff['fields'] as $field)
                            <tr data-field="{{ $field['label'] }}">
                                <th class="px-4 py-2 text-start align-top font-medium text-gray-700 whitespace-nowrap">{{ $field['label'] }}</th>
                                @foreach(['before', 'after'] as $side)
                                    <td class="px-4 py-2 align-top {{ $side === 'after' ? 'text-gray-900' : 'text-gray-500' }}" @if(in_array($field['type'], ['rtl', 'long-rtl'], true)) dir="rtl" @endif>
                                        @if($field[$side] === null)
                                            <span class="text-gray-400">—</span>
                                        @elseif($field['type'] === 'image')
                                            <img src="{{ $image($field[$side]) }}" alt="" class="h-24 w-24 rounded-lg object-cover" data-image="{{ $side }}">
                                        @elseif($field['type'] === 'link')
                                            <a href="{{ $field[$side] }}" target="_blank" rel="noopener noreferrer" class="text-primary-700 hover:underline break-all" dir="ltr">{{ $field[$side] }}</a>
                                        @else
                                            <span class="whitespace-pre-line break-words">{{ $field[$side] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">{{ __('No changes to the product\'s own fields.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.fold>

        @if($diff['variants'] !== [])
            <x-admin.fold :title="__('Variants')" flush data-variant-changes>
                <div class="border-b border-gray-100 px-4 py-2 sm:px-6">
                    <p class="text-xs text-gray-500">{{ __('Stock is not reviewed and is not shown here.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr><th class="px-4 py-2 text-start">{{ __('Change') }}</th><th class="px-4 py-2 text-start">{{ __('Variant') }}</th><th class="px-4 py-2 text-start">{{ __('SKU') }}</th><th class="px-4 py-2 text-start">{{ __('Price') }}</th><th class="px-4 py-2 text-start">{{ __('Active') }}</th><th class="px-4 py-2 text-start">{{ __('Image') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($diff['variants'] as $row)
                                @php $before = $row['before']; $after = $row['after'] ?? $row['before']; @endphp
                                <tr class="{{ $rowStyles[$row['status']] }}" data-variant-status="{{ $row['status'] }}">
                                    <td class="px-4 py-2 font-medium">{{ $rowLabels[$row['status']] }}</td>
                                    @foreach([
                                        [$variantLabel($before), $variantLabel($after)],
                                        [$before['sku'] ?? null, $after['sku']],
                                        [$before ? $money($before['price']) : null, $money($after['price'])],
                                        [$before ? ($before['is_active'] ? __('Yes') : __('No')) : null, $after['is_active'] ? __('Yes') : __('No')],
                                    ] as [$old, $new])
                                        <td class="px-4 py-2" dir="ltr">
                                            @if($row['status'] === 'changed' && $old !== $new)
                                                <span class="text-gray-500 line-through">{{ $old }}</span> → <span class="font-medium text-gray-900">{{ $new }}</span>
                                            @else
                                                {{ $new }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-2">
                                        @if($row['status'] === 'changed' && ($before['image'] ?? null) !== ($after['image'] ?? null))
                                            <span class="flex items-center gap-1">@if($before['image'])<img src="{{ $image($before['image']) }}" alt="" class="h-10 w-10 rounded object-cover opacity-60">@endif → <img src="{{ $image($after['image']) }}" alt="" class="h-10 w-10 rounded object-cover"></span>
                                        @elseif($after['image'] ?? null)
                                            <img src="{{ $image($after['image']) }}" alt="" class="h-10 w-10 rounded object-cover">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.fold>
        @endif

        @if($change->isPending())
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.product-changes.approve', $change) }}" class="rounded-lg bg-white p-5 shadow">
                    @csrf
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Approve') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('The changes go live at once and the shop is emailed.') }}</p>
                    <button class="mt-3 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">{{ __('Approve changes') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.product-changes.reject', $change) }}" class="rounded-lg bg-white p-5 shadow">
                    @csrf
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Reject') }}</h2>
                    <label for="reason" class="mt-1 block text-sm text-gray-600">{{ __('Reason (emailed to the shop)') }}</label>
                    <textarea id="reason" name="reason" rows="3" required minlength="5" maxlength="1000" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">{{ old('reason') }}</textarea>
                    <button class="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('Reject changes') }}</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
