{{-- Seller application: the optional business verification section (SellerVerificationService::validateApplication). --}}
<section class="space-y-4 border-t border-gray-100 pt-6" data-apply-verification>
    <div>
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Business verification') }} <span class="text-sm font-normal text-gray-500">({{ __('optional') }})</span></h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('A registered business can send its documents now to get the "Verified business" badge next to its shop name, or later under Settings. Leave this part empty to skip it.') }}</p>
    </div>
    @include('seller.verification._fields')
    <p class="flex items-start gap-1.5 text-xs text-gray-500"><x-icon name="shield" class="w-4 h-4 shrink-0" />{{ __('Your documents are stored privately. Only the iruali staff who check shops can see them; customers never do.') }}</p>
</section>
