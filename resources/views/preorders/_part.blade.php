{{-- A shop's part of an order with pre-order items: "Awaiting stock" (it can be prepared but not sent) until
     their stock arrives, with each item's date. On the customer's order page (with "Rather not wait?"), the
     guest order page, the shop's order page and the admin order page.
     Needs $part; $items (the part's order items) when already loaded; $for: customer (default), seller or admin;
     $class for the box. --}}
@php
    $for = $for ?? 'customer';
    $preorderItems = collect($items ?? $part->items()->with('product')->get())->filter(fn ($item) => $item->is_preorder)->values();
    $preorderService = app(\App\Services\PreorderService::class);
@endphp
@if($preorderItems->isNotEmpty() && $part->status !== 'cancelled')
    @php $waiting = $part->isAwaitingStock(); @endphp
    <div class="{{ $class ?? '' }} rounded-lg border px-3 py-2 text-sm {{ $waiting ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-green-200 bg-green-50 text-green-900' }}" data-preorder-part="{{ $waiting ? 'waiting' : 'in-stock' }}">
        <p class="font-semibold flex items-center gap-1.5"><x-icon :name="$waiting ? 'clock' : 'box'" class="w-4 h-4 shrink-0" />{{ $waiting ? __('Awaiting stock') : __('Pre-order stock is in') }}</p>
        <ul class="mt-1 ps-6 space-y-0.5">
            @foreach($preorderItems as $item)
                <li>{{ $item->displayName() }} × {{ $item->quantity }}: {{ $item->isPreorderWaiting() ? __('ships around :date', ['date' => $item->preorder_ship_date?->translatedFormat('j M') ?? '']) : __('in stock') }}@if($item->isPreorderWaiting() && $item->preorder_allocated_quantity > 0) ({{ __(':done of :total in', ['done' => $item->preorder_allocated_quantity, 'total' => $item->quantity]) }})@endif</li>
            @endforeach
        </ul>
        @if($waiting)
            <p class="mt-1 ps-6 text-xs">
                @switch($for)
                    @case('seller')
                        {{ __('You can prepare this part, but send it only once the stock is in: record your delivery under Pre-orders when it arrives.') }}
                        <a href="{{ route('seller.preorders') }}" class="font-semibold underline hover:no-underline">{{ __('Pre-orders') }}</a>
                        @break
                    @case('admin')
                        This part can't be marked sent until the shop records the stock (Seller Centre → Pre-orders).
                        @break
                    @default
                        {{ __('The shop sends these items when their stock arrives. We will email you when it does.') }}
                @endswitch
            </p>
        @endif
        @if($for === 'customer' && $preorderService->canCancel($part->order))
            <p class="mt-1 ps-6 text-xs"><a href="{{ $preorderService->cancelUrl($part->order) }}" class="font-semibold underline hover:no-underline" data-preorder-cancel>{{ __('Rather not wait? Cancel for a full refund') }}</a></p>
        @endif
    </div>
@endif
