{{-- Delivery policy, fees list: atolls with their own fee, bulky-item charges and pickup (Admin → Delivery). --}}
@foreach(app(\App\Services\DeliveryService::class)->atollFees() as $atoll => $fee)
    <li><strong>{{ $atoll }}:</strong> {{ __(':amount per order.', ['amount' => \App\Support\Money::format($fee)]) }}</li>
@endforeach
<li>{{ __('Large or heavy items can have an extra delivery charge per item, shown on the product page. Free delivery does not cover it.') }}</li>
<li>{{ __('Some shops let you collect your order from them instead, with no delivery fee. You choose at checkout.') }}</li>
