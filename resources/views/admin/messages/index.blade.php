@extends('layouts.admin')

@php
    $kinds = ['order' => 'Orders', 'product' => 'Item questions', 'support' => 'Customer support', 'shop_support' => 'Shop support', 'item_request' => 'Item requests'];
@endphp

{{-- Admin → Messages: every conversation on iruali (MessagingService). Nothing is ever deleted. --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => 'Messages'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="max-w-3xl text-sm text-gray-600">Every conversation on iruali: orders, customers' questions about items (shops answer; customers see "iruali"), customer and shop support, and item requests. Open one to read it in full, including what was written where contact details were hidden, to reply as iruali support, close it or stop an account from writing. Conversations are never deleted.</p>

        <nav class="flex flex-wrap gap-2 text-sm">
            @foreach(['unread' => 'Unread', 'open' => 'Open', 'reported' => 'Reported', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.messages', array_filter(['show' => $key, 'kind' => $kind])) }}" class="rounded-full px-3 py-1.5 font-medium {{ $filter === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}">{{ $label }}{{ $key === 'unread' && $unread ? ' ('.$unread.')' : '' }}{{ $key === 'reported' && $reported ? ' ('.$reported.')' : '' }}</a>
            @endforeach
        </nav>
        <nav class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('admin.messages', ['show' => $filter]) }}" class="rounded-full px-3 py-1 {{ $kind === null ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 shadow' }}">All kinds</a>
            @foreach($kinds as $key => $label)
                <a href="{{ route('admin.messages', ['show' => $filter, 'kind' => $key]) }}" class="rounded-full px-3 py-1 {{ $kind === $key ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 shadow' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-left">Last message</th><th class="px-4 py-3 text-left">Kind</th><th class="px-4 py-3 text-left">About</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Shop</th><th class="px-4 py-3 text-left">Unread</th><th class="px-4 py-3 text-left">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($conversations as $c)
                            <tr class="hover:bg-gray-50 {{ $c->admin_unread_count ? 'font-semibold' : '' }}" data-admin-conversation="{{ $c->id }}">
                                <td class="px-4 py-3 text-gray-600"><a href="{{ route('admin.messages.show', $c) }}" class="text-primary-700 hover:underline">{{ $c->last_message_at?->format('d M Y, H:i') ?? '—' }}</a></td>
                                <td class="px-4 py-3">{{ $kinds[$c->kind] ?? $c->kind }}</td>
                                <td class="px-4 py-3 max-w-xs truncate">{{ $c->title() }}</td>
                                <td class="px-4 py-3">{{ $c->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $c->seller_id ? $c->shopName() : '—' }}</td>
                                <td class="px-4 py-3">@if($c->admin_unread_count)<span class="rounded-full bg-primary-600 px-2 py-0.5 text-xs text-white">{{ $c->admin_unread_count }}</span>@else—@endif</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $c->isOpen() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $c->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No conversations here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $conversations->links() }}</div>
        </div>
    </div>
</div>
@endsection
