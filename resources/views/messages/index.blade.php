@extends('layouts.app')

@section('title', __('My messages'))

{{-- Every conversation the customer is in (MessagingService): orders, item questions, iruali support, item requests. --}}
@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-dark">{{ __('My messages') }}</h1>
            <p class="mt-1 text-gray-600">{{ __('Your conversations with iruali about orders, items and requests.') }}</p>
        </div>
        <a href="{{ route('messages.support') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover" data-message-iruali>{{ __('Message iruali') }}</a>
    </div>

    @if($conversations->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center">
            <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary"><x-icon name="chat" class="h-7 w-7" /></span>
            <p class="text-lg font-semibold">{{ __('No messages yet') }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ __('Ask about an item from its page, or message iruali with any question.') }}</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($conversations as $conversation)
                <a href="{{ $conversation->urlFor('customer') }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 hover:border-primary {{ $conversation->customer_unread_count ? 'font-semibold' : '' }}" data-conversation-row="{{ $conversation->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-dark">{{ $conversation->title() }}</p>
                        <p class="text-xs text-gray-500">{{ $conversation->last_message_at?->translatedFormat('j M Y, H:i') }}@unless($conversation->isOpen()) · {{ __('Closed') }}@endunless</p>
                    </div>
                    @if($conversation->customer_unread_count)
                        <span class="rounded-full bg-primary px-2 py-0.5 text-xs font-bold text-white">{{ $conversation->customer_unread_count }}</span>
                    @endif
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $conversations->links() }}</div>
    @endif
</div>
@endsection
