@php $sellLinks = \App\Support\SellerApplications::linksShown(); @endphp
@if($sellLinks)
<!-- Sell CTA -->
<section class="rounded-2xl bg-white border border-gray-200 p-6 lg:p-10 flex flex-col lg:flex-row lg:items-center gap-6">
    <span class="w-14 h-14 shrink-0 rounded-2xl bg-sun-soft text-sun-ink flex items-center justify-center"><x-icon name="store" class="w-7 h-7" /></span>
    <div class="flex-1">
        <h2 class="font-display text-2xl font-bold text-dark">{{ __('Sell on iruali') }}</h2>
        <p class="text-gray-600 mt-1">{{ __('Have a shop? Reach customers on every inhabited island. Applying takes five minutes.') }}</p>
    </div>
    <a href="{{ route('seller.apply') }}" class="self-start lg:self-auto inline-flex items-center px-6 py-3 rounded-lg bg-primary hover:bg-primary-hover text-white font-semibold">{{ __('Open your shop') }}</a>
</section>
@endif
