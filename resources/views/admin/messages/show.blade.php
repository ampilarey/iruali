@extends('layouts.admin')

{{-- One conversation, for iruali's team: everything as written, reply as iruali support, close, block. --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => $conversation->title(), 'back' => route('admin.messages')])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <section class="rounded-lg bg-white p-4 shadow text-sm text-gray-700 space-y-1" data-admin-conversation-details>
            <p>Kind: <strong>{{ $conversation->kind }}</strong> · Status: <strong>{{ $conversation->status }}</strong> · Started {{ $conversation->created_at->format('j M Y H:i') }}</p>
            @if($conversation->order)<p>Order: <a href="{{ route('admin.orders.show', $conversation->order) }}" class="text-primary-700 hover:underline">{{ $conversation->order->order_number }}</a></p>@endif
            @if($conversation->product)<p>Item: {{ $conversation->product->getTranslation('name', 'en', false) }} (#{{ $conversation->product->id }})</p>@endif
            @if($conversation->itemRequest)<p>Item request: <a href="{{ route('admin.item-requests.show', $conversation->itemRequest) }}" class="text-primary-700 hover:underline">#{{ $conversation->itemRequest->id }} {{ $conversation->itemRequest->title }}</a></p>@endif
            @if($conversation->isGuest())
                <p data-admin-guest>Visitor (no account): <strong>{{ $conversation->guest_name }}</strong>@if($conversation->guest_email) · {{ $conversation->guest_email }}@endif @if($conversation->guest_phone) · {{ $conversation->guest_phone }}@endif — replies reach them by {{ collect([$conversation->guest_email ? 'email' : null, $conversation->guest_phone ? 'SMS' : null])->filter()->implode(' and ') }}, with their private link.</p>
            @endif
            @foreach(['customer' => 'Customer', 'seller' => 'Shop'] as $relation => $label)
                @if($person = $conversation->{$relation})
                    <div class="flex flex-wrap items-center gap-2">
                        <span>{{ $label }}: <strong>{{ $relation === 'seller' ? $conversation->shopName() : $person->name }}</strong> · {{ $person->email }} · {{ $person->phone }}</span>
                        @if($person->messaging_blocked_at)<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Blocked from messages since {{ $person->messaging_blocked_at->format('j M Y') }}</span>@endif
                        <form method="POST" action="{{ route('admin.messages.block', $person) }}" onsubmit="return confirm('{{ $person->messaging_blocked_at ? 'Let this account send messages again?' : 'Stop this account sending messages? What it wrote stays.' }}')">
                            @csrf
                            <input type="hidden" name="blocked" value="{{ $person->messaging_blocked_at ? 0 : 1 }}">
                            <button class="text-xs font-medium {{ $person->messaging_blocked_at ? 'text-primary-700' : 'text-red-700' }} hover:underline" data-admin-block="{{ $person->id }}">{{ $person->messaging_blocked_at ? 'Unblock' : 'Block from messages' }}</button>
                        </form>
                    </div>
                @endif
            @endforeach
            <form method="POST" action="{{ route('admin.messages.status', $conversation) }}" class="pt-2">
                @csrf
                <input type="hidden" name="status" value="{{ $conversation->isOpen() ? 'closed' : 'open' }}">
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ $conversation->isOpen() ? 'Close conversation' : 'Reopen conversation' }}</button>
            </form>
        </section>

        @include('messaging._chat', ['conversation' => $conversation, 'role' => 'admin', 'action' => route('admin.messages.reply', $conversation)])
    </div>
</div>
@endsection
