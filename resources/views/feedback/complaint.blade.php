@extends('layouts.app')

@section('title', __('Make a complaint'))

@section('content')
<div class="max-w-xl mx-auto px-4 py-8 space-y-5" data-complaint-page>
    <div>
        <h1 class="font-display text-2xl font-bold text-dark">{{ __('Make a complaint') }}</h1>
        <p class="mt-1 text-gray-600">{{ __('Tell us what went wrong. We reply by email, usually within two working days.') }}</p>
    </div>

    @if(session('complaint_reference'))
        <p class="rounded-xl bg-green-50 p-4 font-medium text-green-800" data-complaint-sent>{{ __('Thank you. Your complaint reference is :reference. We have emailed it to you.', ['reference' => session('complaint_reference')]) }}</p>
    @else
        <form method="POST" action="{{ route('complaints.store') }}" enctype="multipart/form-data" class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 text-sm">
            @csrf
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="c-name" class="block text-gray-700">{{ __('Name') }}</label>
                    <input id="c-name" name="name" required maxlength="120" value="{{ old('name', $user?->name) }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2">
                    @error('name')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="c-email" class="block text-gray-700">{{ __('Email') }}</label>
                    <input id="c-email" name="email" type="email" required value="{{ old('email', $user?->email) }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2">
                    @error('email')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="c-phone" class="block text-gray-700">{{ __('Phone (optional)') }}</label>
                    <input id="c-phone" name="phone" maxlength="20" value="{{ old('phone', $user?->phone) }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2" dir="ltr">
                </div>
                <div>
                    <label for="c-order" class="block text-gray-700">{{ __('Order number (optional)') }}</label>
                    <input id="c-order" name="order_number" maxlength="40" value="{{ old('order_number', $orderNumber) }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2" dir="ltr">
                </div>
            </div>
            <div>
                <label for="c-category" class="block text-gray-700">{{ __('What is it about?') }}</label>
                <select id="c-category" name="category" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2">
                    @foreach(\App\Models\Complaint::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="c-details" class="block text-gray-700">{{ __('What happened?') }}</label>
                <textarea id="c-details" name="details" rows="5" required minlength="10" maxlength="3000" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2">{{ old('details') }}</textarea>
                @error('details')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="c-photos" class="block text-gray-700">{{ __('Photos (optional, up to 3)') }}</label>
                <input id="c-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 block w-full text-sm">
                @error('photos')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
                @error('photos.*')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 font-semibold text-white hover:bg-primary-hover">{{ __('Send complaint') }}</button>
        </form>
    @endif
</div>
@endsection
