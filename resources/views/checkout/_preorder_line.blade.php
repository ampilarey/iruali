{{-- Checkout summary: a pre-order line with its expected date and any booking fee. Needs $line (null for an ordinary line). --}}
@if($line)
    <p class="text-xs font-semibold text-primary-700" data-preorder-line>{{ __('Pre-order · ships around :date', ['date' => $line['date']->translatedFormat('j M')]) }}</p>
    @if(($line['fee'] ?? null) !== null && ($bookingFee ?? null))
        <p class="text-xs text-gray-700" data-booking-fee-line>{{ __('Booking fee now: :fee, the rest when it arrives', ['fee' => \App\Support\Money::format($line['fee'])]) }}</p>
    @endif
@endif
