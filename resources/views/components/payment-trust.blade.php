@props(['compact' => false])
{{-- Card acceptance and the payment disclosures Bank of Maldives asks for on pages that present payment options. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 text-xs text-gray-600 space-y-2']) }}>
    <img src="/images/card-brands.png" alt="{{ __('We accept American Express, Visa, Mastercard and Maestro') }}" width="368" height="75" class="h-12 w-auto -ms-2">
    <p>{{ __('Transaction currency') }}: <strong class="text-dark">MVR ({{ __('Maldivian Rufiyaa') }})</strong> &middot; {{ __('Merchant outlet country') }}: <strong class="text-dark">{{ __('Maldives') }}</strong></p>
    @unless($compact)
        <p class="flex items-start gap-1.5"><x-icon name="shield" class="w-4 h-4 shrink-0 text-success" />{{ __('Card payments are processed securely by Bank of Maldives over an encrypted connection. We never see or store your card details.') }} <a href="{{ route('policies.security') }}" class="text-primary font-medium hover:underline">{{ __('Payment Security') }}</a></p>
        <p>{{ __('Please keep a copy of your order confirmation, payment receipt and our policies for your records.') }}</p>
    @endunless
</div>
