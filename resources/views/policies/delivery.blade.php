@extends('policies.layout')

@php
    use App\Models\Setting;
    use App\Support\Company;
    use App\Support\Money;
    $male = (float) Setting::get('delivery_fee_greater_male');
    $islands = (float) Setting::get('delivery_fee_islands');
    $freeOver = (float) Setting::get('free_delivery_over');
    $shopFees = \App\Models\SellerDeliverySetting::ownFeesAllowed();
@endphp

@section('policy_key', 'delivery')
@section('policy_title', __('Delivery Policy'))

@section('policy')
<p>{{ Company::tradingName() }} delivers to inhabited islands across the <strong>Maldives</strong>. We do not deliver outside the Maldives.</p>

<h2>1. Delivery fees</h2>
<ul>
    <li><strong>Greater Malé</strong> (Malé, Hulhumalé and Villimalé): {{ Money::format($male) }} per order.</li>
    <li><strong>Other islands:</strong> {{ Money::format($islands) }} per order.</li>
    @include('policies._delivery_rates')
    @if($freeOver > 0)
        <li><strong>Free delivery</strong> on orders of {{ Money::format($freeOver) }} or more (after discounts).</li>
    @endif
</ul>
@if($shopFees)
    <p>Some items have their own delivery fee, up to {{ Money::format(\App\Models\SellerDeliverySetting::maxFee()) }}, shown on the product page and at checkout. It is charged for those items instead of the fees above; the fees above are charged once for the rest of your order.</p>
@endif
<ul>
    <li><strong>Bulky items:</strong> some large items carry an extra delivery charge per item, shown on the product page. Free delivery does not cover it.</li>
    <li><strong>Pick up:</strong> where it is offered for an item, you can collect it instead, with no delivery fee.</li>
</ul>
<p>The exact fee is shown at checkout before you place your order.</p>

<h2>2. Delivery times</h2>
<ul>
    <li>Each product page shows how quickly the item is usually sent; most orders are prepared within 1–2 working days.</li>
    <li><strong>Greater Malé:</strong> usually 1–3 working days after the order is prepared.</li>
    <li><strong>Other islands:</strong> depends on the ferry, speedboat, cargo boat or flight schedule to your island, usually 3–10 working days.</li>
</ul>
<p>These are estimates, not guarantees. Weather, sea conditions and changes to boat or flight schedules can cause delays. We email you when your order is sent and when it is delivered, and you can follow it under <a href="{{ route('orders') }}">My Orders</a> or <a href="{{ route('order.track.form') }}">Track Order</a>.</p>

<p><strong>Pre-orders</strong> are sent once the item has reached us (and, for a booking fee or "pay once we confirm", once it is paid in full), so they arrive around the date shown on the product page plus the delivery time above. See <a href="{{ route('policies.terms') }}">Terms &amp; Conditions</a>, section 5.</p>

<h2>3. How your order is delivered</h2>
<ul>
    <li>An order may arrive in more than one parcel.</li>
    <li>Not every item can be delivered to every island. Choose your island on the site to see what can reach you; checkout tells you if an item can't be delivered to your address.</li>
    <li>For other islands, the order is sent by boat or cargo service and handed over at your island's jetty or delivered to your address, depending on the service. We or the courier will call the phone number on your order to arrange it.</li>
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
    @if(\App\Services\ManualPaymentService::codEnabled())
        <li>Orders are paid before they are sent, except cash on delivery orders, where you pay the delivery person in cash. Delivery staff never ask for payment on any other order.</li>
    @else
        <li>All orders are paid when placed. Delivery staff never ask for payment.</li>
    @endif
    <li>Items that need an age check or permit are only handed over after the check.</li>
    <li>No import or export customs duties apply, because all orders are delivered within the Maldives.</li>
</ul>
@endsection
