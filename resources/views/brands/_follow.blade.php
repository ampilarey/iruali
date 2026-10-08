{{-- Follow / Following on a brand page. Followers get one email a day when the brand puts products
     on sale. A guest pressing Follow signs in first and comes back to this page. --}}
@php $brandName = $brand->localizedName(); @endphp
@if($brandFollowing ?? false)
    <form method="POST" action="{{ route('brands.unfollow', $brand) }}" class="mt-3">
        @csrf
        @method('DELETE')
        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-primary bg-white px-4 py-2 text-sm font-semibold text-primary hover:bg-primary-50" title="{{ __('Unfollow') }}">
            <x-icon name="check" class="w-4 h-4" />{{ __('Following') }} <span class="sr-only">{{ __('Press to unfollow :brand.', ['brand' => $brandName]) }}</span>
        </button>
    </form>
@else
    <form method="POST" action="{{ route('brands.follow', $brand) }}" class="mt-3">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">
            <x-icon name="plus" class="w-4 h-4" />{{ __('Follow') }} <span class="sr-only">{{ $brandName }}</span>
        </button>
    </form>
@endif
