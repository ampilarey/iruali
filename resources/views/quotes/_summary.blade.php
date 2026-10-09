{{-- A quote request's details, the same for the customer, the shop and staff. Needs $quote; $viewer is 'customer', 'seller' or 'admin'. --}}
@php
    $productUrl = $quote->product && ! $quote->product->trashed() && $quote->product->is_active ? route('products.show', $quote->product) : null;
@endphp
<section class="rounded-xl border border-gray-200 bg-white p-5" data-quote-summary>
    <div class="flex gap-4">
        <img src="{{ $quote->product?->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="h-16 w-16 shrink-0 rounded-lg bg-primary-50 object-cover">
        <div class="min-w-0 text-sm">
            @if($productUrl)
                <a href="{{ $productUrl }}" class="font-semibold text-dark hover:text-primary hover:underline">{{ $quote->productName() }}</a>
            @else
                <p class="font-semibold text-dark">{{ $quote->productName() }} <span class="font-normal text-gray-500">({{ __('no longer on sale') }})</span></p>
            @endif
            @if($quote->variant_name)<p class="text-gray-600">{{ $quote->variant_name }}</p>@endif
            @if($viewer !== 'seller')<p class="text-gray-600">{{ __('Sold by') }} {{ $quote->shopName() }}</p>@endif
        </div>
    </div>

    <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        <div><dt class="text-gray-500">{{ __('Quantity asked for') }}</dt><dd class="font-semibold text-dark" dir="ltr">{{ $quote->quantity }}</dd></div>
        <div><dt class="text-gray-500">{{ __('Deliver to') }}</dt><dd class="text-dark">{{ $quote->deliveryPlace() }}</dd></div>
        <div><dt class="text-gray-500">{{ __('Needed by') }}</dt><dd class="text-dark">{{ $quote->needed_by ? $quote->needed_by->translatedFormat('j M Y') : __('No date given') }}</dd></div>
        <div><dt class="text-gray-500">{{ __('Asked on') }}</dt><dd class="text-dark">{{ $quote->created_at->translatedFormat('j M Y, H:i') }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('Business') }}</dt>
            <dd class="text-dark" data-quote-business-details>
                <span class="font-semibold">{{ $quote->business_name }}</span>@if($quote->business_tin) · {{ __('TIN') }} <span dir="ltr">{{ $quote->business_tin }}</span>@endif
                <span class="block text-gray-600">{{ $quote->business_address }}</span>
                @if($viewer === 'admin' && $quote->customer)<span class="block text-gray-600">{{ $quote->customer->name }} · <span dir="ltr">{{ $quote->customer->email }}</span></span>@endif
            </dd>
        </div>
        @if($quote->notes)
            <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('Notes') }}</dt><dd class="whitespace-pre-line break-words text-dark" dir="auto">{{ $quote->notes }}</dd></div>
        @endif
    </dl>
</section>
