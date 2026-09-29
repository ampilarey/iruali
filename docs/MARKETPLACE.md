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
paid: card confirmed by BML, bank transfer confirmed, or cash on delivery marked paid) → **paid out**.
Cancelled parts earn nothing.

## Payouts

1. Shops add their bank account in Seller Centre → Profile.
2. Admin → **Shop payouts** shows, per shop: ready, pending, paid, commission rate and bank account.
3. **Pay out** lists the ready orders. Untick any to hold back (e.g. a return in progress), make the bank
   transfer, enter its reference and **Record payout**. Those parts are marked paid; they can't be paid twice.
4. Each payout has a statement page and a CSV download. Shops see their balances, per-order earnings and
   payouts under Seller Centre → **Earnings**.

Returns or refunds after a payout are settled with the shop outside the system (adjust the next transfer).
