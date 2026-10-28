@extends('layouts.app')

{{-- One conversation in the Seller Centre: a customer's item question or the shop's thread with iruali. --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => $conversation->exists ? $conversation->title() : __('Message iruali')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <a href="{{ route('seller.messages') }}" class="text-sm text-primary-700 hover:underline">← {{ __('Messages') }}</a>
        @if($conversation->kind === 'product' && $conversation->product)
            <div class="flex items-center gap-3 rounded-lg bg-white p-3 shadow">
                <img src="{{ $conversation->product->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="h-14 w-14 rounded-lg object-cover">
                <div class="text-sm"><p class="font-medium text-gray-900">{{ $conversation->product->name }}</p><p class="text-gray-500">{{ __('Asked by :name', ['name' => \App\Services\PointsService::maskName($conversation->customer?->name)]) }}</p></div>
            </div>
        @elseif(! $conversation->exists)
            <p class="text-sm text-gray-600">{{ __('Questions for the iruali team about your shop, payouts, listings or anything else.') }}</p>
        @endif

        @foreach(['success' => 'border-green-200 bg-green-50 text-green-800', 'error' => 'border-red-200 bg-red-50 text-red-800'] as $flash => $style)
            @if(session($flash))<div class="rounded-lg border px-4 py-3 text-sm {{ $style }}">{{ session($flash) }}</div>@endif
        @endforeach

        @include('messaging._chat', ['conversation' => $conversation, 'role' => 'seller', 'action' => $postUrl])
    </div>
</div>
@endsection
