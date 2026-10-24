@extends('policies.layout')

@php use App\Support\Company; $name = Company::tradingName(); @endphp

@section('policy_key', 'prohibited')
@section('policy_title', __('Prohibited & Restricted Items'))
@section('policy_subtitle', __('What may not be sold on iruali, and what needs a permit or extra care.'))

{{-- Public (unlike the Seller Terms): linked from the footer, the policy menu, the customer terms, the Seller Terms, the product form and the seller application. --}}
@section('policy')
<p>Every shop on {{ $name }} must follow Maldivian law, including the rules of the Maldives Customs Service, the Maldives Food and Drug Authority (MFDA) and the other authorities, and these rules of our own. Some items below are allowed by law in some places but are not allowed on {{ $name }}.</p>
<p>This list is a guide, not the whole law, and it can change. <strong>If you are not sure about an item, ask us before you list it.</strong> We may remove any listing, cancel orders for it with a full refund to the customer, and suspend a shop that breaks these rules (see the <a href="{{ route('policies.seller_terms') }}">Seller Terms</a>).</p>

<h2>1. Not allowed on {{ $name }}</h2>
<ul>
    <li><strong>Alcohol</strong> and alcoholic drinks, and kits for making them.</li>
    <li><strong>Pork</strong> and pork products, and food that contains them.</li>
    <li><strong>Tobacco, e-cigarettes and vapes</strong>, their liquids and parts, and shisha.</li>
    <li><strong>Drugs</strong>: narcotics and psychotropic substances, cannabis and CBD products, and equipment for using drugs.</li>
    <li><strong>Prescription medicines</strong> and any medicine that is not registered with the MFDA.</li>
    <li><strong>Weapons</strong>: firearms and ammunition, and their parts; replicas and toy guns that look real; explosives and fireworks; pepper spray, stun guns and similar; swords, daggers and knives made as weapons. Kitchen, fishing and work knives are fine.</li>
    <li><strong>Items against Islam</strong> or meant for the worship of other religions (such as idols), and material that insults any religion.</li>
    <li><strong>Pornography</strong> and sexually explicit material.</li>
    <li><strong>Protected wildlife</strong> and products made from it: turtles and turtle shell, sharks and rays, coral and black coral, giant clams, and anything else protected under Maldivian law.</li>
    <li><strong>Live animals.</strong></li>
    <li><strong>Fake or copied goods</strong>, and items that use a brand, logo or design without permission (see the Seller Terms).</li>
    <li><strong>Stolen goods</strong>, and anything the shop does not have the right to sell.</li>
    <li><strong>Gambling and lotteries</strong>, including tickets and paid draws.</li>
    <li><strong>Money and documents</strong>: currency (except collector items clearly sold as such), ID cards, passports, licences, and police, military or other official uniforms and badges.</li>
    <li><strong>Accounts and personal data</strong>: user accounts, passwords, personal details, and software or devices for spying, hacking or getting around security.</li>
    <li><strong>Recalled or unsafe products</strong>, and items banned from import into the Maldives.</li>
</ul>

<h2>2. Allowed only with a permit or extra care</h2>
<p>The shop must hold any licence, registration or permit the law requires, keep it valid, and show it to us when we ask. We may ask for it before approving the product.</p>
<ul>
    <li><strong>Medicines that do not need a prescription, supplements and medical devices:</strong> registered with the MFDA, sold only by shops allowed to sell them, with the label and expiry date shown.</li>
    <li><strong>Food and drinks:</strong> safe, properly labelled (ingredients, allergens, expiry date) and halal. Food made for sale needs the licences the law requires. Perishable food must be kept and delivered at the right temperature.</li>
    <li><strong>Cosmetics and personal care:</strong> no harmful or banned ingredients (such as mercury in skin-lightening creams), with ingredients listed.</li>
    <li><strong>Drones, radio transmitters and other telecom equipment:</strong> only types approved in the Maldives; drones need the permits for their use.</li>
    <li><strong>Pesticides, fertilisers and other chemicals:</strong> only those allowed in the Maldives, with safety instructions.</li>
    <li><strong>Plants and seeds:</strong> only with the plant health (biosecurity) permits needed.</li>
    <li><strong>Batteries, gas and other dangerous goods:</strong> packed and delivered safely; some cannot travel by air or boat.</li>
    <li><strong>Second-hand and refurbished items:</strong> clearly described as used, with their condition and any faults.</li>
    <li><strong>Items with an age limit:</strong> the shop checks the customer's age before handing them over and may cancel the order with a full refund.</li>
</ul>

<h2>3. Pre-orders from abroad</h2>
<p>A shop that brings items in for pre-orders is the importer. It must check that each item may be imported into the Maldives, get any permit it needs and pay any customs duty. The price shown to the customer includes these costs: the customer pays nothing more on delivery.</p>

<h2>4. Report a listing</h2>
<p>If you see an item on {{ $name }} that should not be there, please tell us using the contact details at the top of this page, with a link to the product. We check every report.</p>
@endsection
