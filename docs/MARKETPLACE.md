# Multi-vendor: shop parts, commission and payouts

## Shop parts (per-shop fulfilment)

Every order is split into one **part per shop** (`seller_orders`). A customer can buy from several shops in
one checkout; each shop handles its own part.

| Step | Who | What happens |
| --- | --- | --- |
| Order placed | customer | One part per shop, status `pending`. Each shop gets an email with only its items. |
| Start preparing | shop (Seller Centre → Orders) | Part → `processing`. The order becomes `processing` as soon as any shop starts. |
| Mark as sent | shop | Part → `shipped`, with an optional tracking note (boat, flight, courier, reference). If other shops are still preparing, the customer gets "Items from *shop* are on their way". |
| All parts sent | automatic | Order → `shipped`; the customer's email lists every shop's tracking note. |
| Mark as delivered | shop | Part → `delivered`. Order → `delivered` when every part is delivered. |

- The order's status follows the **slowest** part and only moves forward.
- Admins can move a single part (Admin → order → *Shops in this order*) or the whole order; moving the whole
  order brings every part along.
- **Cancelling** is done on the whole order (customer while pending, or admin). It is blocked once any shop
  has sent its part; after that, handle it as a return.
- Delivery is still one fee per order, charged by iruali.

## Commission and earnings

- Each shop has a **commission rate** (Admin → Shop payouts). Blank = the default in Settings (10% to start).
- When an order is placed, each part stores: item subtotal, the rate at that moment, commission, and the
  shop's earnings (`subtotal − commission`). Changing a rate only affects new orders.
- Delivery fees, vouchers and loyalty-point discounts are iruali's and don't change what a shop earns.

Earnings states: **pending** (not delivered or not paid yet) → **ready** (part delivered *and* the order is
paid: card payment confirmed by BML; older non-card orders can be marked paid by an admin) → **paid out**.
Cancelled parts earn nothing.

## Payouts

1. Shops add their bank account in Seller Centre → **Settings → Bank account** (`seller_bank_accounts`: BML,
   MIB or another bank; BML numbers are 13 digits starting 7730/7770, MIB 16 digits starting 90). The number
   is shown masked to the shop; admins see it in full on Shop payouts and can tick **Verified** once it has
   been checked. A changed account loses its verified tick. **A shop with no bank account cannot be paid**:
   `PayoutService` refuses and the admin pages say why.
2. Admin → **Shop payouts** shows, per shop: ready, pending, paid, commission rate and bank account.
3. **Pay out** (one shop) lists the ready orders. Untick any to hold back (e.g. a return in progress), make
   the bank transfer, enter its reference and **Record payout**. Those parts are marked paid; they can't be
   paid twice.
4. Each payout has a statement page and a CSV download. Shops see their balances, per-order earnings and
   payouts under Seller Centre → **Earnings**.

### Payout batches (several shops in one bulk transfer)

Admin → Shop payouts → **New payout batch** (`payout_batches`, reference `PB-<year>-<number>`):

1. Tick the shops to pay (or **Select all with bank details**). Each ticked shop gets one *pending* payout
   for everything it is owed right now, with its open adjustments settled. Shops without a bank account are
   listed but cannot be ticked. The earnings leave the shops' "ready" balance (they see "Payout on its way").
   Parts and adjustments are locked while a batch is drafted, so the same earnings can never be in two
   batches or paid twice.
2. Status **draft**. A draft can be **cancelled**: its payouts are deleted and the earnings are available
   again (the batch stays in the list as *cancelled*).
3. **Download bank file** → status **exported**. Upload the file in BML Internet Banking → Bulk transfer and
   check the total. An exported batch can no longer be cancelled.
4. **Mark paid** with the bank's reference and the transfer date → status **paid**: every payout in the
   batch is marked paid (through `PayoutService`, with the same row locking as single payouts), each shop
   is emailed (`PayoutPaid`, queued) and sees the batch reference, bank reference and date on Earnings.

### Bank file

The file is produced by `App\Support\BankFileFormat` so the exact layout lives in one place:

- CSV, UTF-8, **no byte-order mark**, **CRLF** line endings (also after the last row), file name
  `bml-bulk-<batch reference>.csv`.
- Header row: `Beneficiary Account Number,Beneficiary Name,Amount,Remarks`.
- One row per shop, sorted by shop name:
  - **Beneficiary Account Number** — the account number, digits only.
  - **Beneficiary Name** — the account name exactly as the shop entered it.
  - **Amount** — MVR with two decimals, dot as decimal mark, no thousands separator (`1250.00`).
  - **Remarks** — the payout reference `<batch reference>/<payout id>`, e.g. `PB-2026-0001/17`; the shop sees
    the same reference on its earnings page.
- Line breaks in a value become spaces. A field is double-quoted only when it contains a comma or a quote;
  quotes inside it are doubled.

```
Beneficiary Account Number,Beneficiary Name,Amount,Remarks
7730000123456,Island Crafts Pvt Ltd,1125.00,PB-2026-0001/17
7770000654321,Reefline Marine,89.55,PB-2026-0001/18
```

If BML changes the template, change `BankFileFormat` and the byte-for-byte test in `PayoutBatchesTest`.

## Returns and refunds

1. **Customer:** on a delivered order (My Orders → the order), each shop's part has **Request a return**
   within the return window (Settings → return window days). They pick the items and quantities, a reason,
   notes and a photo (required for damaged or wrong items). One open request per shop part at a time.
2. The shop and the admin contact email are emailed. Shops see requests under Seller Centre → **Returns**.
3. **Admin → Returns** → open a request:
   - **Approve** with the refund amount. The suggestion is the items' value, plus the delivery fee when the
     shop was at fault and it was the only shop in the order. The refund can't exceed what is left on the
     order. Tick **Put the items back in stock** if they can be resold.
   - Or **Reject** with a reason (sent to the customer).
4. Approving takes the shop's share of the returned items (price less the part's commission) from its
   earnings as an **adjustment**. It is settled in the shop's next payout, whether or not that order was
   already paid out. If deductions are more than what is ready, no payout can be recorded until new sales
   cover them.
5. Send the money: card orders are refunded in the **BML merchant portal**; other payments by bank
   transfer. Then **Mark as refunded** with the reference. The customer is emailed at each step and sees
   the status and reference on their order.

Adjustments show on the payout page, statement CSV and Seller Centre → Earnings.
