# Bringing Bake & Grill features into iruali

From a code audit of both repos (October 2026). Bake & Grill (github.com/ampilarey/bakeandgrill) is a
single restaurant: Laravel API in `backend/`, React apps in `apps/` (order, POS, admin, KDS, driver).
iruali is a multi-shop marketplace in Laravel/Blade. Features are rebuilt the iruali way: Blade
pages, Services, Settings switches, tests, Dhivehi text, and with shops in mind. The B&G paths
below are where to look at how it works there; the code itself is not copied.

Work one phase at a time, one PR per numbered item (or a few small ones together). Each PR has
tests, runs Pint and Larastan, and is deployed to test.iruali.mv before the next one.

## Phase 1: before SMS goes live

1. ✅ **SMS control centre** (done: Admin → SMS, `App\Support\SmsTypes`, `sms:release-held`, `sms:prune`). One registry of every SMS iruali sends (today's `toSms()` texts become
   default templates). Admin → SMS shows each type with on/off, an editable template with
   placeholders (English and Dhivehi), preview, "send me a test", who receives it. Plus a monthly
   budget cap, quiet hours (non-urgent SMS wait until morning), and one switch that stops all SMS.
   Log page gets stats, CSV export and resend; old logs pruned.
   B&G: `backend/app/Domains/Notifications/Support/SmsTypeRegistry.php`, `admin/sms/control-center`,
   `sms:dispatch-scheduled`, `sms:release-deferred`, `sms:prune-logs`.
2. ✅ **Dhiraagu SMS driver** (done: `SMS_DRIVER=dhiraagu` with the portal username and password) next to the generic HTTP driver, set up from `.env`, with the ready check.
   B&G: `backend/app/Domains/Notifications/Providers/DhiraaguSmsProvider.php`.
3. ✅ **Alerts to the iruali team** (done: Admin → Team alerts, `App\Support\TeamAlerts`; SMS per staff member and a Telegram group; email stays with the contact inbox) by SMS, email or Telegram (each person picks): new order, bank
   slip uploaded, refund due, dispute opened, new shop application, product waiting for review,
   payment error, backup or scheduler failure. A Telegram bot posting new orders to a group.
   B&G: `backend/app/Domains/Telegram/*`, `admin/staff/{userId}/notification-prefs`.
4. ✅ **Sign in with an SMS code** (done: /login/code, `CodeLoginController`; a new password without the old one for 15 minutes after) (no password), and "forgot password" by SMS code, for customers
   with a verified phone. Sign-in code by email as a fallback. Throttled per phone and per IP.
   B&G: `CustomerAuthController` (`auth/customer/otp/*`, `check-phone`), `CustomerOtpService.php`.
5. ✅ **Payment link** (done: admin order → Payment → Send payment link; `/pay/{order}` signed for 48 hours, `App\Support\PayLink`) for an unpaid order (card, or the bank-transfer details), sent by SMS or
   email from the admin order page; a public pay page. B&G: `orders/{id}/send-pay-link`, `/pay/{token}`.
6. ✅ **BML reconcile job** (done: `payments:reconcile-bml` every 5 minutes; late money for a cancelled order was already flagged as a refund due with alerts): every few minutes, ask BML about card payments still pending, so a
   payment whose return and webhook were both lost is still recorded. Alert when money arrives
   for a cancelled order. B&G: `payments:reconcile-pending-bml`, `LatePaymentService.php`.

## Phase 2: customers, loyalty and accounts

7. ✅ **Loyalty tiers** (done: Reef / Lagoon / Atoll, `App\Support\LoyaltyTiers`, `rewards:refresh-tiers`) (e.g. Reef, Lagoon, Atoll) by spend in the last 12 months, shown on the
   rewards page with progress, earning extra points per tier; a message when someone moves up.
   B&G: `LoyaltyTierService.php`, `admin/loyalty/tiers`, `marketing:send-tier-milestones`.
8. ✅ **Points tools** (done: Admin → Rewards → Adjust; points are taken at checkout and returned on cancel; `rewards:check-balances` nightly): admin adjusts a customer's points with a reason; points held at checkout until
   the order is paid or cancelled; nightly check that balances match the ledger.
   B&G: `admin/loyalty/*`, `loyalty/hold*`, `app:reconcile-loyalty-balances`.
9. ✅ **Birthday offer** (done: birthday in My Account, `marketing:birthday-offers`): optional birthday on the profile; a single-use voucher by email/SMS that week.
   B&G: `marketing:send-birthday-offers`.
10. **Customer page for staff**: everything about one customer (orders, returns, disputes, messages,
    points, wallet, quotes), tags, private notes, merge duplicate accounts, change phone.
    B&G: `routes/domains/admin_customers.php`.
11. **Customer reports**: lifetime value, repeat rate, cohorts, top customers, with CSV.
    B&G: `reports/*` (LTV, cohorts, retention).
12. **Privacy**: staff can erase a customer's personal data (orders kept, names removed);
    customers can ask to delete their account from My Account. B&G: `CustomerErasureService`.
