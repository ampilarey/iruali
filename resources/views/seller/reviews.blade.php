@extends('layouts.app')

@section('content')
@include('seller.partials.header', ['title' => __('Customer reviews')])

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
    <div class="flex gap-2 mb-4 text-sm">
        <a href="{{ route('seller.reviews') }}" class="px-3 py-1.5 rounded-full {{ request('show') !== 'all' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200' }}">{{ __('Waiting for a reply') }} ({{ $unanswered }})</a>
        <a href="{{ route('seller.reviews', ['show' => 'all']) }}" class="px-3 py-1.5 rounded-full {{ request('show') === 'all' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200' }}">{{ __('All reviews') }}</a>
    </div>

    @forelse($reviews as $review)
        <div class="bg-white rounded-lg shadow p-5 mb-3" id="review-{{ $review->id }}">
            <p class="text-xs text-gray-500">
                @if($review->product)<a href="{{ route('products.show', $review->product) }}#review-{{ $review->id }}" class="font-semibold text-primary-700 hover:underline">{{ $review->product->name }}</a> &middot;@endif
                {{ $review->user?->name ?? $review->reviewer_name }} &middot; {{ $review->created_at->diffForHumans() }}
                @if($review->verified_purchase)&middot; <span class="text-green-700 font-medium">{{ __('Verified purchase') }}</span>@endif
            </p>
            <p class="mt-1 flex items-center gap-2"><x-rating :value="$review->rating" />@if($review->title)<span class="font-semibold text-gray-900">{{ $review->title }}</span>@endif</p>
            <p class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $review->comment }}</p>
            @if($review->photos->isNotEmpty())
                <div class="mt-2 flex gap-2">
                    @foreach($review->photos as $photo)
                        <a href="{{ $photo->variant(1200) }}" target="_blank" rel="noopener"><img src="{{ $photo->variant(400) }}" alt="" class="w-16 h-16 object-cover rounded-lg border border-gray-200"></a>
                    @endforeach
                </div>
            @endif
            <form action="{{ route('seller.reviews.reply', $review) }}" method="POST" class="mt-3">
                @csrf
                <label for="reply-{{ $review->id }}" class="block text-sm font-medium text-gray-700">{{ $review->hasReply() ? __('Your reply') : __('Reply as your shop') }}</label>
                <textarea id="reply-{{ $review->id }}" name="seller_reply" rows="2" required minlength="2" maxlength="1000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="{{ __('Thank the customer or explain what happened. Everyone can see this reply.') }}">{{ old('seller_reply', $review->seller_reply) }}</textarea>
                @error('seller_reply')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="mt-2 flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700">{{ $review->hasReply() ? __('Update reply') : __('Publish reply') }}</button>
                    @if($review->seller_replied_at)<span class="text-xs text-gray-500">{{ __('Replied :when', ['when' => $review->seller_replied_at->diffForHumans()]) }}</span>@endif
                </div>
            </form>
        </div>
    @empty
        <div class="bg-white rounded-lg shadow p-10 text-center text-gray-500">{{ __('No reviews here.') }}</div>
    @endforelse

    <div class="mt-4">{{ $reviews->links() }}</div>
</div>
@endsection
