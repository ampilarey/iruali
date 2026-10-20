# Saved for later

Things agreed on 2026-10-19 but not started (updated 2026-10-20). Pick any of them up by asking for it by name.

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

## Testing still open

- The two Cursor test passes (draft PRs #105 and #107) covered everything except a live round-trip
  of Google / Facebook / Apple sign-in (no keys in the demo). Their reports can be merged to keep
  them, or the PRs closed.
