@extends('layouts.admin')

@section('title', 'Item request #'.$itemRequest->id)

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm';
    $answerable = in_array($itemRequest->status, ['new', 'quoted', 'accepted', 'declined'], true)
        || ($itemRequest->status === 'ordered' && $itemRequest->order?->status === 'cancelled');
    $defaultShop = old('seller_id', $itemRequest->seller_id ?? $shops->first(fn ($shop) => $shop->isStaff())?->id);
@endphp

{{-- One item request (admins only): what the customer asked for, the price form and declining (ItemRequestService). --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => 'Item request #'.$itemRequest->id, 'back' => route('admin.item-requests')])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow text-sm text-gray-700 space-y-2" data-admin-item-request-details>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-900">{{ $itemRequest->title }}</h2>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold">{{ $itemRequest->isExpired() ? 'Price expired' : ucfirst($itemRequest->status) }}</span>
            </div>
            @if($itemRequest->photo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($itemRequest->photo_path) }}" alt="" class="h-48 w-auto rounded-lg object-cover">
            @endif
            @if($itemRequest->link)<p>Link: <a href="{{ $itemRequest->link }}" target="_blank" rel="noopener nofollow" class="text-primary-700 hover:underline break-all">{{ $itemRequest->link }}</a></p>@endif
            @if($itemRequest->options)<p>Size, colour or model: {{ $itemRequest->options }}</p>@endif
            <p>Quantity: <strong>{{ $itemRequest->quantity }}</strong></p>
            @if($itemRequest->details)<p class="whitespace-pre-line">{{ $itemRequest->details }}</p>@endif
            <p>Deliver to: {{ $itemRequest->island?->englishName() ?? 'not given' }}@if($itemRequest->island?->atoll) ({{ $itemRequest->island->atoll }})@endif</p>
            @if($itemRequest->budget)<p>Budget: {{ \App\Support\Money::format($itemRequest->budget) }}</p>@endif
            @if($itemRequest->needed_by)<p>Needed by: {{ $itemRequest->needed_by->format('j M Y') }}</p>@endif
            <p>Customer: {{ $itemRequest->user?->name }} · {{ $itemRequest->user?->email }} · {{ $itemRequest->user?->phone }}</p>
            <p class="text-xs text-gray-500">Sent {{ $itemRequest->created_at->format('j M Y H:i') }}</p>
            @if($itemRequest->quoted_at)
                <p class="rounded bg-primary-50 p-3">Priced {{ $itemRequest->quoted_at->format('j M Y') }} by {{ $itemRequest->quotedBy?->name }}: <strong>{{ \App\Support\Money::format($itemRequest->total()) }}</strong> ({{ $itemRequest->quantity }} × {{ \App\Support\Money::format($itemRequest->unit_price) }}), {{ $itemRequest->deposit_percent ? $itemRequest->deposit_percent.'% booking fee' : 'full price' }}, ships in {{ $itemRequest->ships_in_days }} days, filled by {{ $itemRequest->seller?->shopName() }}, holds until {{ $itemRequest->valid_until?->format('j M Y') }}.</p>
            @endif
            @if($itemRequest->order)
                <p>Order: <a href="{{ route('admin.orders.show', $itemRequest->order) }}" class="font-semibold text-primary-700 hover:underline">{{ $itemRequest->order->order_number }}</a> ({{ $itemRequest->order->status }})</p>
            @endif
            @if($itemRequest->declined_reason)<p class="rounded bg-amber-50 p-3">Declined: {{ $itemRequest->declined_reason }}</p>@endif
        </section>

        @if($answerable)
            <form method="POST" action="{{ route('admin.item-requests.quote', $itemRequest) }}" class="rounded-lg bg-white p-6 shadow space-y-4 text-sm" data-admin-item-request-quote>
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">{{ $itemRequest->quoted_at ? 'Change the price' : 'Send a price' }}</h2>
                <p class="text-gray-600">The price is per item in MVR and must cover the item, shipping to the customer's island, customs duty and your margin. Sending it creates a pre-order only this customer can buy (never listed); the shop you choose fills it like any pre-order (Seller Centre → Pre-orders → "Stock arrived").</p>
                @if(count($rates) > 1)
                    <p class="rounded bg-gray-50 p-3 text-xs text-gray-600">Your rates (Settings → Pre-order imports): @foreach($rates as $code => $rate)@if($code !== 'MVR'){{ $code }} {{ $rate }}@if(! $loop->last) · @endif @endif @endforeach · shipping {{ \App\Support\Money::format((float) \App\Models\Setting::get('import_shipping_per_kg')) }}/kg · margin {{ (float) \App\Models\Setting::get('import_margin_percent') }}%</p>
                @endif
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="seller_id" class="block font-medium text-gray-700">Shop that fills it</label>
                        <select id="seller_id" name="seller_id" required class="{{ $field }}">
                            @forelse($shops as $shop)
                                <option value="{{ $shop->id }}" @selected((string) $defaultShop === (string) $shop->id)>{{ $shop->shopName() }}@if($shop->isStaff()) (iruali's own)@endif</option>
                            @empty
                                <option value="">No shop is allowed pre-orders yet</option>
                            @endforelse
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Only shops allowed pre-orders (Admin → Sellers).</p>
                    </div>
                    <div>
                        <label for="category_id" class="block font-medium text-gray-700">Department</label>
                        <select id="category_id" name="category_id" required class="{{ $field }}">
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $itemRequest->product?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="title" class="block font-medium text-gray-700">Name the customer sees</label>
                        <input id="title" name="title" maxlength="200" value="{{ old('title', $itemRequest->product?->getTranslation('name', 'en', false) ?: $itemRequest->title) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="unit_price" class="block font-medium text-gray-700">Price per item (MVR)</label>
                        <input id="unit_price" name="unit_price" type="number" step="0.01" min="1" required value="{{ old('unit_price', $itemRequest->unit_price) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="ships_in_days" class="block font-medium text-gray-700">Ships about … days after the order</label>
                        <input id="ships_in_days" name="ships_in_days" type="number" min="1" max="180" required value="{{ old('ships_in_days', $itemRequest->ships_in_days ?? \App\Services\ItemRequestService::DEFAULT_SHIPS_IN_DAYS) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <span class="block font-medium text-gray-700">The customer pays at checkout</span>
                        @php $payment = old('payment', $itemRequest->quoted_at && ! $itemRequest->deposit_percent ? 'full' : 'deposit'); @endphp
                        <label class="mt-1 flex items-center gap-2"><input type="radio" name="payment" value="deposit" @checked($payment === 'deposit')> A booking fee of <input name="deposit_percent" type="number" min="10" max="{{ \App\Services\BookingFeeService::MAX_PERCENT }}" value="{{ old('deposit_percent', $itemRequest->deposit_percent ?? \App\Services\ItemRequestService::DEFAULT_DEPOSIT) }}" class="w-16 rounded border border-gray-300 px-2 py-1">%, the rest when it arrives</label>
                        <label class="mt-1 flex items-center gap-2"><input type="radio" name="payment" value="full" @checked($payment === 'full')> The full price</label>
                    </div>
                    <div>
                        <label for="valid_days" class="block font-medium text-gray-700">Price holds for … days</label>
                        <input id="valid_days" name="valid_days" type="number" min="1" max="30" required value="{{ old('valid_days', \App\Services\ItemRequestService::DEFAULT_VALID_DAYS) }}" class="{{ $field }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="quote_message" class="block font-medium text-gray-700">Message to the customer (optional)</label>
                        <textarea id="quote_message" name="quote_message" rows="2" maxlength="1000" class="{{ $field }}">{{ old('quote_message', $itemRequest->quote_message) }}</textarea>
                    </div>
                </div>
                <button class="rounded-lg bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700" @disabled($shops->isEmpty())>Send price to the customer</button>
            </form>
        @endif

        @php
            $requestChat = \App\Models\Conversation::where('kind', 'item_request')->where('item_request_id', $itemRequest->id)->first()
                ?? new \App\Models\Conversation(['kind' => 'item_request', 'item_request_id' => $itemRequest->id, 'customer_id' => $itemRequest->user_id, 'status' => 'open']);
        @endphp
        <section class="space-y-2" data-admin-item-request-messages>
            <h2 class="text-lg font-semibold text-gray-900">Messages with the customer</h2>
            @include('messaging._chat', ['conversation' => $requestChat, 'role' => 'admin', 'action' => route('admin.item-requests.messages', $itemRequest)])
        </section>

        @if($itemRequest->canCancel())
            <form method="POST" action="{{ route('admin.item-requests.decline', $itemRequest) }}" class="rounded-lg bg-white p-6 shadow space-y-3 text-sm" data-admin-item-request-decline>
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Decline</h2>
                <label for="reason" class="block font-medium text-gray-700">Reason the customer sees</label>
                <textarea id="reason" name="reason" rows="2" maxlength="500" required placeholder="e.g. This item can't be shipped to the Maldives." class="{{ $field }}">{{ old('reason') }}</textarea>
                <button class="rounded-lg border border-red-300 px-4 py-2 font-medium text-red-700 hover:bg-red-50">Decline the request</button>
            </form>
        @endif
    </div>
</div>
@endsection
