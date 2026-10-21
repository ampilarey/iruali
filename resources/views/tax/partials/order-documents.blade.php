{{-- Each shop's invoice for a paid order (a tax invoice if the shop was GST-registered when the order was placed, else a receipt),
     and a pre-order's booking fee invoice from when the fee is paid. Customer and guest order pages. --}}
@php
    $shopDocuments = $order->payment_status === 'paid' && ! $order->isGiftCardOrder() ? $order->sellerOrders : collect();
    $feeDocuments = $order->hasBookingFee() ? $order->sellerOrders->whereNotNull('deposit_invoice_number') : collect();
    $gstService = app(\App\Services\GstService::class);
@endphp
@if($shopDocuments->isNotEmpty() || $feeDocuments->isNotEmpty())
    <div class="mt-4 rounded-lg border border-gray-200 p-3 text-sm" data-shop-invoices>
        <p class="font-semibold text-gray-900">{{ __('Invoices from the shops') }}</p>
        <ul class="mt-2 space-y-1.5">
            @foreach($feeDocuments as $documentPart)
                <li class="flex flex-wrap items-center justify-between gap-x-2" data-booking-fee-invoice-link>
                    <a href="{{ $gstService->customerDepositInvoiceUrl($order, $documentPart) }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">{{ $documentPart->gst_registered ? __('Tax invoice for the booking fee') : __('Receipt for the booking fee') }} · {{ $documentPart->shopName() }}</a>
                    <span class="text-xs text-gray-500" dir="ltr">{{ $documentPart->deposit_invoice_number }}</span>
                </li>
            @endforeach
            @foreach($shopDocuments as $documentPart)
                <li class="flex flex-wrap items-center justify-between gap-x-2">
                    <a href="{{ $gstService->customerInvoiceUrl($order, $documentPart) }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">{{ $documentPart->gst_registered ? __('Tax invoice') : __('Receipt') }} · {{ $documentPart->shopName() }}</a>
                    @if($documentPart->invoice_number)<span class="text-xs text-gray-500" dir="ltr">{{ $documentPart->invoice_number }}</span>@endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
