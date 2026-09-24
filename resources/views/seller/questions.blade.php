@extends('layouts.app')

@section('content')
@include('seller.partials.header', ['title' => 'Customer questions'])

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
    <div class="flex gap-2 mb-4 text-sm">
        <a href="{{ route('seller.questions') }}" class="px-3 py-1.5 rounded-full {{ request('show') !== 'all' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200' }}">Waiting for an answer ({{ $unanswered }})</a>
        <a href="{{ route('seller.questions', ['show' => 'all']) }}" class="px-3 py-1.5 rounded-full {{ request('show') === 'all' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200' }}">All questions</a>
    </div>

    @forelse($questions as $question)
        <div class="bg-white rounded-lg shadow p-5 mb-3">
            <p class="text-xs text-gray-500">
                <a href="{{ route('products.show', $question->product) }}" class="font-semibold text-primary-700 hover:underline">{{ $question->product->name }}</a>
                &middot; {{ $question->user?->name ?? 'Customer' }} &middot; {{ $question->created_at->diffForHumans() }}
            </p>
            <p class="mt-1 font-medium text-gray-900">{{ $question->question }}</p>
            <form action="{{ route('questions.answer', $question) }}" method="POST" class="mt-3">
                @csrf
                <label for="answer-{{ $question->id }}" class="sr-only">Answer</label>
                <textarea id="answer-{{ $question->id }}" name="answer" rows="2" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Write an answer customers will see on the product page">{{ old('answer', $question->answer) }}</textarea>
                <div class="mt-2 flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700">{{ $question->answer ? 'Update answer' : 'Publish answer' }}</button>
                    @if($question->answered_at)<span class="text-xs text-gray-500">Answered {{ $question->answered_at->diffForHumans() }}</span>@endif
                </div>
            </form>
        </div>
    @empty
        <div class="bg-white rounded-lg shadow p-10 text-center text-gray-500">No questions here.</div>
    @endforelse

    <div class="mt-4">{{ $questions->links() }}</div>
</div>
@endsection
