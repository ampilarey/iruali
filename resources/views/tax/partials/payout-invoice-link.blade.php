{{-- iruali's commission invoice for a paid payout. Pass the route: seller.payouts.invoice or admin.payouts.invoice. --}}
@if($payout->isPaid())
    <a href="{{ route($route, $payout) }}" target="_blank" rel="noopener" class="{{ $class ?? 'text-xs font-medium text-primary-700 hover:underline' }}" data-commission-invoice-link>{{ __('Commission invoice') }}@if($payout->invoice_number) <span dir="ltr">{{ $payout->invoice_number }}</span>@endif</a>
@endif
