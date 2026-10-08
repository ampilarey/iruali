{{-- Admin → Brands → edit: shops iruali has confirmed as authorised sellers of this brand (BrandSellerController) --}}
<section id="authorised-sellers" class="rounded-lg bg-white p-6 shadow text-sm scroll-mt-6">
    <h2 class="text-base font-semibold text-gray-900">{{ __('Authorised sellers') }}</h2>
    <p class="mt-1 text-gray-600">{{ __('Shops iruali has confirmed may sell :brand. Their :brand products show an "Authorised seller" badge, and they come first on the brand page.', ['brand' => $brand->name]) }}</p>

    @if($authorisedSellers->isEmpty())
        <p class="mt-3 text-gray-500">{{ __('No authorised sellers yet.') }}</p>
    @else
        <ul class="mt-3 divide-y divide-gray-100" data-authorised-sellers>
            @foreach($authorisedSellers as $authorised)
                <li class="flex items-start justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <p class="flex items-center gap-1.5 font-medium text-gray-900"><x-icon name="badge" class="w-4 h-4 shrink-0 text-success" /><span class="truncate">{{ $authorised->shopName() }}</span></p>
                        <p class="text-xs text-gray-500">
                            {{ __('Since :date', ['date' => $authorised->pivot->created_at?->format('d M Y')]) }}@if($authorisedBy->has($authorised->pivot->authorised_by)) · {{ __('by :name', ['name' => $authorisedBy[$authorised->pivot->authorised_by]]) }}@endif
                            @unless($authorised->isSeller()) · <span class="text-red-700">{{ __('Not an approved shop now') }}</span>@endunless
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.brands.sellers.destroy', [$brand, $authorised]) }}" onsubmit="return confirm(@js(__('Remove :shop as an authorised seller of :brand?', ['shop' => $authorised->shopName(), 'brand' => $brand->name])));">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-700 hover:underline">{{ __('Remove') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.brands.sellers.store', $brand) }}" class="mt-4 border-t border-gray-100 pt-4">
        @csrf
        <label for="seller_id" class="block font-medium text-gray-700">{{ __('Add a shop that sells :brand', ['brand' => $brand->name]) }}</label>
        @if($sellingShops->isEmpty())
            <p class="mt-1 text-gray-500">{{ __('No other shop has :brand products on sale.', ['brand' => $brand->name]) }}</p>
        @else
            <div class="mt-1 flex gap-2">
                <select id="seller_id" name="seller_id" required class="block w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="">{{ __('Choose a shop…') }}</option>
                    @foreach($sellingShops as $shop)
                        <option value="{{ $shop->id }}">{{ $shop->shopName() }} ({{ trans_choice(':count product|:count products', $shop->brand_product_count, ['count' => $shop->brand_product_count]) }})</option>
                    @endforeach
                </select>
                <button type="submit" class="shrink-0 rounded-lg bg-primary-600 px-4 py-2 font-medium text-white hover:bg-primary-700">{{ __('Authorise') }}</button>
            </div>
        @endif
        @error('seller_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </form>

    <form method="GET" action="{{ route('admin.brands.edit', $brand) }}#authorised-sellers" class="mt-4" role="search">
        <label for="seller_q" class="block font-medium text-gray-700">{{ __('Or find any approved shop') }}</label>
        <div class="mt-1 flex gap-2">
            <input id="seller_q" type="search" name="seller_q" value="{{ $sellerSearch }}" maxlength="100" placeholder="{{ __('Shop name or email') }}" class="block w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
            <button type="submit" class="shrink-0 rounded-lg border border-gray-300 px-4 py-2 font-medium text-gray-700 hover:bg-gray-50">{{ __('Search') }}</button>
        </div>
    </form>
    @if($sellerSearch !== '')
        @if($sellerMatches->isEmpty())
            <p class="mt-2 text-gray-500">{{ __('No other approved shop matches that search.') }}</p>
        @else
            <ul class="mt-2 divide-y divide-gray-100" data-seller-matches>
                @foreach($sellerMatches as $match)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-900">{{ $match->shopName() }}</p>
                            <p class="truncate text-xs text-gray-500" dir="ltr">{{ $match->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.brands.sellers.store', $brand) }}">
                            @csrf
                            <input type="hidden" name="seller_id" value="{{ $match->id }}">
                            <button type="submit" class="text-primary-700 hover:underline">{{ __('Authorise') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
