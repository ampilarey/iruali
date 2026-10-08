@extends('layouts.app')

@php $pct = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => $campaign->name])
        @slot('action')
            <a href="{{ route('seller.campaigns') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">{{ __('All campaigns') }}</a>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="rounded-lg bg-white p-5 shadow text-sm text-gray-700 space-y-1">
            <p><span class="font-medium">{{ __('Runs') }}:</span> {{ $campaign->starts_at->translatedFormat('j M Y, H:i') }} – {{ $campaign->ends_at->translatedFormat('j M Y, H:i') }}</p>
            <p><span class="font-medium">{{ __('Minimum discount') }}:</span> {{ $campaign->minimumDiscount() > 0 ? $pct($campaign->minimumDiscount()).'%' : __('None') }}</p>
            @if($campaign->brand)<p class="font-medium text-primary-700">{{ __('Only :brand products can join this campaign.', ['brand' => $campaign->brand->localizedName()]) }}</p>@endif
            <p class="text-gray-500">{{ __('The discount comes off the product\'s current price while the campaign runs. Approved products show the campaign price on the site and at checkout.') }}</p>
        </div>

        @if($participations->isNotEmpty())
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <h2 class="px-5 py-3 font-semibold text-gray-900 border-b border-gray-200">{{ __('Your products in this campaign') }}</h2>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ __('Product') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Discount') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Campaign price') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Status') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($participations as $row)
                            @php $product = $products->firstWhere('id', $row->product_id) ?? $row->product; $base = (float) ($product->sale_price ?? $product->price); @endphp
                            <tr>
                                <td class="px-4 py-2 text-gray-900">{{ $product->name }}@unless($campaign->acceptsProduct($product))<span class="block text-xs text-red-700">{{ __('No longer a :brand product, so it gets no campaign price.', ['brand' => $campaign->brand?->localizedName()]) }}</span>@endunless</td>
                                <td class="px-4 py-2 text-end" dir="ltr">{{ $pct($campaign->discountFor($row)) }}%</td>
                                <td class="px-4 py-2 text-end" dir="ltr">{{ \App\Support\Money::format(round($base * (1 - $campaign->discountFor($row) / 100), 2)) }}</td>
                                <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $row->isApproved() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $row->isApproved() ? __('Approved') : __('Awaiting approval') }}</span></td>
                                <td class="px-4 py-2 text-end">
                                    <form method="POST" action="{{ route('seller.campaigns.leave', [$campaign, $row]) }}" onsubmit="return confirm('{{ __('Take this product out of the campaign?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('seller.campaigns.store', $campaign) }}" class="rounded-lg bg-white p-5 shadow space-y-4">
            @csrf
            <h2 class="font-semibold text-gray-900">{{ __('Add products') }}</h2>
            @if($products->isEmpty())
                <p class="text-sm text-gray-500">{{ $campaign->brand ? __('You have no active :brand products to add yet.', ['brand' => $campaign->brand->localizedName()]) : __('You have no active products to add yet.') }}</p>
            @else
                <div class="max-w-xs">
                    <label for="discount_percent" class="block text-sm font-medium text-gray-700">{{ __('Discount (%)') }}</label>
                    <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="{{ $campaign->minimumDiscount() }}" max="90" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" value="{{ old('discount_percent', $pct(max($campaign->minimumDiscount(), 10))) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Applies to every product you tick below.') }}</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($products as $product)
                        <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                            <input type="checkbox" name="products[]" value="{{ $product->id }}" @checked(in_array($product->id, old('products', []))) class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="min-w-0">
                                <span class="block truncate text-gray-900">{{ $product->name }}</span>
                                <span class="block text-xs text-gray-500" dir="ltr">{{ \App\Support\Money::format($product->sale_price ?? $product->price) }}@if($participations->has($product->id)) · {{ __('already in') }}@endif</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Submit for approval') }}</button>
            @endif
        </form>
    </div>
</div>
@endsection
