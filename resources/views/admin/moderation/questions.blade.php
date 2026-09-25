@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.moderation._header', ['title' => 'Questions'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex gap-2 mb-4 text-sm">
            <a href="{{ route('admin.questions') }}" class="px-3 py-1.5 rounded-full {{ request('show') !== 'all' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200' }}">Waiting for an answer ({{ $unanswered }})</a>
            <a href="{{ route('admin.questions', ['show' => 'all']) }}" class="px-3 py-1.5 rounded-full {{ request('show') === 'all' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200' }}">All questions</a>
        </div>
        <p class="text-sm text-gray-600 mb-4">Shops answer their own questions from Seller Centre. You can answer for them here, or delete questions that shouldn't be on the site.</p>

        @forelse($questions as $question)
            <div class="bg-white shadow rounded-lg p-4 mb-3">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs text-gray-500">
                        @if($question->product)<a href="{{ route('products.show', $question->product) }}#questions" class="font-semibold text-primary-700 hover:underline">{{ $question->product->name }}</a> &middot;@endif
                        {{ $question->user?->name ?? 'Customer' }} &middot; {{ $question->created_at->diffForHumans() }}
                    </p>
                    <form method="POST" action="{{ route('admin.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question?')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-700 hover:underline">Delete</button>
                    </form>
                </div>
                <p class="mt-1 font-medium text-gray-900">{{ $question->question }}</p>
                <form method="POST" action="{{ route('questions.answer', $question) }}" class="mt-3 flex gap-2">
                    @csrf
                    <label for="answer-{{ $question->id }}" class="sr-only">Answer</label>
                    <input id="answer-{{ $question->id }}" name="answer" required value="{{ $question->answer }}" placeholder="Answer as iruali" class="flex-1 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <button class="px-4 rounded-lg bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700">{{ $question->answer ? 'Update' : 'Answer' }}</button>
                </form>
                @if($question->answered_at)<p class="mt-1 text-xs text-gray-500">Answered by {{ $question->answerer?->name ?? 'someone' }} {{ $question->answered_at->diffForHumans() }}</p>@endif
            </div>
        @empty
            <div class="bg-white shadow rounded-lg p-10 text-center text-gray-500">No questions here.</div>
        @endforelse
        <div class="mt-4">{{ $questions->links() }}</div>
    </div>
</div>
@endsection
