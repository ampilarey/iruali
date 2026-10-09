{{-- Help centre, delivery fees table: atolls with their own fee (Admin → Delivery). --}}
@foreach(app(\App\Services\DeliveryService::class)->atollFees() as $atoll => $fee)
    <tr><th scope="row" class="text-start font-medium text-gray-600 py-2">{{ $atoll }}</th><td class="text-end font-semibold">{{ \App\Support\Money::format($fee) }}</td></tr>
@endforeach
