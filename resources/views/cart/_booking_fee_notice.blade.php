{{-- Cart: pre-orders with a booking fee are ordered on their own, from one shop (BookingFeeService::forCart()).
     Says what to change before checking out. Needs $cart. --}}
@php
    $preorderService = app(\App\Services\PreorderService::class);
    $bookingLines = $cart->items->mapWithKeys(fn ($line) => [(int) $line->id => $line->product ? $preorderService->cartLine($line) : null])->filter()->all();
    $booking = app(\App\Services\BookingFeeService::class)->forCart($cart, $bookingLines);
@endphp
@if($booking && $booking['problem'])
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert" data-booking-fee-problem>
        <p class="font-semibold flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 shrink-0" />{{ $booking['problem'] }}</p>
    </div>
@endif
