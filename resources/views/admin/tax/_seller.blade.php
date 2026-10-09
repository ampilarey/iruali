{{-- Admin → Sellers: the shop's GST details as it entered them (Seller Centre → Settings → Tax) --}}
@php $shopTax = \App\Models\ShopTaxProfile::forUser($seller->id); @endphp
@if($shopTax?->gst_registered)
    <div class="mt-1 text-xs" data-seller-tax>
        <span class="rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800">{{ __('GST-registered') }}</span>
        <span class="font-mono text-gray-700" dir="ltr">{{ $shopTax->tin }}</span>
        @if($shopTax->registered_name || $shopTax->business_address)<span class="block text-gray-500">{{ collect([$shopTax->registered_name, $shopTax->business_address])->filter()->join(' · ') }}</span>@endif
    </div>
@elseif($shopTax)
    <div class="mt-1 text-xs text-gray-500" data-seller-tax>{{ __('Not GST-registered') }}</div>
@endif
