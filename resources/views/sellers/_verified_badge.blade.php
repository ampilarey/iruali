{{-- "Verified business" next to a shop's name: iruali has checked the shop's business registration (Admin → Verifications).
     $seller: the shop. $style: 'block' (product page seller block), 'inline' (shop page heading) or 'chip' (brand page "Sold by" strip). --}}
@if($seller && $seller->hasVerifiedBusiness())
    @php
        $style = $style ?? 'block';
        $verifiedExplained = __('iruali has checked this shop\'s business registration.');
    @endphp
    @if($style === 'chip')
        <span class="inline-flex items-center gap-0.5 text-xs font-semibold text-primary" title="{{ $verifiedExplained }}" data-verified-business><x-icon name="shield" class="w-3.5 h-3.5" />{{ __('Verified business') }}</span>
    @elseif($style === 'inline')
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5" data-verified-business>
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary"><x-icon name="shield" class="w-4 h-4" />{{ __('Verified business') }}</span>
            <span class="text-xs text-gray-500">{{ $verifiedExplained }}</span>
        </div>
    @else
        <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary" data-verified-business>
            <x-icon name="shield" class="w-4 h-4 shrink-0" />{{ __('Verified business') }}
        </p>
        <p class="mt-0.5 text-xs text-gray-500">{{ $verifiedExplained }}</p>
    @endif
@endif
