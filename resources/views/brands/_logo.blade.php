{{-- A brand's logo, or its first letter on a tinted tile when it has none. The name is always
     printed next to it, so the image itself is decorative (empty alt). Pass the size in $class. --}}
@if($brand->logoUrl())
    <span class="{{ $class ?? 'w-12 h-12' }} shrink-0 rounded-xl bg-white border border-gray-200 p-1.5 flex items-center justify-center overflow-hidden">
        <img src="{{ $brand->logoUrl() }}" alt="" class="max-w-full max-h-full object-contain" loading="lazy">
    </span>
@else
    <span class="{{ $class ?? 'w-12 h-12' }} shrink-0 rounded-xl bg-primary-50 text-primary font-display font-bold flex items-center justify-center" aria-hidden="true">{{ $brand->initial() }}</span>
@endif
