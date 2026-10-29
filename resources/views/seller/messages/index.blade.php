@extends('layouts.app')

@php
    $kinds = ['product' => __('Item questions'), 'order' => __('Orders'), 'shop_support' => __('iruali support')];
@endphp

{{-- Seller Centre → Messages: the shop's conversations (MessagingService), unread first. --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Messages')])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('seller.messages') }}" class="rounded-full px-3 py-1.5 font-medium {{ $kind === null ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow' }}">{{ __('All') }}</a>
                @foreach($kinds as $key => $label)
                    <a href="{{ route('seller.messages', ['kind' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $kind === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <a href="{{ route('seller.messages.support') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover" data-seller-message-iruali>{{ __('Message iruali') }}</a>
        </div>
        <p class="text-sm text-gray-600">{{ __('Customers see your replies as iruali. Phone numbers, emails and links are hidden in item questions, and the iruali team can read every conversation.') }}</p>

        <div class="space-y-2">
            @forelse($conversations as $conversation)
                <a href="{{ $conversation->urlFor('seller') }}" class="flex items-center gap-3 rounded-lg bg-white p-4 shadow hover:bg-gray-50 {{ $conversation->seller_unread_count ? 'font-semibold' : '' }}" data-seller-conversation="{{ $conversation->id }}">
                    <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $kinds[$conversation->kind] ?? $conversation->kind }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-gray-900">{{ $conversation->title() }}</p>
                        <p class="text-xs text-gray-500">@if($conversation->customer_id || $conversation->guest_name){{ $conversation->kind === 'product' ? \App\Services\PointsService::maskName($conversation->customerName()) : $conversation->customerName() }} · @endif{{ $conversation->last_message_at?->translatedFormat('j M Y, H:i') }}</p>
                    </div>
                    @if($conversation->seller_unread_count)<span class="rounded-full bg-primary px-2 py-0.5 text-xs font-bold text-white">{{ $conversation->seller_unread_count }}</span>@endif
                </a>
            @empty
                <div class="rounded-lg bg-white p-10 text-center text-sm text-gray-500 shadow">{{ __('No messages yet.') }}</div>
            @endforelse
        </div>
        {{ $conversations->links() }}
    </div>
</div>
@endsection
