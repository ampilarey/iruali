@extends('layouts.admin')

@section('title', 'New shop applications · Sellers')

@section('content')
@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $choice = old('seller_applications', $stored);
    $options = [
        'open' => ['Open', 'Anyone can apply through "Sell on iruali". New applications wait under Admin → Sellers for you to approve.'],
        'paused' => ['Paused', '"Sell on iruali" stays on the site, but shows your message and a "Tell me when you reopen" button instead of the form.'],
        'hidden' => ['Hidden', 'The "Sell on iruali" links come off the menus, footer, home page and help page. Anyone who still has the address sees your message.'],
    ];
@endphp
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => 'New shop applications', 'back' => route('admin.sellers')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.seller-applications.update') }}" class="lg:col-span-2 space-y-5 rounded-lg bg-white p-6 shadow">
            @csrf
            @method('PUT')

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">Applications are</legend>
                <div class="mt-2 grid gap-3">
                    @foreach($options as $value => [$label, $hint])
                        <label class="flex items-start gap-3 rounded-lg border p-3 {{ $choice === $value ? 'border-primary-500 bg-primary-50' : 'border-gray-200' }}">
                            <input type="radio" name="seller_applications" value="{{ $value }}" class="mt-1 text-primary-600 focus:ring-primary-500" @checked($choice === $value) data-application-status="{{ $value }}">
                            <span><span class="block text-sm font-semibold text-gray-900">{{ $label }}</span><span class="block text-sm text-gray-600">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('seller_applications')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </fieldset>

            <div>
                <label for="seller_applications_until" class="block text-sm font-medium text-gray-700">Reopen automatically on (optional)</label>
                <input id="seller_applications_until" name="seller_applications_until" type="date" min="{{ today()->addDay()->toDateString() }}" class="{{ $field }} max-w-xs" value="{{ old('seller_applications_until', $reopensOn?->toDateString()) }}" aria-describedby="until-hint">
                @error('seller_applications_until')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <p id="until-hint" class="mt-1 text-xs text-gray-500">When paused or hidden, applications open again at the start of this day (Maldives time) and everyone on the waiting list gets an email. Leave it empty to reopen them yourself.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="seller_applications_message_en" class="block text-sm font-medium text-gray-700">Message (English)</label>
                    <textarea id="seller_applications_message_en" name="seller_applications_message_en" rows="3" maxlength="300" class="{{ $field }}" placeholder="We are getting our first shops ready and will take new ones again soon.">{{ old('seller_applications_message_en', $messageEn) }}</textarea>
                    @error('seller_applications_message_en')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="seller_applications_message_dv" class="block text-sm font-medium text-gray-700">Message (Dhivehi)</label>
                    <textarea id="seller_applications_message_dv" name="seller_applications_message_dv" rows="3" maxlength="300" dir="rtl" lang="dv" class="{{ $field }}">{{ old('seller_applications_message_dv', $messageDv) }}</textarea>
                    @error('seller_applications_message_dv')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <p class="sm:col-span-2 -mt-2 text-xs text-gray-500">Shown while applications are paused or hidden, up to 300 characters. Without one, visitors read "We have paused new shop applications for a while." Without a Dhivehi message, Dhivehi visitors see the English one.</p>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('seller.apply') }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">View the page</a>
                <button type="submit" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700">Save</button>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="rounded-lg bg-white p-5 shadow" data-applications-now>
                <h2 class="text-sm font-semibold text-gray-900">Right now</h2>
                <p class="mt-1 text-sm text-gray-700">
                    @if($status === 'open')
                        Applications are <span class="font-semibold text-green-700">open</span>.
                    @else
                        Applications are <span class="font-semibold text-amber-700">{{ $status }}</span>{{ $reopensOn ? ' until '.$reopensOn->format('j M Y') : '' }}.
                    @endif
                </p>
                <p class="mt-3 text-sm text-gray-700"><span class="font-semibold">{{ $waiting }}</span> {{ $waiting === 1 ? 'person is' : 'people are' }} waiting to hear when applications reopen.</p>
                <p class="mt-1 text-sm text-gray-700"><span class="font-semibold">{{ $pending }}</span> {{ $pending === 1 ? 'application is' : 'applications are' }} waiting for your review. <a href="{{ route('admin.sellers') }}" class="text-primary-700 hover:underline">Review</a></p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow text-sm text-gray-600">
                <p>Pausing or hiding never affects shops already on iruali or applications already sent: you can still approve or reject those.</p>
            </div>
        </aside>
    </div>
</div>
@endsection
