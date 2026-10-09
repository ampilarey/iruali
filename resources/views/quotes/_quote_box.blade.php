{{-- The shop's quote (price, quantity, total, last day, message), once there is one. Needs $quote; $viewer 'customer', 'seller' or 'admin'. --}}
@if($quote->unit_price !== null && $quote->quoted_at)
    <section class="rounded-xl border border-primary-200 bg-primary-50 p-5 text-sm" data-quote-offer>
        <h2 class="font-semibold text-dark">{{ ($viewer ?? 'customer') === 'seller' ? __('Your quote') : __('The shop\'s quote') }}</h2>
        <dl class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-3">
            <div><dt class="text-gray-600">{{ __('Unit price') }}</dt><dd class="text-lg font-bold text-dark" dir="ltr">{{ \App\Support\Money::format($quote->unit_price) }}</dd>
                @if($saving = $quote->savingPercent())<dd class="text-xs font-semibold text-success">{{ __(':percent% under the listed :price', ['percent' => $saving, 'price' => \App\Support\Money::format($quote->list_price)]) }}</dd>@endif
            </div>
            <div><dt class="text-gray-600">{{ __('Quantity') }}</dt><dd class="text-lg font-bold text-dark" dir="ltr">{{ $quote->quoted_quantity }}</dd>
                @if((int) $quote->quoted_quantity !== (int) $quote->quantity)<dd class="text-xs text-gray-600">{{ __('Asked for: :count', ['count' => $quote->quantity]) }}</dd>@endif
            </div>
            <div><dt class="text-gray-600">{{ __('Total') }}</dt><dd class="text-lg font-bold text-primary-700" dir="ltr" data-quote-total>{{ \App\Support\Money::format($quote->lineTotal()) }}</dd></div>
        </dl>
        <p class="mt-3 text-gray-700">
            @if($quote->isExpired() || $quote->hasStatus(\App\Enums\QuoteStatus::Expired))
                {{ __('This price held until :date.', ['date' => $quote->valid_until?->translatedFormat('j M Y')]) }}
            @else
                {{ __('This price holds until the end of :date.', ['date' => $quote->valid_until?->translatedFormat('j M Y')]) }}
            @endif
            <span class="text-gray-500">{{ __('Sent :date', ['date' => $quote->quoted_at->translatedFormat('j M Y, H:i')]) }}</span>
        </p>
        @if($quote->shop_message)
            <p class="mt-3 whitespace-pre-line break-words rounded-lg bg-white px-3 py-2 text-gray-800" dir="auto">{{ $quote->shop_message }}</p>
        @endif
    </section>
@endif
