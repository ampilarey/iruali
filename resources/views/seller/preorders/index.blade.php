@extends('layouts.app')

@php
    $field = 'rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Pre-orders')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600">{{ __('Customers pay now for units coming on your next delivery. When the stock arrives, record it here: it goes to the paid pre-orders first, oldest first, and those customers are emailed. Anything left over goes on sale.') }}</p>

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @forelse($withWaiting as $row)
            @php
                $product = $row['product'];
                $items = $row['items'];
                $waitingUnits = $items->sum(fn ($item) => $item->preorderWaitingQuantity());
                $waitingByVariant = $items->groupBy(fn ($item) => (int) $item->product_variant_id)->map(fn ($lines) => $lines->sum(fn ($item) => $item->preorderWaitingQuantity()));
                // Options on sale, and any switched off while pre-orders still wait for them
                $variants = $product->has_variants ? $product->variants->filter(fn ($variant) => $variant->is_active || ($waitingByVariant[(int) $variant->id] ?? 0) > 0)->values() : collect();
                $onHand = $product->effectiveStock();
            @endphp
            <section class="overflow-hidden rounded-lg bg-white shadow" data-preorder-product="{{ $product->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900"><a href="{{ route('seller.products.edit', $product) }}" class="hover:underline">{{ $product->name }}</a></h2>
                        <p class="text-xs text-gray-500" dir="ltr">{{ $product->sku }}</p>
                        <p class="mt-1 text-sm text-gray-700">
                            {{ trans_choice(':count unit waiting|:count units waiting', $waitingUnits, ['count' => $waitingUnits]) }}
                            · {{ trans_choice(':count order|:count orders', $items->pluck('order_id')->unique()->count(), ['count' => $items->pluck('order_id')->unique()->count()]) }}
                            @if($product->preorder_ship_date) · {{ __('expected :date', ['date' => $product->preorder_ship_date->translatedFormat('j M Y')]) }}@endif
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['accepting'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ $row['accepting'] ? __('Taking pre-orders: :count left', ['count' => $row['left']]) : __('Not taking new pre-orders') }}
                    </span>
                </div>

                @if($onHand > 0)
                    <p class="mx-5 mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('You have :count in stock while pre-orders wait. Press "Stock arrived" (with 0 if nothing new came) to give them to the waiting customers first.', ['count' => $onHand]) }}</p>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ __('Order') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Placed') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Customer') }}</th>
                                @if($product->has_variants)<th class="px-4 py-2 text-start">{{ __('Option') }}</th>@endif
                                <th class="px-4 py-2 text-end">{{ __('Waiting') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Payment') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Expected') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($items as $item)
                                <tr>
                                    <td class="px-4 py-2"><a href="{{ route('seller.orders.show', $item->order) }}" class="font-medium text-primary-700 hover:underline" dir="ltr">#{{ $item->order->order_number }}</a></td>
                                    <td class="px-4 py-2 text-gray-600">{{ $item->order->created_at->translatedFormat('j M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-700">{{ $item->order->customerName() ?? __('Customer') }}</td>
                                    @if($product->has_variants)<td class="px-4 py-2 text-gray-700">{{ $item->variant_name ?? '—' }}</td>@endif
                                    <td class="px-4 py-2 text-end text-gray-900">{{ $item->preorderWaitingQuantity() }}@if($item->preorder_allocated_quantity > 0)<span class="block text-xs text-gray-500">{{ __(':done of :total in', ['done' => $item->preorder_allocated_quantity, 'total' => $item->quantity]) }}</span>@endif</td>
                                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Services\PaymentService::statusBadge($item->order->payment_status) }}">{{ \App\Services\PaymentService::statusLabel($item->order->payment_status) }}</span></td>
                                    <td class="px-4 py-2 text-gray-600">{{ $item->preorder_ship_date?->translatedFormat('j M Y') }}@if($item->preorder_late_at)<span class="ms-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">{{ __('Late') }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="px-5 pt-2 text-xs text-gray-500">{{ __('Units go to paid orders only; an unpaid order waits until its payment comes in and is cancelled after 24 hours without one.') }}</p>

                <div class="grid gap-4 border-t border-gray-100 px-5 py-4 md:grid-cols-2">
                    <form method="POST" action="{{ route('seller.preorders.arrived', $product->id) }}" class="space-y-2 rounded-lg border border-gray-200 p-3" data-stock-arrived>
                        @csrf
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('Stock arrived') }}</h3>
                        @if($product->has_variants)
                            @foreach($variants as $variant)
                                <div class="flex items-center justify-between gap-3">
                                    <label for="received-{{ $variant->id }}" class="text-sm text-gray-700">{{ $variant->displayNameWithKeys() }}
                                        <span class="block text-xs text-gray-500">{{ __(':waiting waiting, :stock in stock', ['waiting' => $waitingByVariant[(int) $variant->id] ?? 0, 'stock' => (int) $variant->stock_quantity]) }}</span>
                                    </label>
                                    <input id="received-{{ $variant->id }}" name="received[{{ $variant->id }}]" type="number" min="0" max="{{ \App\Services\PreorderService::MAX_LIMIT }}" step="1" dir="ltr" placeholder="0" class="{{ $field }} w-24 text-end">
                                </div>
                            @endforeach
                        @else
                            <div class="flex items-center justify-between gap-3">
                                <label for="received-{{ $product->id }}" class="text-sm text-gray-700">{{ __('Units received') }}</label>
                                <input id="received-{{ $product->id }}" name="received[0]" type="number" min="0" max="{{ \App\Services\PreorderService::MAX_LIMIT }}" step="1" dir="ltr" placeholder="0" class="{{ $field }} w-24 text-end">
                            </div>
                        @endif
                        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Stock arrived') }}</button>
                    </form>

                    <form method="POST" action="{{ route('seller.preorders.date', $product->id) }}" class="space-y-2 rounded-lg border border-gray-200 p-3" data-move-date>
                        @csrf
                        @method('PUT')
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('Move the expected date') }}</h3>
                        <label for="date-{{ $product->id }}" class="block text-xs text-gray-600">{{ __('Waiting customers are emailed the new date, with a link to cancel if they would rather not wait.') }}</label>
                        <input id="date-{{ $product->id }}" name="preorder_ship_date" type="date" required min="{{ today()->addDay()->toDateString() }}" dir="ltr" value="{{ $product->preorder_ship_date?->toDateString() }}" class="{{ $field }} w-full">
                        <button type="submit" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Save the new date') }}</button>
                    </form>
                </div>
            </section>
        @empty
            <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-600 shadow">
                <p class="font-medium text-gray-900">{{ __('No pre-orders are waiting.') }}</p>
                <p class="mt-1">{{ __('Switch pre-orders on for a product in its form (Pre-order), and customers can buy it while it is out of stock.') }}</p>
            </div>
        @endforelse

        @if($taking->isNotEmpty())
            <section class="overflow-hidden rounded-lg bg-white shadow">
                <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-gray-500">{{ __('Taking pre-orders, none waiting yet') }}</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach($taking as $row)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                            <a href="{{ route('seller.products.edit', $row['product']) }}" class="font-medium text-gray-900 hover:underline">{{ $row['product']->name }}</a>
                            <span class="text-gray-600">
                                @if($row['product']->preorder_ship_date){{ __('expected :date', ['date' => $row['product']->preorder_ship_date->translatedFormat('j M Y')]) }} · @endif
                                {{ $row['accepting'] ? __('Taking pre-orders: :count left', ['count' => $row['left']]) : __('Not taking new pre-orders') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
@endsection
