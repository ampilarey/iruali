@extends('policies.layout')

@php use App\Support\Company; $name = Company::tradingName(); @endphp

@section('policy_title', __('Payment Security'))

@section('policy')
<p class="flex items-center gap-4 flex-wrap"><img src="/images/card-brands.png" alt="American Express, Visa, Mastercard and Maestro" width="368" height="75" class="h-9 w-auto"></p>
<p>Card payments on {{ $name }} are processed by <strong>Bank of Maldives (BML)</strong> through BML Connect. We accept <strong>Visa, Mastercard, American Express and Maestro</strong>. The transaction currency is <strong>{{ Company::currency() }}</strong> and the merchant outlet country is the <strong>{{ Company::country() }}</strong>.</p>

<h2>1. How your card details are protected</h2>
<ul>
    <li>When you choose card payment, you are taken to <strong>Bank of Maldives' secure payment page</strong> to enter your card details. They are sent straight to the bank over an encrypted connection.</li>
    <li><strong>{{ $name }} never sees, receives or stores your full card number, expiry date or security code (CVV).</strong> We only receive a transaction reference and whether the payment succeeded.</li>
    <li>Card payments are processed to the security standards of the card schemes (PCI DSS) by the bank.</li>
    <li>Your bank may ask you to confirm the payment with a one-time code or in your banking app (3-D Secure, such as Visa Secure or Mastercard Identity Check).</li>
    <li>Every page of {{ $name }} uses HTTPS (TLS encryption). Look for the padlock in your browser's address bar.</li>
</ul>

<h2>2. How we confirm payments</h2>
<p>We never rely on the browser to tell us a payment went through. Bank of Maldives notifies our server directly, and we check every payment with the bank's systems, including the amount and currency, before your order is marked as paid.</p>

<h2>3. Keep yourself safe</h2>
<ul>
    <li>Before entering card details, check that the page address belongs to Bank of Maldives.</li>
    <li>Never share your card PIN, one-time codes or passwords with anyone, including people claiming to be from {{ $name }}. We will never ask for them.</li>
    <li>If you see a card payment you don't recognise, contact your bank straight away, and tell us.</li>
</ul>

<h2>4. Keep your records</h2>
<p>We recommend you keep a copy of your order confirmation, your payment receipt and our <a href="{{ route('policies.terms') }}">Terms &amp; Conditions</a> and policies for your records.</p>
@endsection
