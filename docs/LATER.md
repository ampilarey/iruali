# Saved for later

Things agreed on 2026-10-19 but not started (updated 2026-10-21; booking-fee pre-orders, "pay once the shop confirms" and admin-allowed pre-orders are built). Pick any of them up by asking for it by name.

## Launch to-dos (owner, on the live server)

1. Deploy the newest `main` to iruali.mv: `bash ~/deploy-live.sh` (wait for GitHub checks first).
2. Open **Admin → dashboard → Launch checklist** on iruali.mv and turn every red line green.
3. **SMS (most urgent):** put the Dhiraagu SMS details in the live `.env`, then send a test from
   Admin → SMS. Sign-in and checkout start with the mobile number: without SMS, new customers
   cannot sign up.
4. **Card payments:** put the BML keys in the live `.env` (`BML_API_KEY`, `BML_ENVIRONMENT=production`
   once BML approves go-live), then place one small real order and refund it.
5. **Server settings:** `APP_DEBUG=false`, and an alerts address (`ALERTS_EMAIL`, or a contact email in
   Admin → Settings) for the daily error digest.
6. **Company details** in Admin → Settings (registered name, registration number, address, email,
   phone): shown on policies and receipts, and BML asks for them.
7. **Real content:** remove the sample data (Admin → Sample data), add the real shops and products.
   Islands: all 207 are loaded; add or correct any in Admin → Islands.
8. **Cash on delivery:** confirm the cash on the order once collected. A return on an unpaid order
   can take the items back but refunds nothing until the payment is confirmed.
9. **iruali's own shop (if iruali sells):** iruali sells through a registered shop, never from the admin
   area. Easiest: while signed in as admin, open "Sell on iruali" and apply as "iruali Direct" (same email
   and mobile); approve it in Admin → Sellers and set its commission to 0% there; give it iruali's bank
   and GST details in its Seller Centre. Then switch with "Switch to Seller Centre" (admin menu) and
   "Switch to Admin" (Seller Centre). Support, finance and catalogue accounts can't sell.

### Launch checklist on 2026-10-21 (Admin → dashboard → Launch checklist)

Green: sample data removed, SMS, live site settings, error alerts email, delivery fees.
Still to do, all in **Admin → Settings** (`/admin/settings`, one Save button at the bottom):

1. **Company details** (red): "Business details" → registered business name, registration number,
   business address; "Storefront" → contact email and contact phone (with +960).
2. **Payment methods** (red): "Payment methods" → tick bank transfer and fill in bank, account name
   and account number (customers upload the slip; confirm it on the order). Cash on delivery is
   optional. Card (BML) later, with the live keys in `.env`.
3. **Staff two-step sign-in** (amber): first set up your own authenticator (admin menu → Two-step
   sign-in), then tick "Require two-step sign-in for staff" under "Storefront".
4. **Shops and products** (red): shops apply from "Sell on iruali"; approve them in Admin → Sellers
   and their products in Admin → Products.
5. Google / Facebook / Apple sign-in (amber): optional.

## Optional setup

- **Google / Facebook / Apple sign-in:** keys in `.env`, step by step in `docs/SOCIAL_LOGIN.md`.
  Google is free and about 15 minutes; Apple needs the paid Apple Developer Program.

## Ideas to build next

None of these exist yet (checked against the code on 2026-10-19). Suggested order: 1 and 4 first
(they protect the business most in the first months), then 6.

### Most useful at launch

1. **WhatsApp order updates.** Order confirmations, "shipped" and "ready to collect" by WhatsApp for
   customers who choose it. Cheaper than SMS and more likely to be read. Fits the existing
   notification preferences (email / SMS / both) as a third channel.
2. **Shop onboarding guide.** A simple step-by-step page in the Seller Centre: first product with
   photos, delivery settings and coverage, bank details, then "you're ready". Fewer questions to the
   owner, fewer half-finished shops.
3. **Daily sales summary.** Every morning to the owner's email (and phone if wanted): yesterday's
   orders, revenue, refunds, returns and disputes, plus anything stuck (unpaid, late, unanswered).
4. **Proof of delivery.** At handover the shop or driver takes a photo, or the customer signs on the
   phone screen; it is saved on the order. Settles "I never got it" disputes.
5. **Live chat / help button.** A WhatsApp or chat button on product and checkout pages, so questions
   are answered before the customer gives up.

### Islands-specific

6. **Boat-day delivery estimates.** Each atoll's cargo boat days (e.g. Addu: Sunday and Wednesday),
   set in Admin → Delivery, so island estimates say "arrives around Thursday" instead of a range.
7. **Island group orders.** Customers on the same island share one delivery fee when they order
   before the next boat day. Lowers island delivery cost, the biggest barrier outside Malé.

### Bringing customers back

8. **Repeat orders / subscriptions.** "Deliver this every week / month" for everyday items (rice,
   water, nappies). Reminder before each run, skip or pause any time, pay each time as usual.
9. **Ramadan & Eid mode.** Switched on by date: iftar-time delivery slots, a seasonal banner and
   Eid gift collections.
