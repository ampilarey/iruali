@extends('layouts.app')

{{-- Where a shop that has not agreed to the current Seller Terms is sent (App\Http\Middleware\ShopAccess). --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Seller Terms')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6" data-seller-terms>
        @if($agreed)
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" data-seller-terms-agreed>
                {{ __('Your shop agreed to the current Seller Terms (:date) on :when.', ['date' => $version, 'when' => $shop->seller_terms_agreed_at?->translatedFormat('j F Y') ?? '—']) }}
                <a href="{{ route('policies.seller_terms') }}" target="_blank" rel="noopener" class="font-medium underline">{{ __('Read the Seller Terms') }} ↗</a>
            </div>
        @else
            <section class="rounded-lg bg-white p-6 shadow space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ $previous ? __('iruali\'s Seller Terms have changed') : __('Please agree to iruali\'s Seller Terms') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Version of :date.', ['date' => $version]) }}
                        @if($previous){{ __('Your shop agreed to the version of :date.', ['date' => $previous]) }}@endif
                        {{ __('The Seller Centre opens again once the shop owner agrees. Your products stay on sale and customers can still order meanwhile.') }}
                    </p>
                </div>

                @if($changes !== [])
                    <div class="rounded-lg border border-primary-100 bg-primary-50 p-4 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">{{ __('What changed') }}</p>
                        <ul class="mt-2 list-disc space-y-1 ps-5" data-seller-terms-changes>
                            @foreach($changes as $change)
                                <li>{{ $change }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="text-sm">
                    <a href="{{ route('policies.seller_terms') }}" target="_blank" rel="noopener" class="font-semibold text-primary-700 hover:underline" data-seller-terms-read>{{ __('Read the full Seller Terms') }} ↗</a>
                </p>

                @if($isOwner)
                    <form method="POST" action="{{ route('seller.terms.agree') }}" class="space-y-3">
                        @csrf
                        <label class="flex items-start gap-3 text-sm text-gray-800">
                            <input type="checkbox" name="agree" value="1" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600">
                            <span>{{ __('I have read the Seller Terms (version of :date) and agree to them for my shop.', ['date' => $version]) }}</span>
                        </label>
                        @error('agree')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                        <button class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700" data-seller-terms-agree>{{ __('I agree') }}</button>
                    </form>
                @else
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" data-seller-terms-staff>
                        {{ __('Only the shop owner can agree. Please ask them to sign in and agree; the Seller Centre opens for you once they have.') }}
                    </div>
                @endif
            </section>
        @endif
    </div>
</div>
@endsection
