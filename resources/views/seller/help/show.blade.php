@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => $guide['title']])

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-[14rem_1fr]">
        <nav aria-label="{{ __('Help centre') }}" class="self-start lg:sticky lg:top-24">
            <a href="{{ route('seller.help') }}" class="block px-3 py-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ __('Help centre') }}</a>
            <ul class="flex lg:flex-col gap-1 overflow-x-auto scrollbar-hide text-sm">
                @foreach($guides as $otherSlug => $other)
                    <li class="shrink-0"><a href="{{ route('seller.help.show', $otherSlug) }}" class="block whitespace-nowrap rounded-lg px-3 py-2 {{ $otherSlug === $slug ? 'bg-primary-600 text-white font-semibold' : 'text-gray-700 hover:bg-white' }}">{{ $other['title'] }}</a></li>
                @endforeach
            </ul>
        </nav>

        <article dir="{{ $contentLocale === 'dv' ? 'rtl' : 'ltr' }}" class="min-w-0 rounded-lg bg-white p-6 sm:p-8 shadow text-gray-700 leading-relaxed space-y-4 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-gray-900 [&_h2]:pt-3 [&_ul]:list-disc [&_ul]:ps-6 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:ps-6 [&_ol]:space-y-1 [&_a]:text-primary-700 [&_a]:font-medium [&_a:hover]:underline [&_strong]:text-gray-900">
            <h1 class="text-2xl font-bold text-gray-900">{{ $guide['title'] }}</h1>
            <p class="text-sm text-gray-500">{{ $guide['summary'] }}</p>
            @include($content)
        </article>
    </div>
</div>
@endsection
