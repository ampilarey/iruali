{{-- "Authorised seller" next to a shop's name: iruali has confirmed the shop as an authorised seller of $brand (Admin → Brands). --}}
<p class="mt-1 inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-semibold text-success" data-authorised-seller>
    <x-icon name="badge" class="w-4 h-4 shrink-0" />{{ __('Authorised seller') }}
</p>
<p class="mt-0.5 text-xs text-gray-500">{{ __('iruali has confirmed this shop is an authorised seller of :brand.', ['brand' => $brand->localizedName()]) }}</p>
