@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-3 py-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-primary-600">Content</p>
                <h1 class="text-2xl font-bold text-gray-900">Legal pages</h1>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.legal.update') }}" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        @csrf
        @method('PUT')
        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow space-y-3 text-sm text-gray-600">
            <p>These are the pages Bank of Maldives checks before approving card payments. Each page already has iruali's own text, written for the marketplace. <strong>Leave a box empty to keep that text.</strong> Write in a box only to replace the whole page with your own wording (plain text; blank lines start new paragraphs).</p>
            <p>The business details at the top of every page come from <a href="{{ route('admin.settings') }}" class="text-primary-700 font-medium hover:underline">Settings → Business details</a>.</p>
            <div class="max-w-xs">
                <label for="legal_last_updated_date" class="block font-medium text-gray-700">"Last updated" date</label>
                <input id="legal_last_updated_date" name="legal_last_updated_date" placeholder="e.g. 25 September 2026" value="{{ old('legal_last_updated_date', $values['legal_last_updated_date']) }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                <p class="mt-1 text-xs text-gray-500">Shown on every policy page. Leave empty to hide it; update it whenever you change a policy.</p>
            </div>
        </section>

        @foreach($pages as $key => [$label, $route])
            <section class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between gap-3">
                    <label for="legal_{{ $key }}_body" class="text-lg font-semibold text-gray-900">{{ $label }}</label>
                    <a href="{{ route($route) }}" target="_blank" class="text-sm font-medium text-primary-700 hover:underline">View page ↗</a>
                </div>
                <p class="mt-1 text-xs {{ $values["legal_{$key}_body"] !== '' ? 'text-amber-700 font-semibold' : 'text-gray-500' }}">{{ $values["legal_{$key}_body"] !== '' ? 'Using your own text.' : 'Using iruali\'s built-in text.' }}</p>
                <textarea id="legal_{{ $key }}_body" name="legal_{{ $key }}_body" rows="{{ $values["legal_{$key}_body"] !== '' ? 16 : 4 }}" placeholder="Leave empty to use the built-in {{ strtolower($label) }}." class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">{{ old("legal_{$key}_body", $values["legal_{$key}_body"]) }}</textarea>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">Save legal pages</button>
        </div>
    </form>
</div>
@endsection
