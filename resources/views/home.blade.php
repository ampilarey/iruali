@extends('layouts.app')

@section('content')
{{-- The home page is a list of blocks in the order set under Admin → Home page (App\Support\HomeLayout) --}}
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 pt-4 lg:pt-6 pb-10 space-y-8 lg:space-y-12">
        @if($previewingDraft ?? false)
            <p class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900" data-home-preview>Preview of the draft home page. <a href="{{ route('admin.home-page') }}" class="font-semibold underline">Back to the editor</a></p>
        @endif
        @foreach($homeBlocks as $block)
            @include('home.blocks.'.$block['type'], ['block' => $block])
        @endforeach
    </div>
</div>
@endsection
