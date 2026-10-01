@php $window = \App\Support\Company::returnWindowDays(); @endphp
<h2>When a customer can ask for a return</h2>
<ul>
    <li>Only on a part you have marked <strong>delivered</strong>, and within <strong>{{ $window }} {{ \Illuminate\Support\Str::plural('day', $window) }}</strong> of that date (the return window set by iruali).</li>
    <li>The customer picks the items and quantities, a reason (arrived damaged, faulty, wrong item, not as described, missing, or changed my mind), writes notes and can attach a photo. A photo is required for damaged or wrong items.</li>
    <li>One open request per shop part at a time. Items from an earlier approved request cannot be requested again.</li>
</ul>

<h2>The flow</h2>
<ol>
    <li><strong>Requested</strong> – you and iruali are emailed. The request appears under <a href="{{ route('seller.returns') }}">Returns</a> with the customer's reason, notes and photo. Keep the items aside if they are sent back to you; do not refund the customer yourself.</li>
    <li><strong>Reviewed by iruali</strong> – iruali reads the request (and anything you tell us) and either <strong>approves</strong> it with a refund amount, or <strong>rejects</strong> it with a reason sent to the customer. If the items can be resold, iruali ticks "put back in stock" and your stock goes up again.</li>
    <li><strong>Refunded</strong> – iruali sends the money back to the customer's card through BML and records the reference. The customer is emailed at every step.</li>
</ol>

<h2>Timelines</h2>
<ul>
    <li>Reply to iruali about a request within two working days; otherwise it is decided on the customer's evidence.</li>
    <li>Approved refunds usually reach the customer's card in 5–7 business days, depending on their bank.</li>
</ul>

<h2>What you are charged</h2>
<ul>
    <li>When a return is approved, your share of the returned items is taken back: the items' price <strong>less the commission</strong> you already paid on them. Example: an item sold for MVR 100 at 10% commission earned you MVR 90, so MVR 90 is taken back.</li>
    <li>It is recorded as an <strong>adjustment</strong> on your <a href="{{ route('seller.earnings') }}">Earnings</a> page and settled in your next payout, whether or not that order was already paid out. If deductions are more than what is ready to pay, nothing is paid until new sales cover them.</li>
    <li>Delivery fees are iruali's. If the fault was the shop's and it was the only shop in the order, iruali may refund the delivery fee to the customer; that part is not charged to you.</li>
    <li>Change-of-mind returns are at iruali's discretion. Food, opened cosmetics, underwear, swimwear and custom-made items are only returned when damaged, faulty or wrong.</li>
</ul>
