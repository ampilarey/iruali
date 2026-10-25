@extends('layouts.app')

@section('title', __('My item requests'))

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-dark">{{ __('My item requests') }}</h1>
            <p class="mt-1 text-gray-600">{{ __('Items you asked us to bring for you.') }}</p>
        </div>
        <a href="{{ route('item-requests.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover" data-new-item-request>{{ __('Request an item') }}</a>
    </div>

    @if($requests->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center">
            <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary"><x-icon name="box" class="h-7 w-7" /></span>
            <p class="text-lg font-semibold">{{ __('No requests yet') }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ __('Seen something online that we don\'t sell? Send us the link and we\'ll bring it for you.') }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($requests as $itemRequest)
                <a href="{{ route('item-requests.show', $itemRequest) }}" class="flex gap-4 rounded-xl border border-gray-200 bg-white p-4 hover:border-primary" data-item-request-row="{{ $itemRequest->id }}">
                    <img src="{{ $itemRequest->product?->mainImage?->url ?? ($itemRequest->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($itemRequest->photo_path) : '/images/product-placeholder.svg') }}" alt="" class="h-16 w-16 shrink-0 rounded-lg bg-primary-50 object-cover">
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-dark">{{ $itemRequest->label() }} · {{ $itemRequest->title }}</p>
                            <span class="rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700">{{ $itemRequest->statusLabel() }}</span>
                        </div>
                        <p class="text-gray-600">{{ __('Quantity: :count', ['count' => $itemRequest->quantity]) }} · {{ $itemRequest->created_at->translatedFormat('j M Y') }}</p>
                        @if($itemRequest->unit_price !== null && in_array($itemRequest->status, ['quoted', 'accepted', 'ordered'], true))
                            <p class="mt-1 font-medium text-dark" dir="ltr">{{ \App\Support\Money::format($itemRequest->total()) }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