13. **Account safety**: "Sign out everywhere"; after repeated wrong passwords a staff account is
    locked for a while and the owner is told; an admin can reset another staff member's two-step
    sign-in; `staff:create`, `staff:list` commands. B&G: `auth/logout-everywhere`,
    `ClearStaffLoginThrottleCommand.php`, `DELETE admin/staff/{id}/two-factor`.
14. **Feedback and complaints**: rate the order from the delivered email or order page; a public
    complaint form (with photos) and admin complaints list with status, notes, a weekly summary
    and reminders for old ones. B&G: `routes/domains/complaints.php`, `receipts/{token}/feedback`.

## Phase 3: ordering and money

15. **Minimum order** (site-wide, and per shop) and an optional small-order fee.
    B&G: `config/ordering.php`, `OrderFeeTaxCalculator`.
16. **Pause ordering**: site-wide switch (or delivery only / cash on delivery only) with a message,
    a scheduled pause, and "tell me when it's back". B&G: `admin/service-availability/*`,
    `service-status/notify-me`.
17. **Shop opening hours** used for "ships today" and pickup times. B&G: `opening-hours*`.
18. **Live order page**: status changes show without a refresh (polling first; SSE later).
    B&G: `stream/order-status/{orderId}`.
19. **Late order alerts**: paid orders a shop has not started within N hours, and parts past their
    delivery estimate, to admins and the shop. B&G: `orders:alert-unstarted`, `ops:alert-delivery-delays`.
20. **Refund approval**: a refund above a set amount needs a second person; a daily refunds
    summary. B&G: `RefundWorkflowService`, `refunds:send-daily-summary`.
21. **Receipts**: PDF download and "email me the receipt again". B&G: `/receipts/{token}/pdf`.
22. **Pay later for business buyers**: approved businesses get a credit limit, invoices with a due
    date, reminders, overdue marking and statements. B&G: `admin/customers/{id}/credit/*`,
    `invoices:mark-overdue`, `credit:send-payment-reminders`.
23. **Better suggestions**: "frequently bought together" worked out nightly from orders, and cart
    suggestions, with a report of how often they are used. B&G: `insights:compute-item-pairs`,
    `recommendations/cart`.

## Phase 4: marketing

24. **SMS campaigns** to segments (bought from a category, a brand's followers, inactive 90 days,
    island), with preview, test, scheduling, opt-out and the budget from item 1.
    B&G: `admin/sms/campaigns*`, `audiences*`.
25. **Abandoned-cart SMS** as well as the email (for verified phones that allow marketing SMS).
26. **Promotion rules**: automatic offers without a code (spend MVR X get Y off, free delivery over
    X for a campaign); batches of printed discount cards. B&G: `backend/app/Domains/Promotions`,
    `admin/discount-cards/*`.
27. **Social posting**: post new products, deals and campaigns to the iruali Facebook page,
    Instagram and a Telegram/Viber channel, with an approval step; track which products customers
    share. B&G: `routes/domains/social.php`, `*AutoPoster.php`, `share-events`.
28. **Home page builder**: arrange home page blocks (hero, campaign, category row, brand row,
    product row) with draft, preview and publish. B&G: `admin/page-blocks*`, Content Studio.

## Phase 5: admin, finance and running the site

29. **More CSV exports**: orders, order lines, sales by shop / category / brand / island,
    customers, vouchers used, refunds by reason. B&G: `reports/*/csv`.
30. **iruali's own finance**: commission income, delivery income, card fees, expenses (with
    receipts and recurring ones), a monthly profit and loss. B&G: `reports/finance/*`, `expenses*`.
31. **Settlements**: import the BML statement and match card payments and bank transfers to
    orders. B&G: `settlements/*` (`BankStatementParser`).
32. **GST tools**: GST ledger, period lock, filing reminder. B&G: `backend/app/Domains/Gst`,
    `gst:filing-reminder`.
33. **Xero** (optional): push commission invoices and expenses. B&G: `backend/app/Domains/Accounting`.
34. **Webhooks out** (optional): order.created / paid / delivered for other systems.
    B&G: `backend/app/Domains/Webhooks`.
35. **Running the site**: alerts when queued jobs fail or the queue stops; `/health/ready`
    answering 503 when the database or storage is down; Sentry as an option; a command that checks
    the production `.env`; copy live to test with personal data removed.
    B&G: `jobs:alert-failed`, `ops:watch-queue-worker`, `SystemHealthService.php`,
    `VerifyProductionConfig`, `admin/ops/clone-live-to-test`.
36. **Prayer times** (optional): a small prayer-time bar for the shopper's island.
    B&G: `prayer-times/*`, `packages/prayer-times-widget`.

## Not brought over (restaurant only)

POS (and offline POS), shifts and cash drawer, time clock and rosters, kitchen display, ready
board, customer display, receipt printers, tables and table QR ordering, reservations, wait time,
modifiers / combos / platters, recipes, inventory / suppliers / purchasing / production / waste,
label printing, own drivers app and GPS (iruali shops ship; revisit if iruali runs its own
couriers), catering events, wholesale/consignment, TV signage, posters, printed menu, daily
specials, order-for-tomorrow, POS device approval and staff PIN login. Where a shop could use one
later (stock counts, labels), it is a seller-centre idea, not part of this plan.