10. **Search in Latin-letter Dhivehi.** Understand Dhivehi typed in English letters (e.g. "mas" for
    fish, "kandu mas") as well as Thaana and English.

### Pre-orders with a booking fee (order-on-demand imports): built on 2026-10-21

Built as recommended (see `docs/FEATURES.md`, "Pre-orders with a booking fee"): a booking fee per
product (percentage or amount per item), "ships about N days after each order" or a fixed date, the
balance by pay link (SMS and email) when "Stock arrived" is recorded, no delivery before it is paid,
a reminder and an overdue alert, and the fee kept when the customer cancels (refunded in full when the
shop cancels, can't get the item, moves the date later or is later than the product allows). Booking-fee
items are ordered on their own.

How to use it: make a shop account for these imports (e.g. "iruali Direct", not the admin account),
allow it pre-orders (Admin → Sellers → the shop → "Allow pre-orders"; "coming soon" is a separate switch),
then in its product form → Pre-order: tick "Take pre-orders", choose "Some days after each order"
(e.g. 14), the most units, "A booking fee: a percentage of the price" (e.g. 30), and if they differ
from 7, the days to pay the rest and the days late before a full refund. When the camera arrives,
Seller Centre → Pre-orders → "Stock arrived"; the customer is asked for the balance. If the shop is
GST-registered (Seller Centre → Settings → Tax), each booking fee gets its own tax invoice.

Added on 2026-10-21 after the first version: each product sets its own days (to pay the rest once it
arrives, and how late before a full refund), and the booking fee has GST with its own tax invoice.

Added on 2026-10-21, third round:

- **Only shops an admin allows** can take pre-orders or list "coming soon", each allowed on its own
  (Admin → Sellers → the shop's page → "Allow pre-orders" / "Allow "coming soon""). A shop sees only
  what it is allowed: no Pre-order or Coming soon section in its product form and no Pre-orders tab
  otherwise. Off for every shop until then; what shops had set up before was switched off.
- **Pay once the shop confirms:** in the product form → Pre-order → "What the customer pays at
  checkout", choose "Nothing yet: the full price once I confirm I can get it" (and the days to pay, 3
  if left empty). The customer orders without paying; the shop (Seller Centre → Pre-orders → "To
  confirm") or an admin (Admin → Pre-orders) presses "I can get it", and the customer gets an SMS and
  email with a link to pay in full. "I can't get it" cancels the order. Unpaid after the days to pay,
  the order is cancelled.

Still open (owner's choice):

1. **GST check:** the booking fee gets its own tax invoice when it is paid, and the final invoice
   deducts it. Ask the accountant to confirm this is how MIRA wants advance payments shown, and that a
   fee kept when the customer cancels stays a sale.
2. **Payouts:** a kept booking fee is credited to the shop, but paid out with the shop's next payout
   that includes a delivered order (payouts need at least one).
3. **Mixed carts:** booking-fee items with ordinary items in one order (second version).
4. **Link to the overseas listing:** a field on the product for the page the vendor buys from (e.g. an
   Amazon or AliExpress link), seen by admins, so iruali can check what is being bought. Not built.

### Review notes: how overseas pre-orders work now (checked 2026-10-23)

For a vendor who lists items from online shops abroad and buys them once the customer pays:

- **Who:** only vendors allowed in Admin → Sellers → the shop → "Allow pre-orders"; others see nothing of it.
- **Listing:** a normal product in the Seller Centre (name, photos, MVR price that already covers shipping
  to the Maldives), stock 0, then Pre-order: "Take pre-orders", when it ships ("Some days after each
  order", e.g. 21, or a fixed date), most units at a time, what the customer pays at checkout, days to
  pay (empty: 7 for a booking fee, 3 for confirm first) and days late before it counts as late.
- **Three ways to pay:**
  | Choice | When ordered | Vendor buys abroad | When it arrives |
  | --- | --- | --- | --- |
  | Full price | pays everything | once told it is paid | "Stock arrived", then send |
  | Booking fee (% or amount) | pays the fee (own tax invoice if GST-registered) | once the fee is paid | "Stock arrived": customer gets an SMS/email link for the rest; send once paid |
  | Nothing until the vendor confirms | pays nothing | checks the shop abroad, then "I can get it" (customer gets a link to pay in full) or "I can't get it" (cancelled) | "Stock arrived", then send |
- **Rules:** these items are ordered on their own, by card or bank transfer (no cash on delivery); nothing
  is sent before it is fully paid; unpaid links get one reminder, then the order is cancelled (confirm
  first) or flagged in Admin → Inbox (booking-fee balance); a customer cancelling loses the booking fee
  (kept by the vendor less commission), but gets everything back if the vendor cancels, can't get it,
  moves the date later or is too late; late orders show in Admin → Inbox, all waiting ones in Admin →
  Pre-orders.
- **To decide on review:** items 1–4 above (GST check, payouts of kept fees, mixed carts, overseas link).

## Testing still open

- The two Cursor test passes (draft PRs #105 and #107) covered everything except a live round-trip
  of Google / Facebook / Apple sign-in (no keys in the demo). Their reports can be merged to keep
  them, or the PRs closed.
