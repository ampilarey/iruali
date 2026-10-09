@extends('layouts.app')

@section('title', __('Quotes'))

@php
    $filters = [
        'waiting' => __('Waiting for a shop (:days+ days)', ['days' => \App\Services\QuoteService::WAITING_DAYS]),
        'new' => \App\Enums\QuoteStatus::New->label(),
        'quoted' => \App\Enums\QuoteStatus::Quoted->label(),
        'accepted' => \App\Enums\QuoteStatus::Accepted->label(),
        'ordered' => \App\Enums\QuoteStatus::Ordered->label(),
        'declined' => \App\Enums\QuoteStatus::Declined->label(),
        'expired' => \App\Enums\QuoteStatus::Expired->label(),
        'all' => __('All'),
    ];
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('Quotes')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="max-w-3xl text-sm text-gray-600">{{ __('Bulk quote requests from businesses to shops. Follow up shops that have not answered (they get an email each time someone writes in a request), write in a request as iruali support, or close a request with a reason both sides see. Closing and writing are in the audit log.') }}</p>

        <nav class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('Show') }}">
            @foreach($filters as $key => $label)
                <a href="{{ route('admin.quotes', ['status' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $status === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}" @if($status === $key) aria-current="true" @endif>{{ $label }} ({{ (int) ($counts[$key] ?? 0) }})</a>
            @endforeach
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Request') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Business') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Shop') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Product') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Quantity') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Quote') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($requests as $quote)
                            <tr class="hover:bg-gray-50" data-quote-row="{{ $quote->id }}">
                                <td class="px-4 py-3 whitespace-nowrap"><a href="{{ route('admin.quotes.show', $quote) }}" class="font-medium text-primary-700 hover:underline">{{ $quote->label() }}</a><span class="block text-xs text-gray-500">{{ $quote->created_at->format('d M Y, H:i') }}</span></td>
                                <td class="px-4 py-3">{{ $quote->business_name }}<span class="block text-xs text-gray-500">{{ $quote->customer?->name }}</span></td>
                                <td class="px-4 py-3">{{ $quote->shopName() }}</td>
                                <td class="px-4 py-3">{{ $quote->displayName() }}</td>
                                <td class="px-4 py-3 text-end" dir="ltr">{{ $quote->quantity }}</td>
                                <td class="px-4 py-3" dir="ltr">@if($quote->unit_price !== null && $quote->quoted_at){{ \App\Services\QuoteService::priceLine($quote) }}@else — @endif</td>
                                <td class="px-4 py-3"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold {{ $quote->statusBadge() }}">{{ $quote->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">{{ __('No quote requests here.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $requests->links() }}</div>
        </div>
    </div>
</div>
@endsection
