@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Notifications')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('seller.settings._nav')

        <form method="POST" action="{{ route('seller.settings.notifications.update') }}" class="rounded-lg bg-white p-6 shadow space-y-4">
            @csrf
            @method('PUT')
            <div>
                <h2 class="text-base font-semibold text-gray-900">{{ __('Emails from iruali') }}</h2>
                <p class="text-sm text-gray-500">{{ __('Sent to :email. Turn off the ones you do not want; order details are always available in the Seller Centre.', ['email' => $user->email]) }}</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($types as $type => $info)
                    <li class="flex items-start justify-between gap-4 py-3">
                        <label for="pref-{{ $type }}" class="flex-1 cursor-pointer">
                            <span class="block text-sm font-medium text-gray-900">{{ $info['label'] }}</span>
                            <span class="block text-xs text-gray-500">{{ $info['hint'] }}</span>
                        </label>
                        <input type="hidden" name="{{ $type }}" value="0">
                        <input id="pref-{{ $type }}" type="checkbox" name="{{ $type }}" value="1" class="mt-1 h-5 w-5 rounded border-gray-300 text-primary-600" @checked(old($type, $preferences[$type]))>
                    </li>
                @endforeach
            </ul>
            <div class="flex justify-end border-t border-gray-100 pt-4">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save notification settings') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
