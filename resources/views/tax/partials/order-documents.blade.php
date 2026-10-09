{{-- Each shop's invoice for a paid order (a tax invoice if the shop was GST-registered when the order was placed, else a receipt). Customer and guest order pages. --}}
@php
    $shopDocuments = $order->payment_status === 'paid' && ! $order->isGiftCardOrder() ? $order->sellerOrders : collect();
    $gstService = app(\App\Services\GstService::class);
@endphp
@if($shopDocuments->isNotEmpty())
    <div class="mt-4 rounded-lg border border-gray-200 p-3 text-sm" data-shop-invoices>
        <p class="font-semibold text-gray-900">{{ __('Invoices from the shops') }}</p>
        <ul class="mt-2 space-y-1.5">
            @foreach($shopDocuments as $documentPart)
                <li class="flex flex-wrap items-center justify-between gap-x-2">
                    <a href="{{ $gstService->customerInvoiceUrl($order, $documentPart) }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">{{ $documentPart->gst_registered ? __('Tax invoice') : __('Receipt') }} · {{ $documentPart->shopName() }}</a>
                    @if($documentPart->invoice_number)<span class="text-xs text-gray-500" dir="ltr">{{ $documentPart->invoice_number }}</span>@endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
