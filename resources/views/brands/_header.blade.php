{{-- Top of a brand's page: logo, name, description, who sells it and where it sits in the shop. --}}
@php $currentDepartment = request('category'); @endphp
{{-- A running campaign for this brand only (Admin → Campaigns, "Only for brand"), like the category strips --}}
@foreach($brand->campaigns()->live()->ordered()->get() as $brandCampaign)
    <div class="mb-4" data-brand-campaign>@include('campaigns._banner', ['campaign' => $brandCampaign, 'size' => 'strip', 'link' => route('campaigns.show', $brandCampaign)])</div>
@endforeach
<div class="bg-white border border-gray-200 rounded-xl p-4 lg:p-6 mb-5">
    <div class="flex items-center gap-4">
        @include('brands._logo', ['brand' => $brand, 'class' => 'w-16 h-16 lg:w-20 lg:h-20 text-2xl lg:text-3xl'])
        <div class="min-w-0">
            <h1 class="font-display text-xl lg:text-3xl font-bold text-dark">{{ $brand->localizedName() }}</h1>
            @if($brand->localizedName() !== $brand->name)<p class="text-sm text-gray-500" lang="en"><bdi>{{ $brand->name }}</bdi></p>@endif
            <p class="text-sm text-gray-600 mt-1 max-w-3xl whitespace-pre-line">{{ $subtitle }}</p>
            <p class="text-sm text-gray-500 mt-1">
                {{ trans_choice(':count product|:count products', $brand->active_products_count, ['count' => $brand->active_products_count]) }}
                · {{ trans_choice('sold by :count shop|sold by :count shops', $brandShopCount, ['count' => $brandShopCount]) }}
                @if(($brandFollowers ?? 0) >= \App\Models\Brand::FOLLOWERS_SHOWN_FROM)
                    · {{ trans_choice(':count follower|:count followers', $brandFollowers, ['count' => $brandFollowers]) }}
                @endif
            </p>
            @include('brands._follow')
        </div>
    </div>

    @if($brandShops->isNotEmpty())
        {{-- Authorised sellers of the brand come first (BrandService::shops) and carry the badge --}}
        @php $authorisedShopCount = $brandShops->filter(fn ($shop) => $brand->isAuthorisedSeller($shop->id))->count(); @endphp
        @php $brandShops->loadMissing('businessVerification'); @endphp {{-- the "Verified business" marks below, in one query --}}
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 me-1">{{ __('Sold by') }}</span>
            @foreach($brandShops as $shop)
                @php $authorisedShop = $brand->isAuthorisedSeller($shop->id); @endphp
                <a href="{{ route('sellers.show', $shop) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border {{ $authorisedShop ? 'border-success-100 bg-success-50' : 'border-gray-200 bg-white' }} text-sm font-medium text-dark hover:border-primary hover:text-primary" @if($authorisedShop) title="{{ __('iruali has confirmed this shop is an authorised seller of :brand.', ['brand' => $brand->localizedName()]) }}" data-authorised-seller @endif>
                    <x-icon :name="$authorisedShop ? 'badge' : 'store'" class="w-4 h-4 {{ $authorisedShop ? 'text-success' : 'text-gray-500' }}" />{{ $shop->business_name ?: $shop->name }}
                    @if($authorisedShop)<span class="text-xs font-semibold text-success">{{ __('Authorised seller') }}</span>@endif
                    @include('sellers._verified_badge', ['seller' => $shop, 'style' => 'chip'])
                    <span class="text-xs text-gray-500">{{ $shop->brand_product_count }}</span>
                </a>
            @endforeach
        </div>
        @if($authorisedShopCount > 0)
            <p class="mt-2 flex items-start gap-1.5 text-xs text-gray-500"><x-icon name="badge" class="w-4 h-4 shrink-0 text-success" />{{ trans_choice('iruali has confirmed this shop is an authorised seller of :brand.|iruali has confirmed these shops are authorised sellers of :brand.', $authorisedShopCount, ['brand' => $brand->localizedName()]) }}</p>
        @endif
        @include('sellers._verified_footnote', ['shops' => $brandShops])
    @endif

    @if($brandDepartments->count() > 1)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 me-1">{{ __('Departments') }}</span>
            <a href="{{ request()->url() }}" class="px-3 py-1.5 rounded-full text-sm font-medium {{ $currentDepartment ? 'border border-gray-200 bg-white text-dark hover:border-primary hover:text-primary' : 'bg-primary text-white' }}" @unless($currentDepartment) aria-current="true" @endunless>{{ __('All') }}</a>
            @foreach($brandDepartments as $department)
                @php $selected = $currentDepartment === $department->slug; @endphp
                <a href="{{ request()->url().'?category='.urlencode($department->slug) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium {{ $selected ? 'bg-primary text-white' : 'border border-gray-200 bg-white text-dark hover:border-primary hover:text-primary' }}" @if($selected) aria-current="true" @endif>
                    {{ $department->localized_name }}<span class="text-xs {{ $selected ? 'text-white/80' : 'text-gray-500' }}">{{ $department->brand_product_count }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
