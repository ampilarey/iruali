@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Tax')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('seller.settings._nav')

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($profile?->isRegistered())
            <div class="rounded-lg bg-white p-5 shadow text-sm">
                <h2 class="font-semibold text-gray-900">{{ __('Your invoices show GST') }}</h2>
                <p class="mt-1 text-gray-700">{{ $profile->registered_name }} · <span class="font-mono" dir="ltr">{{ $profile->tin }}</span></p>
            </div>
        @endif

        @include('seller.settings._tax')
    </div>
</div>
@endsection
