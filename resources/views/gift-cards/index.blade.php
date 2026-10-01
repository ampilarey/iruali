@extends('layouts.app')

@section('title', __('Gift cards'))

@php $field = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500'; @endphp

@section('content')
<div class="bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 lg:px-6 py-8 lg:py-12">
        <div class="grid lg:grid-cols-2 gap-8 items-start">
            <div>
                <div class="rounded-2xl bg-primary text-white p-8 min-h-[14rem] flex flex-col justify-end relative overflow-hidden">
                    <x-icon name="gift" class="absolute -top-4 -end-4 w-40 h-40 opacity-20" />
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/80">iruali</p>
                    <h1 class="font-display text-3xl lg:text-4xl font-bold">{{ __('Gift cards') }}</h1>
                    <p class="mt-2 text-white/85">{{ __('The easiest gift for anyone on any island. Sent by email, spent on any shop on iruali.') }}</p>
                </div>
                <ul class="mt-6 space-y-3 text-sm text-gray-700">
                    <li class="flex gap-3"><x-icon name="check" class="w-5 h-5 shrink-0 text-success" />{{ __('Delivered to the recipient\'s email as soon as your card payment is confirmed.') }}</li>
                    <li class="flex gap-3"><x-icon name="check" class="w-5 h-5 shrink-0 text-success" />{{ __('They redeem the code into their iruali wallet and spend it on any order.') }}</li>
                    <li class="flex gap-3"><x-icon name="check" class="w-5 h-5 shrink-0 text-success" />{{ __('Valid for 12 months. Gift cards cannot be refunded or exchanged for cash.') }}</li>
                </ul>
                <p class="mt-4 text-sm"><a href="{{ route('account.wallet') }}" class="text-primary hover:underline">{{ __('Received a gift card? Redeem it here.') }}</a></p>
            </div>

            <form method="POST" action="{{ route('gift-cards.store') }}" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                @csrf
                @if($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @unless($paymentsOpen)
                    <p class="rounded-xl border border-sun bg-sun-soft px-4 py-3 text-sm text-sun-ink">{{ __('Card payment is not available right now, so gift cards can\'t be bought. Please try again later.') }}</p>
                @endunless

                <fieldset>
                    <legend class="text-sm font-medium text-gray-900 mb-2">{{ __('Amount') }}</legend>
                    <div class="grid grid-cols-5 gap-2">
                        @foreach($amounts as $amount)
                            <label class="rounded-lg border border-gray-200 py-2 text-center text-sm font-semibold cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50 has-[:checked]:text-primary">
                                <input type="radio" name="amount" value="{{ $amount }}" class="sr-only" @checked(old('amount', $amounts[1]) == $amount)><span dir="ltr">{{ $amount }}</span>
                            </label>
                        @endforeach
                        <label class="rounded-lg border border-gray-200 py-2 text-center text-sm font-semibold cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50 has-[:checked]:text-primary">
                            <input type="radio" name="amount" value="custom" class="sr-only" @checked(old('amount') === 'custom')>{{ __('Other') }}
                        </label>
                    </div>
                    <div class="mt-2">
                        <label for="custom_amount" class="block text-xs text-gray-600 mb-1">{{ __('Other amount (MVR :min to :max)', ['min' => $min, 'max' => $max]) }}</label>
                        <input id="custom_amount" name="custom_amount" type="number" min="{{ $min }}" max="{{ $max }}" step="1" dir="ltr" value="{{ old('custom_amount') }}" class="{{ $field }}" placeholder="{{ __('e.g. 300') }}">
                    </div>
                </fieldset>

                <div>
                    <label for="recipient_email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Recipient\'s email') }}</label>
                    <input id="recipient_email" name="recipient_email" type="email" required dir="ltr" value="{{ old('recipient_email') }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Recipient\'s name') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="recipient_name" name="recipient_name" maxlength="120" value="{{ old('recipient_name') }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Message') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <textarea id="message" name="message" rows="3" maxlength="500" class="{{ $field }}" placeholder="{{ __('Happy birthday!') }}">{{ old('message') }}</textarea>
                </div>

                @auth
                    <button type="submit" @disabled(! $paymentsOpen) class="w-full bg-primary text-white py-3 px-4 rounded-xl font-semibold hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed">{{ __('Pay by card and send') }}</button>
                    <x-payment-trust />
                @else
                    <a href="{{ route('login') }}" class="block w-full text-center bg-primary text-white py-3 px-4 rounded-xl font-semibold hover:bg-primary-hover">{{ __('Sign in to buy a gift card') }}</a>
                @endauth
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        var custom = document.getElementById('custom_amount');
        var radios = document.querySelectorAll('input[name="amount"]');
        if (! custom) return;
        custom.addEventListener('input', function () { radios.forEach(function (r) { r.checked = r.value === 'custom'; }); });
        radios.forEach(function (r) { r.addEventListener('change', function () { if (r.value !== 'custom') custom.value = ''; }); });
    })();
</script>
@endsection
