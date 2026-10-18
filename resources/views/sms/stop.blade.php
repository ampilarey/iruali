@extends('layouts.app')

@section('title', __('Stop offers by SMS'))

@push('styles')<meta name="robots" content="noindex, nofollow">@endpush

@section('content')
<div class="max-w-md mx-auto px-4 py-12 text-center space-y-4" data-sms-stop>
    <h1 class="font-display text-2xl font-bold text-dark">{{ __('Stop offers by SMS') }}</h1>
    @if($done)
        <p class="rounded-xl bg-green-50 p-4 text-green-800">{{ __('Done. You will not get offers by SMS any more. Order updates still come as before.') }}</p>
    @else
        <p class="text-gray-600">{{ __('You will still get texts about your orders.') }}</p>
        <form method="POST" action="{{ route('sms.stop.store', $code) }}">
            @csrf
            <button class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-white hover:bg-primary-hover">{{ __('Stop offers by SMS') }}</button>
        </form>
    @endif
</div>
@endsection
