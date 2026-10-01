@php
    $rate = auth()->user()->effectiveCommissionRate();
    $rateText = rtrim(rtrim(number_format($rate, 2), '0'), '.');
    $schedule = (string) \App\Models\Setting::get('payout_schedule', 'weekly');
    $payoutDay = (string) \App\Models\Setting::get('payout_day', '');
    $scheduleText = ['weekly' => __('every week'), 'fortnightly' => __('every two weeks'), 'monthly' => __('every month')][$schedule] ?? $schedule;
    $termsUrl = \Illuminate\Support\Facades\Route::has('policies.seller_terms') ? route('policies.seller_terms') : route('policies.terms');
@endphp
<h2>How commission is worked out</h2>
<ul>
    <li>iruali keeps a percentage of the <strong>item price</strong> of everything you sell. Your rate is <strong>{{ $rateText }}%</strong> (shown on <a href="{{ route('seller.earnings') }}">Earnings</a>). It is the site default unless iruali has agreed a different rate for your shop.</li>
    <li>The rate is fixed on each order when it is placed; a later change only applies to new orders.</li>
    <li>Per order: <em>your earnings = item subtotal − commission</em>. Example at 10%: items worth MVR 500 → commission MVR 50 → you earn MVR 450.</li>
    <li>Delivery fees, vouchers and loyalty points are iruali's and never change what you earn: a customer paying with a voucher still earns you the full item price less commission.</li>
</ul>

<h2>When earnings become payable</h2>
<ul>
    <li><strong>Pending</strong> – the part is not delivered yet, or the customer's card payment has not been confirmed.</li>
    <li><strong>Ready</strong> – you marked the part delivered <em>and</em> the payment is confirmed. iruali can now pay it.</li>
    <li><strong>Paid out</strong> – included in a payout. Cancelled parts earn nothing.</li>
</ul>

<h2>When payouts happen</h2>
<ul>
    <li>iruali pays ready earnings <strong>{{ $scheduleText }}</strong>{{ $payoutDay !== '' ? ' ('.$payoutDay.')' : '' }} by bank transfer, as set out in the <a href="{{ $termsUrl }}">seller terms</a>.</li>
    <li>You need a bank account under <a href="{{ route('seller.settings.bank') }}">Settings → Bank account</a> (BML, MIB or another Maldivian bank, in MVR). Without one your earnings stay on hold. New or changed accounts are checked by iruali before the first transfer to them.</li>
    <li>Each payout covers everything ready at that moment, less any open adjustments.</li>
</ul>

<h2>Bank files and references</h2>
<ul>
    <li>iruali pays shops in batches through a BML bulk transfer. While a batch is being processed your Earnings page shows the payout as <em>on its way</em> with the batch reference (for example PB-2026-0003).</li>
    <li>When the bank has made the transfer you get a <em>Payout sent</em> email with the amount, the payout reference (batch/payout number, which also appears as the remark on your bank statement) and the bank's reference. The same details are on Earnings under Payouts.</li>
</ul>

<h2>Adjustments</h2>
<ul>
    <li>An adjustment is money taken from (or, rarely, added to) your next payout. The usual one is an approved return: your share of the returned items, i.e. their price less commission (see <a href="{{ route('seller.help.show', 'handling-returns') }}">Handling a return</a>).</li>
    <li>Adjustments are settled in the next payout even if the order was already paid out. If they are larger than what is ready, no payout is made until new sales cover them. Open and settled adjustments are listed on Earnings.</li>
</ul>
