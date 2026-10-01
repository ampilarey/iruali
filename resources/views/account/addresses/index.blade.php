@extends('layouts.app')

@section('title', __('My addresses'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-dark">{{ __('My addresses') }}</h1>
                <p class="text-sm text-gray-600 mt-1">{{ __('Saved addresses are offered at checkout. The default one is selected for you.') }}</p>
            </div>
            <a href="{{ route('account.addresses.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"><x-icon name="plus" class="w-4 h-4" />{{ __('Add address') }}</a>
        </div>

        @if($addresses->isEmpty())
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-10 text-center text-gray-500">
                <x-icon name="map-pin" class="w-8 h-8 mx-auto text-gray-300" />
                <p class="mt-3">{{ __('No saved addresses yet.') }}</p>
                <a href="{{ route('account.addresses.create') }}" class="mt-3 inline-block font-semibold text-primary hover:underline">{{ __('Add your first address') }}</a>
            </div>
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach($addresses as $address)
                    <li class="bg-white rounded-lg shadow-sm border {{ $address->is_default ? 'border-primary' : 'border-gray-100' }} p-5 flex flex-col">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold text-dark">{{ $address->label ?: __('Address') }}</p>
                            @if($address->is_default)<span class="rounded-full bg-primary-50 text-primary px-2 py-0.5 text-xs font-semibold">{{ __('Default') }}</span>@endif
                        </div>
                        <p class="mt-2 text-sm text-gray-900">{{ $address->recipient_name }}</p>
                        <p class="text-sm text-gray-600">{{ $address->summary() }}</p>
                        <p class="text-sm text-gray-600">{{ $address->country }}</p>
                        @if($address->phone)<p class="text-sm text-gray-600" dir="ltr">{{ $address->phone }}</p>@endif
                        <div class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap items-center gap-3 text-sm">
                            <a href="{{ route('account.addresses.edit', $address) }}" class="font-medium text-primary hover:underline">{{ __('Edit') }}</a>
                            @unless($address->is_default)
                                <form method="POST" action="{{ route('account.addresses.default', $address) }}">@csrf<button type="submit" class="font-medium text-gray-700 hover:text-primary hover:underline">{{ __('Set as default') }}</button></form>
                            @endunless
                            <form method="POST" action="{{ route('account.addresses.destroy', $address) }}" class="ms-auto" onsubmit="return confirm('{{ __('Remove this address?') }}')">@csrf @method('DELETE')<button type="submit" class="font-medium text-danger hover:underline">{{ __('Remove') }}</button></form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-6"><a href="{{ route('account') }}" class="text-sm text-primary hover:underline">{{ __('← Back to My Account') }}</a></p>
    </div>
</div>
@endsection
