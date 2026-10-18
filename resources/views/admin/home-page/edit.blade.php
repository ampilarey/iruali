@extends('layouts.admin')

@section('title', 'Home page')

@section('content')
<div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
    <div>
        <h1 class="text-xl font-bold lg:text-2xl">Home page</h1>
        <p class="text-sm text-gray-500">Arrange the home page: move blocks, hide them, add product rows and text. Changes go to a draft; preview it, then publish.</p>
    </div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <div class="flex flex-wrap items-center gap-2 rounded-lg bg-white p-4 shadow text-sm" data-home-draft="{{ $hasDraft ? 'yes' : 'no' }}">
        <span class="me-auto">{{ $hasDraft ? 'You have unpublished changes.' : 'The draft is the same as the live home page.' }}</span>
        <a href="{{ route('home', ['preview' => 1]) }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-1.5 font-semibold hover:bg-gray-50">Preview draft</a>
        @if($hasDraft)
            <form method="POST" action="{{ route('admin.home-page.publish') }}">@csrf<input type="hidden" name="action" value="discard"><button class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Discard</button></form>
            <form method="POST" action="{{ route('admin.home-page.publish') }}">@csrf<input type="hidden" name="action" value="publish"><button class="rounded-lg bg-primary-600 px-3 py-1.5 font-semibold text-white hover:bg-primary-700">Publish</button></form>
        @endif
    </div>

    <ol class="space-y-2">
        @foreach($blocks as $block)
            @php
                $custom = isset(\App\Support\HomeLayout::CUSTOM[$block['type']]);
                $label = \App\Support\HomeLayout::BUILT_IN[$block['type']] ?? (\App\Support\HomeLayout::CUSTOM[$block['type']] ?? $block['type']);
                $visible = $block['visible'] ?? true;
            @endphp
            <li id="block-{{ $block['id'] }}" @class(['rounded-lg bg-white p-3 shadow text-sm', 'opacity-60' => ! $visible]) data-home-block="{{ $block['id'] }}">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="me-auto font-medium">{{ $label }}@if($custom && ! empty($block['title_en'])): {{ $block['title_en'] }}@endif @unless($visible)<span class="text-xs text-gray-500">(hidden)</span>@endunless</span>
                    @foreach(['up' => '↑', 'down' => '↓', 'toggle' => $visible ? 'Hide' : 'Show'] + ($custom ? ['remove' => 'Remove'] : []) as $action => $text)
                        <form method="POST" action="{{ route('admin.home-page.move', $block['id']) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" aria-label="{{ ucfirst($action) }} {{ $label }}">{{ $text }}</button></form>
                    @endforeach
                </div>
                @if($custom)
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs text-primary-700">Edit</summary>
                        @include('admin.home-page._form', ['action' => route('admin.home-page.update', $block['id']), 'method' => 'PUT', 'values' => $block])
                    </details>
                @endif
            </li>
        @endforeach
    </ol>

    <x-admin.fold title="Add a block" body="text-sm" :open="$errors->any()" id="home-add">
        @include('admin.home-page._form', ['action' => route('admin.home-page.store'), 'method' => 'POST', 'values' => ['type' => 'product_row']])
    </x-admin.fold>
</div>
@endsection
