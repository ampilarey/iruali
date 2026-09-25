@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.moderation._header', ['title' => 'Newsletter'])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between gap-3 mb-4">
            <p class="text-sm text-gray-600">{{ $subscribers->total() }} {{ \Illuminate\Support\Str::plural('subscriber', $subscribers->total()) }} from the footer signup.</p>
            <a href="{{ route('admin.newsletter', ['export' => 'csv']) }}" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700">Download CSV</a>
        </div>
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr><th class="text-start px-4 py-2 font-medium">Email</th><th class="text-start px-4 py-2 font-medium">Language</th><th class="text-start px-4 py-2 font-medium">Signed up</th><th class="px-4 py-2"><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($subscribers as $subscriber)
                        <tr>
                            <td class="px-4 py-2">{{ $subscriber->email }}</td>
                            <td class="px-4 py-2">{{ $subscriber->locale === 'dv' ? 'Dhivehi' : 'English' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $subscriber->created_at?->format('j M Y') }}</td>
                            <td class="px-4 py-2 text-end">
                                <form method="POST" action="{{ route('admin.newsletter.destroy', $subscriber) }}" onsubmit="return confirm('Remove this subscriber?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-700 hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">No subscribers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $subscribers->links() }}</div>
    </div>
</div>
@endsection
