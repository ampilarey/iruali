{{-- Admin → order: the buyer's business details, iruali's GST and each shop's invoice (gift-card orders have none) --}}
@php
    $giftCardOrder = $order->isGiftCardOrder();
    $paid = $order->payment_status === 'paid';
    $canOpenInvoices = \App\Support\StaffAccess::can('admin.orders.invoice');
@endphp
@unless($giftCardOrder)
    <div class="rounded-lg bg-white p-5 shadow text-sm" data-order-tax>
        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ __('Invoices and GST') }}</h2>
        @if($order->buyer_business_name)
            <div class="mt-2">
                <p class="text-xs text-gray-500">{{ __('Buying for a business') }}</p>
                <p class="font-medium text-gray-900">{{ $order->buyer_business_name }}</p>
                @if($order->buyer_tin)<p class="font-mono text-xs text-gray-700" dir="ltr">{{ $order->buyer_tin }}</p>@endif
                @if($order->buyer_business_address)<p class="text-xs text-gray-600">{{ $order->buyer_business_address }}</p>@endif
            </div>
        @endif
        @if($order->gst_platform_registered)
            <p class="mt-2 text-xs text-gray-600">{{ __('iruali GST-registered when placed (:rate%): GST in delivery :amount.', ['rate' => \App\Support\InvoiceText::rate($order->gst_rate), 'amount' => \App\Support\Money::format($order->delivery_gst)]) }}</p>
        @endif
        <ul class="mt-2 space-y-2">
            @foreach($order->sellerOrders as $taxPart)
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span class="font-medium text-gray-900">{{ $taxPart->shopName() }}</span>
                        <span class="block text-xs text-gray-500">
                            {{ $taxPart->gst_registered ? __('GST-registered when placed · GST :amount', ['amount' => \App\Support\Money::format($taxPart->gst_amount)]) : __('Not GST-registered when placed') }}
                        </span>
                    </span>
                    @if(! $paid)
                        <span class="text-xs text-gray-400">{{ __('Invoice once paid') }}</span>
                    @elseif($canOpenInvoices)
                        <a href="{{ route('admin.orders.invoice', [$order, $taxPart]) }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary-700 hover:underline">{{ $taxPart->gst_registered ? __('Tax invoice') : __('Receipt') }}@if($taxPart->invoice_number) <span dir="ltr">{{ $taxPart->invoice_number }}</span>@endif</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endunless
