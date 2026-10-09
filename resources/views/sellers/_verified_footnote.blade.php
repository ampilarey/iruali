{{-- Under the brand page's "Sold by" strip: what the "Verified business" mark on a shop means ($shops: the shops in the strip). --}}
@if($shops->contains(fn ($shop) => $shop->hasVerifiedBusiness()))
    <p class="mt-2 flex items-start gap-1.5 text-xs text-gray-500" data-verified-business-note><x-icon name="shield" class="w-4 h-4 shrink-0 text-primary" />{{ __('Verified business: iruali has checked this shop\'s business registration.') }}</p>
@endif
