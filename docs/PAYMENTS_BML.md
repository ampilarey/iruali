# Card payments with BML Connect

iruali takes card payments (Visa, Mastercard, American Express) through **BML Connect**, the Bank of Maldives
payment gateway, using its redirect method: the customer places the order, pays on BML's secure page, and comes
back to their order page. iruali never sees card details.

## Setup

1. Get a BML Connect merchant account from Bank of Maldives and sign in to the merchant portal.
2. In the portal, create an app and copy its **API key**. Start in the **UAT (sandbox)** environment.
3. On the server, add to `.env`:

   ```
   BML_API_KEY=your_api_key
   BML_ENVIRONMENT=sandbox        # production when BML approves the go-live
   ```

   then run `php artisan config:clear` (the deploy scripts cache config for you).

   Optional, matching the Bake & Grill and Akuru setups:

   | Variable | Use |
   | --- | --- |
   | `BML_AUTH_MODE` | `auto` (default: `Bearer` for a JWT key, plain key otherwise), `raw` (UAT), `bearer_jwt`, `bearer_basic` (needs `BML_APP_ID`) |
   | `BML_APP_ID` | App ID from the portal, for `bearer_basic` |
   | `BML_WEBHOOK_SECRET` | Webhook secret from the portal; enables `X-BML-Signature` HMAC checking |
   | `BML_EXTERNAL_TERMS_URL` | Terms URL sent to BML (defaults to `/terms`) |

   In the BML portal, set the **webhook URL** to `https://<your-site>/api/payments/bml/webhook`. The webhook
   host must match the site the Connect app is registered for, or BML rejects new transactions.
4. Admin → Settings → **Payments** shows whether card payments are on, and whether it is the sandbox.
   You can turn cash on delivery off there to take card payments only.
5. Make sure the Laravel scheduler runs (cPanel → Cron Jobs):

   ```
   * * * * * cd /path/to/iruali && php artisan schedule:run >> /dev/null 2>&1
   ```

   It cancels card orders that are still unpaid after 24 hours so their stock goes back on sale
   (`php artisan orders:cancel-unpaid-card`).

When BML approves you for live payments, swap in the production API key, set `BML_ENVIRONMENT=production`, and
place one small real order to check the whole path.

## How it works

| Step | What happens |
| --- | --- |
| Checkout | Order is created (stock is reserved) with payment method `bml`, then the customer is sent to BML. |
| BML page | Customer pays. BML sends them back to `/payments/bml/return/{order}`. |
| Return | iruali **asks BML's API** for the transaction (`GET /v2/transactions/{id}`). Only `CONFIRMED` with a matching amount, currency and reference marks the order paid. |
| Webhook | BML also calls `POST /api/payments/bml/webhook` (sent with each transaction). The signature (`X-Signature` = SHA-256 of nonce + timestamp + API key) is checked, then the API is asked again. |
| Not paid | The order page shows **Pay now** to try again. Each try is a new BML transaction; `localId` is the order number in letters and digits only plus `P1`, `P2`, … (BML rejects other characters). |
| Admin | The order page lists every attempt and its BML state, with **Check with BML** to refresh. |

Amounts are sent in laari (MVR 1.00 = 100). Each transaction also sends `paymentPortalExperience`
(`externalWebsiteTermsAccepted: true` and the terms URL), which BML Connect v2 expects. Each attempt is
stored in the `payment_transactions` table.

The website requirements BML checks before go-live are covered in [BML_COMPLIANCE.md](BML_COMPLIANCE.md).

## Refunds

Refund card payments in the BML merchant portal. If a paid card order is cancelled, the admin order page
reminds you to refund it.

## API reference

- Sandbox: `https://api.uat.merchants.bankofmaldives.com.mv/public`
- Production: `https://api.merchants.bankofmaldives.com.mv/public`
- Header: `Authorization: <API key>`
- BML docs: https://docs.merchants.bankofmaldives.com.mv
