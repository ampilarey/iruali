@extends('layouts.admin')

@section('title', $customer->name)

@section('content')
@php
    $isAdmin = auth()->user()->hasRole('admin');
    $notCustomer = $accounts->notACustomer($customer);
    $canEdit = \App\Support\StaffAccess::can('admin.customers.tags');
@endphp
<div class="max-w-6xl mx-auto py-6 px-4 space-y-4">
    <div>
        <a href="{{ route('admin.users') }}" class="text-sm text-primary-700 hover:underline">← Users</a>
        <h1 class="mt-1 text-xl font-bold lg:text-2xl">{{ $customer->name }}</h1>
        <p class="text-sm text-gray-600 break-all">{{ $customer->email }}@if($customer->phone) · {{ $customer->phone }} {{ $customer->phone_verified_at ? '(verified)' : '(not verified)' }}@endif</p>
        <p class="text-xs text-gray-500">Joined {{ $customer->created_at?->format('j M Y') }}
            @if($customer->erased_at) · <span class="font-semibold text-red-700">Erased {{ $customer->erased_at->format('j M Y') }}</span>@endif
            @if($customer->merged_into_id) · <a href="{{ route('admin.customers.show', $customer->merged_into_id) }}" class="font-semibold text-amber-700 hover:underline">Merged into another account</a>@endif
            @if(! $customer->is_active && ! $customer->erased_at && ! $customer->merged_into_id) · <span class="text-red-700">Inactive</span>@endif
        </p>
        @if($customer->staff_tags)
            <div class="mt-2 flex flex-wrap gap-1">@foreach($customer->staff_tags as $tag)<span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">{{ $tag }}</span>@endforeach</div>
        @endif
    </div>

    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([
            ['Orders', number_format($stats['orders'])],
            ['Spent (paid)', 'MVR '.number_format($stats['spent'], 2)],
            ['Average order', 'MVR '.number_format($stats['average'], 2)],
            ['Points / wallet', number_format((int) $customer->loyalty_points).' / MVR '.number_format((float) $customer->wallet_balance, 2)],
        ] as [$label, $value])
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500">
        @if($stats['first'])First order {{ $stats['first']->format('j M Y') }}, last {{ $stats['last']->format('j M Y') }}.@else No orders yet.@endif
        @if($stats['tier']) Tier: {{ $stats['tier'] }}.@endif
    </p>

    <x-admin.fold title="Orders" :open="true" flush>
        <x-slot:badge><span class="text-xs text-gray-500">{{ $stats['orders'] }}</span></x-slot:badge>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Order</th><th class="p-3 text-start">Date</th><th class="p-3 text-start">Status</th><th class="p-3 text-end">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                        <tr>
                            <td class="p-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-primary-700 hover:underline">{{ $order->order_number }}</a></td>
                            <td class="p-3 whitespace-nowrap">{{ $order->created_at->format('j M Y') }}</td>
                            <td class="p-3">{{ str_replace('_', ' ', $order->status) }} · {{ $order->payment_status }}</td>
                            <td class="p-3 text-end whitespace-nowrap">MVR {{ number_format((float) $order->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-3 text-gray-500">No orders.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($stats['orders'] > $orders->count())<p class="p-3 text-xs text-gray-500">Showing the latest {{ $orders->count() }}.</p>@endif
        </div>
    </x-admin.fold>

    <x-admin.fold title="Returns, disputes and quotes" body="space-y-4 text-sm">
        <x-slot:badge><span class="text-xs text-gray-500">{{ $returns->count() + $disputes->count() + $quotes->count() }}</span></x-slot:badge>
        <div>
            <h3 class="font-semibold">Returns</h3>
            <ul class="mt-1 space-y-1">
                @forelse($returns as $return)
                    <li><a href="{{ route('admin.returns.show', $return) }}" class="text-primary-700 hover:underline">Return #{{ $return->id }}</a> · {{ $return->statusLabel() }} · {{ $return->created_at->format('j M Y') }}</li>
                @empty<li class="text-gray-500">None.</li>@endforelse
            </ul>
        </div>
        <div>
            <h3 class="font-semibold">Disputes</h3>
            <ul class="mt-1 space-y-1">
                @forelse($disputes as $dispute)
                    <li><a href="{{ route('admin.disputes.show', $dispute) }}" class="text-primary-700 hover:underline">Dispute #{{ $dispute->id }}</a> · {{ $dispute->statusLabel() }} · {{ $dispute->created_at->format('j M Y') }}</li>
                @empty<li class="text-gray-500">None.</li>@endforelse
            </ul>
        </div>
        <div>
            <h3 class="font-semibold">Bulk quotes</h3>
            <ul class="mt-1 space-y-1">
                @forelse($quotes as $quote)
                    <li><a href="{{ route('admin.quotes.show', $quote) }}" class="text-primary-700 hover:underline">Quote request #{{ $quote->id }}</a> · {{ $quote->product_name }} · {{ $quote->status instanceof \BackedEnum ? $quote->status->value : $quote->status }}</li>
                @empty<li class="text-gray-500">None.</li>@endforelse
            </ul>
        </div>
    </x-admin.fold>

    <x-admin.fold title="Messages" body="text-sm">
        <x-slot:badge><span class="text-xs text-gray-500">{{ $conversations->count() }}</span></x-slot:badge>
        <ul class="space-y-1">
            @forelse($conversations as $conversation)
                <li>
                    @if($conversation->order_id)<a href="{{ route('admin.orders.show', $conversation->order_id) }}" class="text-primary-700 hover:underline">Order conversation #{{ $conversation->id }}</a>@else Conversation #{{ $conversation->id }}@endif
                    · {{ $conversation->status }} · {{ $conversation->last_message_at?->format('j M Y') }}
                </li>
            @empty<li class="text-gray-500">No messages.</li>@endforelse
        </ul>
    </x-admin.fold>

    <x-admin.fold title="Points" flush>
        <x-slot:badge><span class="text-xs text-gray-500">{{ number_format((int) $customer->loyalty_points) }}</span></x-slot:badge>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @forelse($points as $entry)
                    <tr><td class="p-3 whitespace-nowrap">{{ $entry->created_at?->format('j M Y') }}</td><td class="p-3">{{ $entry->type }}@if($entry->note) · {{ $entry->note }}@endif</td><td class="p-3 text-end">{{ $entry->points > 0 ? '+' : '' }}{{ number_format($entry->points) }}</td></tr>
                @empty
                    <tr><td class="p-3 text-gray-500">No points yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.fold>

    <x-admin.fold title="Tags and private notes" body="space-y-4 text-sm" id="notes">
        <x-slot:badge><span class="text-xs text-gray-500">{{ $notes->count() }}</span></x-slot:badge>
        <p class="text-xs text-gray-500">Only staff see these, never the customer.</p>
        @if($canEdit)
            <form method="POST" action="{{ route('admin.customers.tags', $customer) }}" class="flex flex-wrap items-end gap-2">
                @csrf @method('PUT')
                <div class="min-w-0 flex-1"><label for="tags" class="block text-xs font-medium text-gray-700">Tags, separated by commas</label>
                    <input id="tags" name="tags" maxlength="300" value="{{ old('tags', implode(', ', (array) $customer->staff_tags)) }}" placeholder="VIP, business, late payer" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></div>
                <button class="rounded-lg border border-gray-300 px-4 py-2 font-semibold hover:bg-gray-50">Save tags</button>
            </form>
            <form method="POST" action="{{ route('admin.customers.notes', $customer) }}" class="space-y-2">
                @csrf
                <label for="note-body" class="block text-xs font-medium text-gray-700">Add a note</label>
                <textarea id="note-body" name="body" rows="2" maxlength="2000" required class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('body') }}</textarea>
                @error('body')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Add note</button>
            </form>
        @endif
        <ul class="space-y-2">
            @forelse($notes as $note)
                <li class="rounded-lg bg-gray-50 p-3"><p class="whitespace-pre-line">{{ $note->body }}</p><p class="mt-1 text-xs text-gray-500">{{ $note->author?->name ?? 'Staff' }} · {{ $note->created_at->format('j M Y H:i') }}</p></li>
            @empty<li class="text-gray-500">No notes.</li>@endforelse
        </ul>
    </x-admin.fold>

    @if($isAdmin)
        <x-admin.fold title="Change phone" body="space-y-2 text-sm" id="phone">
            <form method="POST" action="{{ route('admin.customers.phone', $customer) }}" class="flex flex-wrap items-end gap-2">
                @csrf @method('PUT')
                <div class="min-w-0 flex-1"><label for="phone-input" class="block text-xs font-medium text-gray-700">New phone (empty to remove)</label>
                    <input id="phone-input" name="phone" value="{{ old('phone', $customer->phone) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('phone')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <button class="rounded-lg border border-gray-300 px-4 py-2 font-semibold hover:bg-gray-50">Change</button>
            </form>
            <p class="text-xs text-gray-500">The new number has to be verified by the customer before codes go to it. The audit log keeps the old one.</p>
        </x-admin.fold>

        <x-admin.fold title="Pay later (business invoice)" body="space-y-3 text-sm" id="pay-later">
            <x-slot:badge>@if($credit)<span class="text-xs {{ $credit->isActive() ? 'text-green-700' : 'text-gray-500' }}">{{ $credit->isActive() ? 'On' : 'Suspended' }}</span>@endif</x-slot:badge>
            <p class="text-xs text-gray-500">An approved business orders now and pays the invoice later. Orders go to the shops straight away; shops are paid once the invoice is paid. Record a payment on the order (Payment → Confirm payment).</p>
            <form method="POST" action="{{ route('admin.customers.credit', $customer) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf @method('PUT')
                <div><label for="credit_limit" class="block text-xs font-medium text-gray-700">Credit limit (MVR)</label>
                    <input id="credit_limit" name="credit_limit" type="number" min="0" step="0.01" required value="{{ old('credit_limit', $credit?->credit_limit) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></div>
                <div><label for="terms_days" class="block text-xs font-medium text-gray-700">Days to pay</label>
                    <input id="terms_days" name="terms_days" type="number" min="1" max="120" required value="{{ old('terms_days', $credit?->terms_days ?? 30) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></div>
                <div><label for="credit_status" class="block text-xs font-medium text-gray-700">Status</label>
                    <select id="credit_status" name="status" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="active" @selected(($credit?->status ?? 'active') === 'active')>On</option>
                        <option value="suspended" @selected($credit?->status === 'suspended')>Suspended</option>
                    </select></div>
                <div><label for="credit_note" class="block text-xs font-medium text-gray-700">Note</label>
                    <input id="credit_note" name="note" maxlength="500" value="{{ old('note', $credit?->note) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></div>
                <div class="sm:col-span-4"><button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Save</button></div>
            </form>
            @if($invoices->isNotEmpty())
                <table class="w-full text-sm">
                    <thead><tr class="text-xs uppercase text-gray-500"><th class="py-1 text-start">Invoice</th><th class="py-1 text-start">Due</th><th class="py-1 text-end">Amount</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($invoices as $invoice)
                            <tr @class(['text-red-700' => $invoice->credit_due_on?->isPast()])>
                                <td class="py-1"><a href="{{ route('admin.orders.show', $invoice) }}" class="font-mono hover:underline">{{ $invoice->order_number }}</a></td>
                                <td class="py-1">{{ $invoice->credit_due_on?->format('j M Y') }}@if($invoice->credit_due_on?->isPast()) (overdue)@endif</td>
                                <td class="py-1 text-end">MVR {{ number_format((float) $invoice->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-gray-500">Owed: MVR {{ number_format((float) $invoices->sum('total_amount'), 2) }}@if($credit) of MVR {{ number_format((float) $credit->credit_limit, 2) }}@endif.</p>
            @endif
        </x-admin.fold>

        <x-admin.fold title="Merge a duplicate account" body="space-y-2 text-sm" id="merge">
            @if($notCustomer)
                <p class="text-gray-600">{{ $notCustomer }} Only customer accounts can be merged.</p>
            @else
                <p class="text-xs text-gray-500">Moves this account's orders, addresses, points, wallet, reviews, messages and everything else into the account below, then closes this one. It cannot be undone.</p>
                <form method="POST" action="{{ route('admin.customers.merge', $customer) }}" class="space-y-2">
                    @csrf
                    <label for="merge-into" class="block text-xs font-medium text-gray-700">Email of the account to keep</label>
                    <input id="merge-into" name="into" type="email" required value="{{ old('into') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('into')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    <label class="flex items-start gap-2"><input type="checkbox" name="confirm" value="1" required class="mt-1"> <span>Yes, move everything and close {{ $customer->email }}</span></label>
                    <button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-700">Merge</button>
                </form>
            @endif
        </x-admin.fold>

        <x-admin.fold title="Erase personal data" body="space-y-2 text-sm" id="erase">
            @if($problem = $accounts->eraseProblem($customer))
                <p class="text-gray-600">{{ $problem }}</p>
            @else
                <p class="text-xs text-gray-500">Removes the name, email, phone, addresses, saved lists and sign-ins. Orders and invoices stay for the records, without the customer's details. It cannot be undone.</p>
                <form method="POST" action="{{ route('admin.customers.erase', $customer) }}" class="space-y-2">
                    @csrf
                    <label for="erase-reason" class="block text-xs font-medium text-gray-700">Reason</label>
                    <input id="erase-reason" name="reason" required maxlength="200" value="{{ old('reason') }}" placeholder="Customer asked by email on …" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('reason')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    <label class="flex items-start gap-2"><input type="checkbox" name="confirm" value="1" required class="mt-1"> <span>Yes, erase this customer's personal data</span></label>
                    <button class="rounded-lg bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700">Erase</button>
                </form>
            @endif
        </x-admin.fold>
    @endif
</div>
@endsection
