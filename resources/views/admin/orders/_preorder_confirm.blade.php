{{-- Admin order page → Payment: a pre-order paid once the shop confirms it can get the item
     (App\Services\PreorderConfirmService). Where it stands, and confirm or decline it for the shop. Needs $order. --}}
@if($order->confirm_first)
    @php $money = fn ($amount) => \App\Support\Money::format($amount); @endphp
    <div class="mt-3 space-y-2 rounded-md border border-primary-200 bg-primary-50 p-3 text-sm" data-admin-confirm-first>
        <p class="font-semibold text-gray-900">Pre-order paid once the shop confirms</p>
        <p class="text-xs text-gray-700">
            @if($order->availability_declined_at)
                The shop couldn't get the item: cancelled {{ $order->availability_declined_at->format('d M Y, H:i') }}. Nothing was paid.
            @elseif($order->awaitingConfirmation())
                Waiting for the shop to confirm it can get the item (ordered {{ $order->created_at->format('d M Y, H:i') }}). Nothing can be paid until then; it is never cancelled for being unpaid meanwhile.
            @elseif($order->availability_confirmed_at)
                Confirmed {{ $order->availability_confirmed_at->format('d M Y, H:i') }}.
                @if($order->payment_status === 'paid') Paid {{ $order->paid_at?->format('d M Y, H:i') }}.
                @elseif($order->status !== 'cancelled') The customer was sent a link to pay <span dir="ltr">{{ $money($order->cardAmount()) }}</span> by {{ $order->pay_due_on?->format('d M Y') }}; unpaid after that, the order is cancelled. "Send payment link" below gives them 48 more hours.
                @endif
            @endif
        </p>
        @if($order->awaitingConfirmation() && \App\Support\StaffAccess::can('admin.orders.preorder-confirm'))
            @include('preorders._confirm_actions', ['confirmUrl' => route('admin.orders.preorder-confirm', $order), 'declineUrl' => route('admin.orders.preorder-decline', $order)])
        @endif
    </div>
@endif
