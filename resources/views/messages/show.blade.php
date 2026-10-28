@extends('layouts.app')

@section('title', $conversation->exists ? $conversation->title() : __('Message iruali'))

{{-- One conversation for the customer: an item question, iruali support or an item request (order threads are on the order page). --}}
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">
    <a href="{{ route('messages.index') }}" class="text-sm text-primary hover:underline">← {{ __('My messages') }}</a>
    <h1 class="text-2xl font-bold text-dark">{{ $conversation->exists ? $conversation->title() : __('Message iruali') }}</h1>

    @if($conversation->kind === \App\Models\Conversation::PRODUCT && $conversation->product)
        <a href="{{ route('products.show', $conversation->product) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 hover:border-primary">
            <img src="{{ $conversation->product->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="h-14 w-14 rounded-lg object-cover">
            <span class="text-sm font-medium text-dark">{{ $conversation->product->name }}</span>
        </a>
    @elseif($conversation->kind === \App\Models\Conversation::ITEM_REQUEST && $conversation->itemRequest)
        <a href="{{ route('item-requests.show', $conversation->itemRequest) }}" class="text-sm text-primary hover:underline">{{ __('See the request') }}</a>
    @elseif(! $conversation->exists)
        <p class="text-gray-600">{{ __('Questions about your account, payments, delivery or anything else: write to us and we will answer here, usually within a working day.') }}</p>
    @endif

    @foreach(['success' => 'border-green-200 bg-green-50 text-green-800', 'error' => 'border-red-200 bg-red-50 text-red-800'] as $flash => $style)
        @if(session($flash))<div class="rounded-lg border px-4 py-3 text-sm {{ $style }}">{{ session($flash) }}</div>@endif
    @endforeach

    @include('messaging._chat', ['conversation' => $conversation, 'role' => 'customer', 'action' => $postUrl])
</div>
@endsection
