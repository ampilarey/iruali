# Saved for later

Things agreed on 2026-10-19 but not started. Pick any of them up by asking for it by name.

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

- **WhatsApp order updates** for customers who prefer WhatsApp to SMS.
- **Daily sales summary** to the owner's phone or email: orders, revenue, refunds, problems.
- **Shop onboarding guide:** a simple page that walks a new seller through their first product,
  delivery settings and bank details.

## Testing still open

- The two Cursor test passes (draft PRs #105 and #107) covered everything except a live round-trip
  of Google / Facebook / Apple sign-in (no keys in the demo). Their reports can be merged to keep
  them, or the PRs closed.
