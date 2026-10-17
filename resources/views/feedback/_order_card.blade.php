{{-- Order page: rate a delivered order (OrderFeedbackController), or the rating already given --}}
@if($order->status === 'delivered')
    @php $feedback = $order->feedback; @endphp
    <div class="mt-6 rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm" data-order-feedback>
        @if($feedback)
            <p class="text-gray-700">{{ __('You rated this order :rating out of 5. Thank you!', ['rating' => $feedback->rating]) }}</p>
        @else
            <p class="font-medium text-gray-900">{{ __('How did it go?') }}</p>
            <a href="{{ $order->feedbackUrl() }}" class="mt-2 inline-block rounded-lg bg-primary px-4 py-2 font-semibold text-white hover:bg-primary-hover">{{ __('Rate your order') }}</a>
        @endif
        <p class="mt-2 text-xs text-gray-500">{{ __('Something wrong?') }} <a href="{{ route('complaints.create', ['order' => $order->order_number]) }}" class="text-primary hover:underline">{{ __('Make a complaint') }}</a></p>
    </div>
@endif
