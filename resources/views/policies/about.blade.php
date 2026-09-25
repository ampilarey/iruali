@extends('policies.layout')

@php use App\Support\Company; $name = Company::tradingName(); @endphp

@section('policy_title', __('About & Contact'))
@section('policy_subtitle', __('Who we are and how to reach us.'))

@section('policy')
<p><strong>{{ $name }}</strong> is an online marketplace for shops across the Maldives. Local sellers, from fishermen and farmers to craftspeople and stores, list their products here, and we deliver to inhabited islands in every atoll. Every shop is reviewed by our team before its products go live.</p>

<h2>Business details</h2>
<dl class="grid sm:grid-cols-[14rem_1fr] gap-x-6 gap-y-2">
    <dt class="font-semibold text-dark">Trading name</dt><dd>{{ $name }}</dd>
    @if(Company::legalName())<dt class="font-semibold text-dark">Registered business name</dt><dd>{{ Company::legalName() }}</dd>@endif
    @if(Company::registrationNo())<dt class="font-semibold text-dark">Registration number</dt><dd>{{ Company::registrationNo() }}</dd>@endif
    @if(Company::address())<dt class="font-semibold text-dark">Business address</dt><dd>{{ Company::address() }}</dd>@endif
    @if(Company::postalAddress())<dt class="font-semibold text-dark">Postal address</dt><dd>{{ Company::postalAddress() }}</dd>@endif
    <dt class="font-semibold text-dark">Country</dt><dd>{{ Company::country() }}</dd>
    <dt class="font-semibold text-dark">Currency</dt><dd>{{ Company::currency() }}</dd>
</dl>

<h2>Customer service</h2>
<dl class="grid sm:grid-cols-[14rem_1fr] gap-x-6 gap-y-2">
    @if(Company::email())<dt class="font-semibold text-dark">Email</dt><dd><a href="mailto:{{ Company::email() }}">{{ Company::email() }}</a></dd>@endif
    @if(Company::phone())<dt class="font-semibold text-dark">Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', Company::phone()) }}" dir="ltr">{{ Company::phone() }}</a></dd>@endif
    @if($whatsapp = preg_replace('/[^0-9]/', '', (string) \App\Models\Setting::get('whatsapp_number')))<dt class="font-semibold text-dark">WhatsApp</dt><dd><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" dir="ltr">+{{ $whatsapp }}</a></dd>@endif
    @if(Company::hours())<dt class="font-semibold text-dark">Hours</dt><dd>{{ Company::hours() }}</dd>@endif
</dl>
<p>For order questions, have your order number ready. You can also follow any order under <a href="{{ route('orders') }}">My Orders</a> or <a href="{{ route('order.track.form') }}">Track Order</a>, and find answers in the <a href="{{ route('help') }}">Help centre</a>.</p>

<h2>Our policies</h2>
<ul>
    <li><a href="{{ route('policies.terms') }}">Terms &amp; Conditions</a></li>
    <li><a href="{{ route('policies.refunds') }}">Returns, Refunds &amp; Cancellations</a></li>
    <li><a href="{{ route('policies.delivery') }}">Delivery Policy</a></li>
    <li><a href="{{ route('policies.privacy') }}">Privacy Policy</a></li>
    <li><a href="{{ route('policies.security') }}">Payment Security</a></li>
</ul>
@endsection
