<table class="table" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 8px 0;">
@foreach($products as $product)
@php $productUrl = route('products.show', $product); @endphp
<tr>
<td style="padding: 6px 0; width: 64px;"><a href="{{ $productUrl }}"><img src="{{ $product->mainImage?->variant(200) ?? asset('images/product-placeholder.svg') }}" alt="" width="56" height="56" style="width: 56px; height: 56px; border-radius: 8px; object-fit: cover;"></a></td>
<td style="padding: 6px 10px; font-size: 14px;"><a href="{{ $productUrl }}">{{ $product->name }}</a></td>
<td style="padding: 6px 0; font-size: 14px; white-space: nowrap;" dir="ltr">{{ \App\Support\Money::format($product->final_price) }}@if($product->was_price)<br><s style="color: #718096;">{{ \App\Support\Money::format($product->was_price) }}</s>@endif</td>
</tr>
@endforeach
</table>
