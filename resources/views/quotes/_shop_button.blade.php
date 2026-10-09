{{-- Shop page: businesses can ask the shop for a bulk quote on any of its products (the form lets them pick one). Not while it is on holiday. --}}
@if($seller->isSeller() && ! $seller->isOnHoliday() && auth()->id() !== $seller->id)
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm lg:px-6" data-shop-bulk-quote>
        <p class="flex items-center gap-2 text-gray-700"><x-icon name="box" class="w-5 h-5 shrink-0 text-primary" />{{ __('Buying in bulk for a resort, office or café? Ask :shop for a price on any of its products.', ['shop' => $seller->shopName()]) }}</p>
        <a href="{{ route('quotes.create', ['shop' => $seller->id]) }}" class="inline-flex items-center rounded-lg border border-primary px-4 py-2 font-semibold text-primary hover:bg-primary-50">{{ auth()->check() ? __('Request a bulk quote') : __('Sign in to request a bulk quote') }}</a>
    </div>
@endif
