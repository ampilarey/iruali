<x-mail::message>
# {{ __('Hello :name,', ['name' => $name]) }}

@if($stage === 1)
{{ __('You left these in your cart. They are still here whenever you are ready.') }}
@else
{{ __('Your cart is still waiting. Stock can run out, so finish your order while everything is available.') }}
@endif

<table class="table" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 16px 0;">
@foreach($items as $item)
<tr>
<td style="padding: 6px 0; width: 64px;"><img src="{{ $item->product->mainImage?->variant(200) ?? asset('images/product-placeholder.svg') }}" alt="" width="56" height="56" style="width: 56px; height: 56px; border-radius: 8px; object-fit: cover;"></td>
<td style="padding: 6px 10px; font-size: 14px;">{{ $item->product->name }}@if($item->variant) – {{ $item->variant->displayName() }}@endif<br><span style="color: #718096;">{{ __('Qty') }}: {{ $item->quantity }}</span></td>
<td style="padding: 6px 0; font-size: 14px; white-space: nowrap;" dir="ltr">{{ \App\Support\Money::format($item->quantity * $item->unit_price) }}</td>
</tr>
@endforeach
</table>

@if($voucher)
{{ __('As a thank you, here is :percent% off this order with the code below. It is just for you, works once, and expires on :date.', ['percent' => $percent, 'date' => $voucher->valid_until->translatedFormat('j F Y')]) }}

<x-mail::panel>
<span dir="ltr" style="font-family: monospace; font-size: 20px; letter-spacing: 2px;">{{ $voucher->code }}</span>
</x-mail::panel>
@endif

<x-mail::button :url="$cartUrl">
{{ __('Back to my cart') }}
</x-mail::button>

{{ __('The iruali team') }}

<small style="color: #718096;">{{ __('Don\'t want reminders like this?') }} <a href="{{ $unsubscribeUrl }}">{{ __('Unsubscribe from marketing emails') }}</a></small>
</x-mail::message>
