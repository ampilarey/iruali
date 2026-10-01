@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.moderation._header', ['title' => 'Reviews'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
            @foreach(['' => ['All', $counts['all']], 'low' => ['1–2 stars', $counts['low']], 'hidden' => ['Hidden', $counts['hidden']]] as $key => [$label, $count])
                <a href="{{ route('admin.reviews', array_filter(['show' => $key, 'q' => request('q')])) }}" class="px-3 py-1.5 rounded-full {{ (string) request('show') === $key ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200 text-gray-700' }}">{{ $label }} ({{ $count }})</a>
            @endforeach
            <form method="GET" class="ms-auto flex gap-2">
                @if(request('show'))<input type="hidden" name="show" value="{{ request('show') }}">@endif
                <label for="review-search" class="sr-only">Search reviews</label>
                <input id="review-search" name="q" value="{{ request('q') }}" placeholder="Search text or name" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                <button class="px-3 py-1.5 rounded-lg bg-gray-700 text-white">Search</button>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg divide-y divide-gray-200">
            @forelse($reviews as $review)
                <div class="p-4 flex flex-col sm:flex-row gap-3 {{ $review->is_approved ? '' : 'bg-gray-50' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-500">
                            @if($review->product)<a href="{{ route('products.show', $review->product) }}#review-{{ $review->id }}" class="font-semibold text-primary-700 hover:underline">{{ $review->product->name }}</a> &middot;@endif
                            {{ $review->user?->name ?? $review->reviewer_name }} ({{ $review->reviewer_email }}) &middot; {{ $review->created_at?->diffForHumans() }}
                            @if($review->verified_purchase)&middot; <span class="text-green-700 font-medium">Verified purchase</span>@endif
                            @unless($review->is_approved)&middot; <span class="text-red-700 font-semibold">Hidden</span>@endunless
                        </p>
                        <p class="mt-1 text-sm"><span class="text-amber-500" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', $review->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            @if($review->title)<span class="font-semibold ms-1">{{ $review->title }}</span>@endif</p>
                        <p class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $review->comment }}</p>
                        @if($review->photos->isNotEmpty())
                            <div class="mt-2 flex gap-2">
                                @foreach($review->photos as $photo)
                                    <a href="{{ $photo->variant(1200) }}" target="_blank" rel="noopener"><img src="{{ $photo->variant(400) }}" alt="" class="w-14 h-14 object-cover rounded border border-gray-200"></a>
                                @endforeach
                            </div>
                        @endif
                        @if($review->hasReply())
                            <div class="mt-2 rounded-lg bg-primary-50 px-3 py-2 text-sm">
                                <p class="text-xs text-gray-500">Reply from <span class="font-semibold text-gray-800">{{ $review->replyShopName() }}</span> &middot; {{ $review->seller_replied_at?->diffForHumans() }}</p>
                                <p class="mt-1 text-gray-700 whitespace-pre-line">{{ $review->seller_reply }}</p>
                                <form method="POST" action="{{ route('admin.reviews.reply.destroy', $review) }}" class="mt-2" onsubmit="return confirm('Remove the shop\'s reply?')">
                                    @csrf @method('DELETE')
                                    <button class="px-3 py-1 rounded-lg text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100">Remove reply</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <div class="flex sm:flex-col gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.reviews.toggle', $review) }}">
                            @csrf
                            <button class="w-24 px-3 py-1.5 rounded-lg text-sm font-medium {{ $review->is_approved ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}">{{ $review->is_approved ? 'Hide' : 'Show' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Delete this review for good?')">
                            @csrf @method('DELETE')
                            <button class="w-24 px-3 py-1.5 rounded-lg text-sm font-medium bg-red-50 text-red-700 hover:bg-red-100">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="p-10 text-center text-gray-500">No reviews here.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $reviews->links() }}</div>
    </div>
</div>
@endsection
