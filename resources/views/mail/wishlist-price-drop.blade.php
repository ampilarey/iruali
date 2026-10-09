<x-mail::message>
# {{ $name ? __('Hello :name,', ['name' => $name]) : __('Hello,') }}

{{ trans_choice('Good news: an item on your wishlist is cheaper now.|Good news: :count items on your wishlist are cheaper now.', count($lines), ['count' => count($lines)]) }}

<table class="table" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 8px 0;">
@foreach($lines as $line)
@php $product = $line['product']; $productUrl = route('products.show', $product); $saved = round($line['was'] - $line['now'], 2); @endphp
<tr>
<td style="padding: 6px 0; width: 64px;"><a href="{{ $productUrl }}"><img src="{{ $product->mainImage?->variant(200) ?? asset('images/product-placeholder.svg') }}" alt="" width="56" height="56" style="width: 56px; height: 56px; border-radius: 8px; object-fit: cover;"></a></td>
<td style="padding: 6px 10px; font-size: 14px;"><a href="{{ $productUrl }}">{{ $product->name }}</a>@if($line['variant']) – {{ $line['variant']->displayName() }}@endif<br><span style="color: #2f855a;">{{ __('You save :amount (:percent%)', ['amount' => \App\Support\Money::format($saved), 'percent' => (int) floor($saved / max($line['was'], 0.01) * 100)]) }}</span></td>
<td style="padding: 6px 0; font-size: 14px; white-space: nowrap;" dir="ltr">{{ \App\Support\Money::format($line['now']) }}<br><s style="color: #718096;">{{ \App\Support\Money::format($line['was']) }}</s></td>
</tr>
@endforeach
</table>

{{ __('Prices can change again, so do not wait too long if you want it.') }}

<x-mail::button :url="$wishlistUrl">
{{ __('See your wishlist') }}
</x-mail::button>

{{ __('The iruali team') }}

<small style="color: #718096;">{{ __('You get this email because these items are on your wishlist on iruali.') }} <a href="{{ $settingsUrl }}">{{ __('Change your email settings') }}</a></small>
</x-mail::message>
