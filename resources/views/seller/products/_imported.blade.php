{{-- Seller product form, filled from a product's page abroad (App\Services\ProductImportService): where it came from, the
     price there and the MVR price suggested from iruali's rates, and the photos to keep. Needs $imported (the draft). --}}
@php
    $importer = app(\App\Services\ProductImportService::class);
    $suggestion = $importer->suggestPrice($imported['price'], $imported['currency'], $imported['weight']);
    $keptImages = old('imported_images', $imported['images']);
@endphp
<section class="space-y-3 rounded-lg border border-primary-200 bg-primary-50 p-4 text-sm" data-imported>
    <input type="hidden" name="from_link" value="1">
    <div>
        <h2 class="font-semibold text-gray-900">{{ __('Filled in from a page abroad') }}</h2>
        <a href="{{ $imported['source_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="block truncate text-xs text-primary-700 underline" dir="ltr">{{ $imported['source_url'] }}</a>
        <p class="mt-1 text-xs text-gray-600">{{ __('Check every field: pages are not always complete or right. Choose a category, then the pre-order details below.') }}</p>
    </div>

    <div data-imported-price>
        @if($imported['price'] !== null && $imported['currency'])
            <p>{{ __('Price on the page: :price', ['price' => $imported['currency'].' '.number_format($imported['price'], 2)]) }}</p>
        @endif
        @if($suggestion)
            <p class="font-medium text-gray-900">{{ __('Suggested price: :amount', ['amount' => \App\Support\Money::format($suggestion['price'])]) }}</p>
            <p class="text-xs text-gray-600">{{ __('Rate :rate, shipping :shipping, margin :margin%. It is filled in below; change it if you need to.', ['rate' => rtrim(rtrim(number_format($suggestion['rate'], 4), '0'), '.'), 'shipping' => \App\Support\Money::format($suggestion['shipping']), 'margin' => rtrim(rtrim(number_format($suggestion['margin'], 2), '0'), '.')]) }}
                @if($imported['weight'] === null) {{ __('The page gives no weight, so shipping is not included.') }}@endif</p>
        @else
            <p class="text-xs text-gray-600">{{ __('No price in MVR could be worked out (no price on the page, or no exchange rate for its currency). Please enter the price yourself.') }}</p>
        @endif
    </div>

    @if($imported['images'] !== [])
        <fieldset>
            <legend class="font-medium text-gray-900">{{ __('Photos from the page') }}</legend>
            <p class="text-xs text-gray-600">{{ __('Untick any you do not want. The first one ticked becomes the main image, unless you upload one below.') }}</p>
            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-6">
                @foreach($imported['images'] as $path)
                    <label class="block cursor-pointer rounded-lg border border-gray-200 bg-white p-1">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($path) }}" alt="" class="aspect-square w-full rounded object-cover">
                        <span class="mt-1 flex items-center justify-center"><input type="checkbox" name="imported_images[]" value="{{ $path }}" @checked(in_array($path, (array) $keptImages, true)) class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" aria-label="{{ __('Keep this photo') }}"></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @else
        <p class="text-xs text-gray-600">{{ __('No photos could be taken from the page. Upload the main image below.') }}</p>
    @endif
</section>
