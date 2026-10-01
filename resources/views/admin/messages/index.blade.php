@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Messages'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <nav class="flex flex-wrap gap-2 text-sm">
            @foreach(['unread' => 'Unread', 'open' => 'Open', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.messages', ['show' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $filter === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}">{{ $label }}@if($key === 'unread' && $unread) ({{ $unread }})@endif</a>
            @endforeach
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-left">Last message</th><th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Shop</th><th class="px-4 py-3 text-left">Unread</th><th class="px-4 py-3 text-left">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($conversations as $c)
                            <tr class="hover:bg-gray-50 {{ $c->admin_unread_count ? 'font-semibold' : '' }}">
                                <td class="px-4 py-3 text-gray-600"><a href="{{ route('admin.orders.show', $c->order) }}#conversation-{{ $c->id }}" class="text-primary-700 hover:underline">{{ $c->last_message_at?->format('d M Y, H:i') ?? '—' }}</a></td>
                                <td class="px-4 py-3">#{{ $c->order?->order_number }}</td>
                                <td class="px-4 py-3">{{ $c->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $c->shopName() }}</td>
                                <td class="px-4 py-3">@if($c->admin_unread_count)<span class="rounded-full bg-primary-600 px-2 py-0.5 text-xs text-white">{{ $c->admin_unread_count }}</span>@else—@endif</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $c->isOpen() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $c->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No conversations here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $conversations->links() }}</div>
        </div>
    </div>
</div>
@endsection
