@extends('policies.layout')

@php
    use App\Models\Setting;
    use App\Support\Company;
    use App\Support\Money;
    $male = (float) Setting::get('delivery_fee_greater_male');
    $islands = (float) Setting::get('delivery_fee_islands');
    $freeOver = (float) Setting::get('free_delivery_over');
@endphp

@section('policy_key', 'delivery')
@section('policy_title', __('Delivery Policy'))

@section('policy')
<p>{{ Company::tradingName() }} delivers to inhabited islands across the <strong>Maldives</strong>. We do not deliver outside the Maldives.</p>

<h2>1. Delivery fees</h2>
<ul>
    <li><strong>Greater Malé</strong> (Malé, Hulhumalé and Villimalé): {{ Money::format($male) }} per order.</li>
    <li><strong>Other islands:</strong> {{ Money::format($islands) }} per order.</li>
    @if($freeOver > 0)
        <li><strong>Free delivery</strong> on orders of {{ Money::format($freeOver) }} or more (after discounts).</li>
    @endif
</ul>
<p>The exact fee is shown at checkout before you place your order.</p>

<h2>2. Delivery times</h2>
<ul>
    <li>Shops usually prepare orders within 1–2 working days.</li>
    <li><strong>Greater Malé:</strong> usually 1–3 working days after the order is prepared.</li>
    <li><strong>Other islands:</strong> depends on the ferry, speedboat, cargo boat or flight schedule to your island, usually 3–10 working days.</li>
</ul>
<p>These are estimates, not guarantees. Weather, sea conditions and changes to boat or flight schedules can cause delays. We email you when your order is sent and when it is delivered, and you can follow it under <a href="{{ route('orders') }}">My Orders</a> or <a href="{{ route('order.track.form') }}">Track Order</a>.</p>

<h2>3. How your order is delivered</h2>
<ul>
    <li>Orders from different shops may arrive separately.</li>
    <li>For other islands, the order is sent by the shop's usual boat or cargo service and handed over at your island's jetty or delivered to your address, depending on the service. The shop or courier will call the phone number on your order to arrange it.</li>
    <li>Large, fragile or perishable items may need a delivery time agreed with you by phone.</li>
</ul>

<h2>4. Receiving your order</h2>
<ul>
    <li>Please give a correct address and a phone number we can reach. Someone must be available to receive the order.</li>
    <li>If a delivery fails because nobody could be reached or the address was wrong, we will contact you to arrange another delivery, which may have a further fee.</li>
    <li>Check your order when it arrives. Tell us within <strong>48 hours</strong> if anything is damaged or missing, with photos, so we can fix it under our <a href="{{ route('policies.refunds') }}">Returns, Refunds &amp; Cancellations Policy</a>.</li>
</ul>

<h2>5. Special conditions</h2>
<ul>
    <li>Cash on delivery orders must be paid in full to the delivery person.</li>
    <li>Items that need an age check or permit are only handed over after the check.</li>
    <li>No import or export customs duties apply, because all orders are delivered within the Maldives.</li>
</ul>
@endsection
