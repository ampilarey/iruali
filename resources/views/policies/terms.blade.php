@extends('policies.layout')

@php
    use App\Models\Setting;
    use App\Support\Company;
    use App\Support\Money;
    $name = Company::tradingName();
    $operator = Company::legalName() ?? $name;
    $days = Company::returnWindowDays();
    $pointsExpire = app(\App\Services\PointsService::class)->expiryMonths();
@endphp

@section('policy_key', 'terms')
@section('policy_title', __('Terms & Conditions'))
@section('policy_subtitle', __('Please read these terms before completing your purchase.'))

@section('policy')
<p>These terms apply when you use <strong>{{ $name }}</strong> and when you buy anything through it. You accept them by ticking the box at checkout before you pay.</p>

{{-- BML requirement 2: description of goods and services --}}
<h2>1. About our services</h2>
<p>{{ $name }} is an online marketplace operated by <strong>{{ $operator }}</strong>{{ Company::registrationNo() ? ' (registration no. '.Company::registrationNo().')' : '' }} in the Republic of Maldives. Independent local shops ("sellers") from islands across the Maldives sell products through {{ $name }}: food and groceries, handmade crafts, fashion, home and living, electronics, beauty and wellness, fishing and marine supplies, and kids' items. Each product page describes the item, shows its price, stock and the shop that sells it.</p>
<p>We run the website, take payment for your order on behalf of the sellers, and handle customer service, cancellations, returns and refunds. Every seller is reviewed by us before their products go live.</p>

{{-- BML requirements 4 and 5: transaction currency and merchant outlet country --}}
<h2>2. Payment and currency</h2>
<ul>
    <li>All prices are shown and charged in <strong>{{ Company::currency() }}</strong>. The total, including delivery, is shown at checkout before you pay.</li>
    <li>Card payments are processed securely by <strong>Bank of Maldives (BML)</strong> through BML Connect. We accept <strong>Visa, Mastercard, American Express and Maestro</strong>.</li>
    <li>The merchant outlet is located in the <strong>{{ Company::country() }}</strong>.</li>
    <li>Card details are entered only on BML's secure payment page. {{ $name }} does not store, view or process your card number, expiry date or security code.</li>
    @if(\App\Services\ManualPaymentService::transferEnabled() || \App\Services\ManualPaymentService::codEnabled())
        <li>Card payment is the main payment method. Orders are paid in full when placed, except pre-orders with a booking fee or paid once the shop confirms (section 5).</li>
        @if(\App\Services\ManualPaymentService::transferEnabled())
            <li><strong>Bank transfer:</strong> transfer the order total to the account shown after you order, with the order number as the reference, and upload the transfer slip within {{ \App\Services\ManualPaymentService::payWithinHours() }} hours, or the order is cancelled. The shops send your order once we have confirmed the payment.</li>
        @endif
        @if(\App\Services\ManualPaymentService::codEnabled())
            <li><strong>Cash on delivery</strong> (orders from one shop): pay the full amount in cash when the order is handed to you.</li>
        @endif
    @else
        <li>Card payment is the only payment method. Orders are paid in full when placed, except pre-orders with a booking fee or paid once the shop confirms (section 5); we do not accept cash on delivery or bank transfer.</li>
    @endif
</ul>

<h2>3. Wallet, gift cards and loyalty points</h2>
<ul>
    <li>Your iruali wallet holds credit from gift cards and from refunds you chose to take as credit. It pays towards your orders at checkout and is not paid out in cash, unless the law requires it.</li>
    <li>A gift card must be redeemed within {{ \App\Models\GiftCard::VALID_MONTHS }} months of being issued. Once redeemed, its credit stays in your wallet.</li>
    <li>Loyalty points are earned on paid orders and can be used at checkout. They have no cash value{{ $pointsExpire > 0 ? ' and expire '.$pointsExpire.' months after they were earned (we remind you first)' : '' }}. Points and vouchers used on an order that is cancelled are returned to your account.</li>
</ul>

{{-- BML requirement 8: delivery policy --}}
<h2>4. Delivery policy</h2>
<ul>
    <li><strong>Delivery area:</strong> inhabited islands in the Maldives. We do not deliver outside the Maldives.</li>
    <li><strong>Delivery fee:</strong> {{ Money::format((float) Setting::get('delivery_fee_greater_male')) }} in Greater Malé and {{ Money::format((float) Setting::get('delivery_fee_islands')) }} to other islands{{ (float) Setting::get('free_delivery_over') > 0 ? ', free on orders of '.Money::format((float) Setting::get('free_delivery_over')).' or more' : '' }}.. Shops that deliver their own orders may charge their own fee instead, and some bulky items carry an extra charge. The fee is shown at checkout before payment.</li>
    <li><strong>Estimated delivery time:</strong> usually 1–3 working days in Greater Malé and 3–10 working days to other islands, depending on boat and flight schedules.</li>
    <li>We may decline or cancel an order if delivery is not possible because of weather, distance or other operational reasons. In that case you receive a full refund.</li>
</ul>
<p>Full details: <a href="{{ route('policies.delivery') }}">Delivery Policy</a>.</p>

