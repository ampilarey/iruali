{{-- A brand as a tile (home page, the directory's popular row, My Account → Brands you follow) --}}
<a href="{{ route('brands.show', $brand) }}" class="group bg-white border border-gray-200 rounded-xl p-3 flex flex-col items-center text-center gap-2 hover:border-primary hover:shadow-sm">
    @include('brands._logo', ['brand' => $brand, 'class' => 'w-12 h-12 lg:w-14 lg:h-14 text-lg'])
    <span class="w-full text-xs lg:text-sm font-semibold text-dark truncate group-hover:text-primary">{{ $brand->localizedName() }}</span>
    {{-- In Dhivehi, a brand with a Dhivehi name shows its own (English) name small underneath --}}
    @if($brand->localizedName() !== $brand->name)<span class="-mt-1.5 w-full text-[11px] text-gray-500 truncate" lang="en"><bdi>{{ $brand->name }}</bdi></span>@endif
</a>
