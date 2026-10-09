@extends('layouts.app')

@section('title', __('Quote requests'))

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Quote requests')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">{{ __('Businesses asking you for a price on a quantity. Reply with a unit price (it may be under your listed price), the quantity you can supply and how long the price holds, or decline with a reason. Once the customer accepts, the quote is in their cart at your price; you get the usual new-order email when they check out. Commission is on the quoted price.') }}</p>

        <nav class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('Show') }}">
            @foreach(array_keys(\App\Http\Controllers\Seller\QuoteController::TABS) as $tab)
                @php $n = $tab === 'all' ? $counts->sum() : (int) ($counts[$tab] ?? 0); @endphp
                <a href="{{ route('seller.quotes', ['status' => $tab]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $status === $tab ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}" @if($status === $tab) aria-current="true" @endif>{{ \App\Http\Controllers\Seller\QuoteController::tabLabel($tab) }} ({{ $n }})</a>
            @endforeach
        </nav>

        @if($requests->isEmpty())
            <div class="rounded-lg bg-white p-8 text-center text-gray-500 shadow">{{ __('No quote requests here.') }}</div>
        @else
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ __('Request') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Product') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Business') }}</th>
                                <th class="px-4 py-2 text-end">{{ __('Quantity') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Deliver to') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Your quote') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($requests as $quote)
                                <tr data-quote-row="{{ $quote->id }}">
                                    <td class="px-4 py-3 align-top whitespace-nowrap">
                                        <a href="{{ route('seller.quotes.show', $quote) }}" class="font-semibold text-primary-700 hover:underline">{{ $quote->label() }}</a>
                                        <span class="block text-xs text-gray-500">{{ $quote->created_at->translatedFormat('j M Y') }}</span>
                                        @if($quote->seller_unread > 0)<span class="mt-0.5 inline-block rounded-full bg-primary px-1.5 text-[11px] font-bold text-white">{{ trans_choice(':count new message|:count new messages', $quote->seller_unread, ['count' => $quote->seller_unread]) }}</span>@endif
                                    </td>
                                    <td class="px-4 py-3 align-top">{{ $quote->displayName() }}</td>
                                    <td class="px-4 py-3 align-top">{{ $quote->business_name }}</td>
                                    <td class="px-4 py-3 align-top text-end" dir="ltr">{{ $quote->quantity }}</td>
                                    <td class="px-4 py-3 align-top">
                                        {{ $quote->deliveryPlace() }}
                                        @if($quote->needed_by)<span class="block text-xs text-gray-500">{{ __('Needed by: :date', ['date' => $quote->needed_by->translatedFormat('j M Y')]) }}</span>@endif
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        @if($quote->unit_price !== null && $quote->quoted_at)
                                            <span class="block" dir="ltr">{{ \App\Services\QuoteService::priceLine($quote) }}</span>
                                            <span class="block text-xs text-gray-500">{{ __('Until :date', ['date' => $quote->valid_until?->translatedFormat('j M Y')]) }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold {{ $quote->statusBadge() }}">{{ $quote->statusLabel() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $requests->links() }}</div>
            </div>
        @endif
    </div>
</div>
@endsection
