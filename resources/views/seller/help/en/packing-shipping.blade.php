@php $lateDays = (int) \App\Models\Setting::get('late_shipment_days', 3); @endphp
<h2>Your part of the order</h2>
<p>A customer can buy from several shops in one checkout. iruali splits the order into one part per shop; you only see and handle your own items, and the customer pays iruali by card before anything is packed. Card orders not paid within 24 hours are cancelled automatically and the stock is put back.</p>

<h2>Statuses</h2>
<ol>
    <li><strong>Pending</strong> – a new order. You get an email with the items to pack (turn it on or off under <a href="{{ route('seller.settings.notifications') }}">Settings → Notifications</a>).</li>
    <li><strong>Processing</strong> – press <em>Start preparing</em> on the order as soon as you begin packing. The customer sees the order move to processing.</li>
    <li><strong>Shipped</strong> – press <em>Mark as sent</em> when the parcel has left you, and add a tracking note: the boat or flight, the courier, and a reference the customer can quote. If other shops in the same order are still packing, the customer is emailed that <em>your</em> items are on their way.</li>
    <li><strong>Delivered</strong> – press <em>Mark as delivered</em> once the customer has it. This starts the return window and is what makes your earnings payable.</li>
</ol>
<p>Statuses only move forward. The customer's order shows the status of the slowest shop. You cannot cancel your part yourself; while the order is pending the customer can cancel it, and after that iruali handles problems as returns.</p>

<h2>Deadlines</h2>
<ul>
    <li>Ship within <strong>{{ $lateDays }} {{ \Illuminate\Support\Str::plural('day', $lateDays) }} of payment</strong>. Parts shipped later than that count as late on your Performance page, which iruali reviews.</li>
    <li>If you cannot ship in time (out of stock, bad weather), tell iruali straight away so the customer can be told or refunded.</li>
</ul>

<h2>Island deliveries</h2>
<ul>
    <li>The customer pays one delivery fee per order to iruali: one rate for Greater Malé (Malé, Hulhumalé, Villimalé) and one for other islands. You do not charge delivery separately.</li>
    <li>Use the delivery notes on your <a href="{{ route('seller.profile') }}#delivery">profile</a> to say how you send: supply boat, speed launch, domestic flight cargo, or courier in Malé, and how long it usually takes.</li>
    <li>Write the customer's name, island and phone number clearly on the parcel. Boat crews call the number on the parcel; it is on the order page.</li>
    <li>Pack for the sea: waterproof bags for paper and fabric, boxes with padding for anything fragile, and sealed containers for food. Perishables should go on the next departure, not wait for a weekly boat.</li>
</ul>
