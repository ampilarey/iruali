@extends('layouts.app')

@section('title', $itemRequest->label())

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6" data-item-request="{{ $itemRequest->id }}">
    <div>
        <a href="{{ route('item-requests.index') }}" class="text-sm text-primary hover:underline">← {{ __('My item requests') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-bold text-dark">{{ $itemRequest->label() }} · {{ $itemRequest->title }}</h1>
            <span class="rounded-full bg-primary-50 px-3 py-1 text-sm font-semibold text-primary-700" data-item-request-status="{{ $itemRequest->status }}">{{ $itemRequest->statusLabel() }}</span>
        </div>
    </div>

    @foreach(['success' => 'border-green-200 bg-green-50 text-green-800', 'error' => 'border-red-200 bg-red-50 text-red-800'] as $flash => $style)
        @if(session($flash))<div class="rounded-lg border px-4 py-3 text-sm {{ $style }}">{{ session($flash) }}</div>@endif
    @endforeach

    @if($itemRequest->unit_price !== null && in_array($itemRequest->status, ['quoted', 'accepted', 'ordered'], true))
        <section class="rounded-xl border border-primary-200 bg-primary-50 p-6 space-y-3" data-item-request-price>
            <h2 class="text-lg font-semibold text-dark">{{ __('Our price') }}</h2>
            <p class="text-2xl font-bold text-dark" dir="ltr">{{ \App\Support\Money::format($itemRequest->total()) }}</p>
            <p class="text-sm text-gray-700">
                {{ __(':qty × :price, delivered to your island, customs duty included.', ['qty' => $itemRequest->quantity, 'price' => \App\Support\Money::format($itemRequest->unit_price)]) }}
                {{ __('It ships about :days days after you order.', ['days' => $itemRequest->ships_in_days]) }}
            </p>
            <p class="text-sm text-gray-700">
                @if($itemRequest->deposit_percent)
                    {{ __('Pay a booking fee of :amount (:percent%) now and the rest when it arrives. If you cancel after ordering, the booking fee is not refunded.', ['amount' => \App\Support\Money::format($itemRequest->payNow()), 'percent' => $itemRequest->deposit_percent]) }}
                @else
                    {{ __('You pay the full price when you order.') }}
                @endif
            </p>
            @if($itemRequest->quote_message)
                <p class="rounded-lg bg-white p-3 text-sm text-gray-700 whitespace-pre-line">{{ $itemRequest->quote_message }}</p>
            @endif
            @if($itemRequest->status === 'quoted')
                <p class="text-xs text-gray-600">{{ __('The price holds until :date.', ['date' => $itemRequest->valid_until?->translatedFormat('j M Y')]) }}</p>
            @endif

            @if($itemRequest->canAccept())
                <form method="POST" action="{{ route('item-requests.accept', $itemRequest) }}">
                    @csrf
                    <button class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover" data-item-request-accept>{{ __('Accept and add to cart') }}</button>
                </form>
            @elseif($itemRequest->status === 'accepted' && ! $itemRequest->isExpired())
                <a href="{{ route('cart') }}" class="inline-block rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Go to your cart') }}</a>
            @elseif($itemRequest->status === 'ordered' && $itemRequest->order)
                <a href="{{ route('orders.show', $itemRequest->order) }}" class="inline-block rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover" data-item-request-order>{{ __('See your order :number', ['number' => $itemRequest->order->order_number]) }}</a>
            @endif
        </section>
    @elseif($itemRequest->status === 'new')
        <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-700">{{ __('We are looking for it. You will get our price by email or SMS, usually within 2 working days.') }}</div>
    @elseif($itemRequest->status === 'declined')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 space-y-1" data-item-request-declined>
            <p class="font-semibold">{{ __('Sorry, we can\'t bring this one.') }}</p>
            @if($itemRequest->declined_reason)<p class="whitespace-pre-line">{{ $itemRequest->declined_reason }}</p>@endif
        </div>
    @endif

    <section class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-700 space-y-2">
        <h2 class="text-lg font-semibold text-dark">{{ __('What you asked for') }}</h2>
        @if($itemRequest->photo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($itemRequest->photo_path) }}" alt="" class="h-40 w-auto rounded-lg object-cover">
        @endif
        @if($itemRequest->link)<p><a href="{{ $itemRequest->link }}" target="_blank" rel="noopener nofollow" class="text-primary hover:underline break-all" dir="ltr">{{ $itemRequest->link }}</a></p>@endif
        @if($itemRequest->options)<p>{{ __('Size, colour or model') }}: {{ $itemRequest->options }}</p>@endif
        <p>{{ __('Quantity: :count', ['count' => $itemRequest->quantity]) }}</p>
        @if($itemRequest->details)<p class="whitespace-pre-line">{{ $itemRequest->details }}</p>@endif
        @if($itemRequest->island)<p>{{ __('Deliver to') }}: {{ $itemRequest->island->localized_name }}</p>@endif
        @if($itemRequest->budget)<p>{{ __('Your budget') }}: {{ \App\Support\Money::format($itemRequest->budget) }}</p>@endif
        @if($itemRequest->needed_by)<p>{{ __('Needed by') }}: {{ $itemRequest->needed_by->translatedFormat('j M Y') }}</p>@endif
        <p class="text-xs text-gray-500">{{ __('Sent on :date', ['date' => $itemRequest->created_at->translatedFormat('j M Y')]) }}</p>
    </section>

    @if($itemRequest->canCancel())
        <form method="POST" action="{{ route('item-requests.cancel', $itemRequest) }}" onsubmit="return confirm(@js(__('Cancel this request?')))">
            @csrf
            <button class="text-sm font-medium text-gray-600 hover:text-danger hover:underline" data-item-request-cancel>{{ __('Cancel this request') }}</button>
        </form>
    @endif
</div>
@endsection
