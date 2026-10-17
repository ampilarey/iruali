@extends('layouts.app')

@section('title', __('Rate order :number', ['number' => $order->order_number]))

@push('styles')<meta name="robots" content="noindex, nofollow">@endpush

@section('content')
<div class="max-w-xl mx-auto px-4 py-8 space-y-5" data-feedback-page>
    <h1 class="font-display text-2xl font-bold text-dark">{{ __('Rate order :number', ['number' => $order->order_number]) }}</h1>

    @if(session('success'))<p class="rounded-xl bg-green-50 p-4 font-medium text-green-800">{{ session('success') }}</p>@endif

    @if($feedback)
        <p class="rounded-xl bg-gray-50 p-4 text-gray-700">{{ __('You rated this order :rating out of 5. Thank you!', ['rating' => $feedback->rating]) }}</p>
        @if($feedback->rating <= \App\Http\Controllers\Customer\OrderFeedbackController::LOW)
            <p class="text-sm text-gray-600">{{ __('Sorry it did not go well. Tell us what happened and we will sort it out:') }} <a href="{{ route('complaints.create', ['order' => $order->order_number]) }}" class="text-primary hover:underline">{{ __('Make a complaint') }}</a></p>
        @endif
    @elseif($order->status !== 'delivered')
        <p class="rounded-xl bg-gray-100 p-4 text-gray-700">{{ __('You can rate this order once it has been delivered.') }}</p>
    @else
        <form method="POST" action="{{ request()->fullUrl() }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-4">
            @csrf
            <fieldset>
                <legend class="font-medium text-gray-900">{{ __('How did it go?') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2" dir="ltr">
                    @for($i = 1; $i <= 5; $i++)
                        <label class="cursor-pointer">
                            <input type="radio" name="rating" value="{{ $i }}" class="peer sr-only" @checked((int) old('rating') === $i) required>
                            <span class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-300 text-lg peer-checked:border-amber-500 peer-checked:bg-amber-100 peer-focus-visible:ring-2 peer-focus-visible:ring-primary" aria-label="{{ __(':count out of 5', ['count' => $i]) }}">{{ $i }}★</span>
                        </label>
                    @endfor
                </div>
                @error('rating')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </fieldset>
            <div>
                <label for="comment" class="block text-sm text-gray-700">{{ __('Anything to add? (optional)') }}</label>
                <textarea id="comment" name="comment" rows="3" maxlength="1000" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">{{ old('comment') }}</textarea>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 font-semibold text-white hover:bg-primary-hover">{{ __('Send rating') }}</button>
        </form>
    @endif
</div>
@endsection
