<x-mail::message>
# {{ $name ? __('Hello :name,', ['name' => $name]) : __('Hello,') }}

{{ __('Here is what is new from the brands you follow: products that went on sale or joined a sale on iruali.') }}

@foreach($listed as $group)
## {{ $group['brand']->localizedName() }}

<table class="table" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 8px 0;">
@foreach($group['lines'] as $line)
@php $product = $line['product']; $productUrl = route('products.show', $product); @endphp
<tr>
<td style="padding: 6px 0; width: 64px;"><a href="{{ $productUrl }}"><img src="{{ $product->mainImage?->variant(200) ?? asset('images/product-placeholder.svg') }}" alt="" width="56" height="56" style="width: 56px; height: 56px; border-radius: 8px; object-fit: cover;"></a></td>
<td style="padding: 6px 10px; font-size: 14px;"><a href="{{ $productUrl }}">{{ $product->name }}</a><br><span style="color: #718096;">{{ $line['campaign'] ? __('Part of :campaign', ['campaign' => $line['campaign']->headline]) : __('Now on sale') }}</span></td>
<td style="padding: 6px 0; font-size: 14px; white-space: nowrap;" dir="ltr">{{ \App\Support\Money::format($product->final_price) }}@if($product->was_price)<br><s style="color: #718096;">{{ \App\Support\Money::format($product->was_price) }}</s>@endif</td>
</tr>
@endforeach
</table>

@if($group['total'] > count($group['lines']))
{{ trans_choice('And :count more new product.|And :count more new products.', $group['total'] - count($group['lines']), ['count' => $group['total'] - count($group['lines'])]) }}
@endif
[{{ __('See everything from :brand', ['brand' => $group['brand']->localizedName()]) }}]({{ route('brands.show', $group['brand']) }})

@endforeach
@if($others !== [])
{{ __('Also new from:') }} @foreach($others as $group)[{{ $group['brand']->localizedName() }}]({{ route('brands.show', $group['brand']) }}){{ $loop->last ? '' : ', ' }}@endforeach


@endif
<x-mail::button :url="$followingUrl">
{{ __('Brands you follow') }}
</x-mail::button>

{{ __('The iruali team') }}

<small style="color: #718096;">{{ __('You get this email because you follow these brands on iruali.') }} <a href="{{ $settingsUrl }}">{{ __('Change your email settings') }}</a></small>
</x-mail::message>
