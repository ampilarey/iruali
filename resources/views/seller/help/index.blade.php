@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Help centre')])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600">{{ __('Short guides on running your shop on iruali. Everything here describes how the Seller Centre actually works, in English and Dhivehi.') }}</p>

        <ul class="grid gap-4 sm:grid-cols-2">
            @foreach($guides as $slug => $guide)
                <li>
                    <a href="{{ route('seller.help.show', $slug) }}" class="block h-full rounded-lg bg-white p-5 shadow hover:shadow-md">
                        <h2 class="text-base font-semibold text-gray-900">{{ $guide['title'] }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $guide['summary'] }}</p>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="rounded-lg border border-primary-100 bg-primary-50 px-4 py-3 text-sm text-gray-700">
            {{ __('Still stuck? Email or WhatsApp iruali using the contact details in the footer, and we will help.') }}
        </div>
    </div>
</div>
@endsection
