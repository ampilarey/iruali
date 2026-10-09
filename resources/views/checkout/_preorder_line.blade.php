{{-- Checkout summary: a pre-order line with its expected date. Needs $line (null for an ordinary line). --}}
@if($line)
    <p class="text-xs font-semibold text-primary-700" data-preorder-line>{{ __('Pre-order · ships around :date', ['date' => $line['date']->translatedFormat('j M')]) }}</p>
@endif
