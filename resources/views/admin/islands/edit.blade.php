@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    <div class="bg-white shadow">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-3 lg:py-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-primary-600">Islands</p>
                <h1 class="text-xl font-bold text-gray-900 lg:text-2xl">Edit {{ $island->englishName() }}</h1>
            </div>
            <a href="{{ route('admin.islands') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to islands</a>
        </div>
    </div>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <form method="POST" action="{{ route('admin.islands.update', $island) }}" class="space-y-4 rounded-lg bg-white p-5 shadow" data-island-edit>
            @csrf
            @method('PUT')
            @include('admin.islands._form', ['island' => $island])
            <p class="text-xs text-gray-500">Addresses and orders keep the name they were saved with. Shops based on this island stay on it.</p>
            <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Save island</button>
        </form>
    </div>
</div>
@endsection