<h2>5. Pre-orders</h2>
<p>Some shops take pre-orders for items they receive only after you order, for example from abroad. The product page shows when the item is expected to ship and what you pay now, and checkout shows it again, with the days that apply to your order, before you order.</p>
<ul>
    <li><strong>Full price now:</strong> you pay in full when you order, and you can cancel for a full refund until anything in the order has been sent.</li>
    <li><strong>Booking fee now:</strong> you pay the booking fee when you order and the rest once the item has arrived. We send you a payment link by SMS and email; please pay within the days shown at checkout. The order is sent only once it is paid in full.</li>
    <li><strong>Nothing until the shop confirms:</strong> you pay nothing when you order. The shop checks that it can get the item. If it can, we send you a link to pay the full price within the days shown at checkout; if it is not paid by then, the order is cancelled. If it can't, the order is cancelled and you owe nothing.</li>
    <li><strong>If you cancel a booking-fee order, the booking fee is not refunded;</strong> anything you paid beyond it is. You get the booking fee back as well if the shop cancels, cannot get the item or moves the date later, or if the order is later than the number of days shown at checkout.</li>
    <li>Ship dates are the shop's estimate. If a date moves, we tell you and you may cancel.</li>
    <li>Booking-fee and "pay once the shop confirms" items are ordered on their own. Pre-orders cannot be paid in cash on delivery.</li>
</ul>

{{-- BML requirement 6: return, refund, exchange and cancellation --}}
<h2>6. Refund and cancellation policy</h2>
<p>Please read our full <a href="{{ route('policies.refunds') }}">Returns, Refunds &amp; Cancellations Policy</a> before you buy. In summary:</p>
<ul>
    <li>You can cancel your order free of charge while it is <em>Pending</em>, from My Orders. Pre-orders follow section 5: a booking fee is not refunded when you cancel.</li>
    <li>Items that arrive damaged, faulty, wrong or not as described can be returned within {{ $days }} days of delivery for a replacement, exchange or full refund.</li>
    <li><strong>Food and other perishables, opened beauty and personal care products, underwear, swimwear and custom-made items cannot be returned</strong> unless they arrive damaged, faulty or wrong. Change-of-mind returns are at the shop's discretion.</li>
    <li>If we or the shop cannot fulfil your order, you receive a full refund.</li>
    <li>Approved card refunds go back to the same card through BML, usually within 5–7 business days depending on your bank.</li>
    <li>For payment disputes, contact us within 7 days of the transaction.</li>
</ul>

{{-- BML requirement 7: import/export, legal restrictions and customs duties --}}
<h2>7. Import / export and legal restrictions</h2>
<p>All products are sold and delivered within the Maldives. No import, export or customs duties apply to orders from {{ $name }}. Sellers must follow Maldivian law, and items that are prohibited or restricted in the Maldives, including under the Maldives Customs Service's rules, may not be listed (see <a href="{{ route('policies.prohibited') }}">Prohibited &amp; Restricted Items</a>). If an item needs an age check or a permit, the seller may ask for proof before delivery and cancel the order with a full refund if it cannot be provided.</p>

{{-- BML requirements 9 and 10: privacy, security and card data --}}
<h2>8. Security and card data</h2>
<p>All card payments are processed on Bank of Maldives' secure payment page using SSL/TLS encryption. {{ $name }} does not store, view or retain any card details on its servers, and every payment result is confirmed directly with BML before an order is marked as paid. We restrict and regularly review access to customer data to prevent unauthorised access. See <a href="{{ route('policies.security') }}">Payment Security</a> and our <a href="{{ route('policies.privacy') }}">Privacy Policy</a>.</p>

{{-- BML requirement 11: keep transaction records --}}
<h2>9. Transaction records</h2>
<p>We strongly recommend that you keep a copy of your order confirmation email, your payment receipt and these Terms &amp; Conditions and policies as they were when you placed your order. A printable receipt for every order is available from <a href="{{ route('orders') }}">My Orders</a>.</p>

<h2>10. Your account</h2>
<p>You need an account to check out. Keep your password private; you are responsible for orders placed from your account. Tell us straight away if you think someone else has used it. We may suspend an account that is used for fraud, abuse of offers or harassment of shops or staff.</p>

<h2>11. Products, prices and availability</h2>
<p>Each product is sold by the shop named on it. Descriptions and photos are provided by the shop, and we remove listings that are wrong or misleading. If a price is clearly wrong because of a mistake, or an item turns out to be unavailable, we contact you before sending the order and you may cancel for a full refund.</p>

<h2>12. Reviews and questions</h2>
<p>Reviews and questions must be honest and about the product. We may remove content that is abusive, misleading, advertising, or that shares personal details.</p>

<h2>13. Privacy</h2>
<p>Your personal data is collected and used in accordance with our <a href="{{ route('policies.privacy') }}">Privacy Policy</a>.</p>

<h2>14. Governing law</h2>
<p>These terms are governed by the laws of the Republic of Maldives. Any dispute will be resolved through the relevant Maldivian courts or authority. Nothing in these terms limits your rights under Maldivian law.</p>

<h2>15. Changes to these terms</h2>
<p>We may update these terms. The version that applies to your order is the one shown when you placed it.</p>
<p>These terms are written in English. If a translation differs, the English version applies.</p>
@endsection
