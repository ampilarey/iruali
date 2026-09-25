# BML card acceptance: website requirements

Bank of Maldives (BML) checks the website before approving card payments (MPG service / BML Connect).
This page maps each of BML's 14 **Website Requirements** to where iruali meets it, so it can be sent to
BML with the application. Replace `https://iruali.mv` with the site BML is reviewing
(for testing: `https://test.iruali.mv`).

Set the business details first: **Admin → Settings → Business details** (registered name, registration
number, business address, postal address, hours) and **Contact** (email, phone with `+960`). The page
lists anything BML will still ask for.

| # | BML requirement | Where it is on iruali |
|---|---|---|
| 1 | All card brand marks, full colour, equal prominence | Amex, Visa, Mastercard and Maestro marks (`/images/card-brands.png`) at checkout (payment method and payment box), on every product's buy box, in the footer of every page, and on [Payment Security](https://iruali.mv/payment-security). 3-D Secure is described on the same page. |
| 2 | Complete description of goods or services | Every product page (`/products/{product}`): overview, key features, specs table (brand, model, SKU, weight, dimensions, department), seller, price, stock. |
| 3 | Corporate information: trading name, full address of permanent establishment, postal address, email, phone with country code, customer service contact | [About & Contact](https://iruali.mv/about) (all fields), plus the footer of every page (registered name, registration no., address, email, phone) and the details box at the end of every policy page. |
| 4 | Transaction currency | "Transaction currency: MVR (Maldivian Rufiyaa)" at checkout next to the payment options; "Prices in MVR" in the footer and buy box; stated in Terms §3 and §5 and on Payment Security. |
| 5 | Merchant outlet country shown when payment options are presented | "Merchant outlet country: Maldives" in the payment box at checkout, beside the card marks; also in the footer, Terms §5 and Payment Security. |
| 6 | Return / refund / exchange / cancellation policy; limited-refund items made clear before purchase | [Returns, Refunds & Cancellations](https://iruali.mv/refund-policy) (cancellation, returns within N days, exchanges, **non-returnable items**, refund method and timing). A summary including the non-returnable items is shown at checkout above the Place Order button. |
| 7 | Import/export or other legal restrictions and customs duties | Terms §8 "Legal restrictions, import and export" and Delivery Policy §5: delivery within the Maldives only, so no import/export duties; prohibited/restricted items under Maldivian law are not allowed. |
| 8 | Delivery policy including special conditions | [Delivery Policy](https://iruali.mv/delivery-policy): fees (from Settings), free-delivery threshold, times for Greater Malé and other islands, how delivery works, receiving, special conditions. |
| 9 | Privacy policy: data collected, purpose, use, protection against unauthorised access to cardholder data | [Privacy Policy](https://iruali.mv/privacy-policy) §1–§4: data collected, why, who it is shared with, and security measures (card data never received or stored, HTTPS, hashed passwords, 2-step sign-in, restricted access). |
| 10 | Security capabilities and policy for transmitting card details | [Payment Security](https://iruali.mv/payment-security): BML hosted payment page, TLS, PCI DSS at the bank, 3-D Secure, no card data on iruali, server-side confirmation of every payment. Short statement also at checkout. |
| 11 | Recommend the cardholder keeps transaction records and the policies | Checkout payment box, order page, the order confirmation email, Terms §12, Refunds §7, Payment Security §4, and the box at the end of every policy page. |
| 12 | Purchase terms shown during the order process, on the checkout screen with the total or before final checkout | The checkout order summary shows the total, a "Before you order" summary of the key terms, and links to all policies, on the same screen as the Place Order button. |
| 13 | Mechanism to affirmatively accept the terms | Required checkbox at checkout: "I have read and accept the Terms & Conditions, Returns, Refunds & Cancellations, Delivery Policy and Privacy Policy" (each a link). The order cannot be placed without it. `paymentPortalExperience.externalWebsiteTermsAccepted = true` and the terms URL are also sent to BML with every transaction. |
| 14 | The webhook is the primary method for capturing transaction responses | Every transaction is created with `webhook = https://iruali.mv/api/payments/bml/webhook`; the same URL should be set in the BML merchant portal. Webhooks are signature-checked, then the transaction is fetched from BML's API before the order is marked paid. The customer's return is only a fallback. |

## Screenshots to include

1. Checkout: payment methods with the card marks, the payment box (currency, outlet country, security, keep records), and the order summary with the "Before you order" summary and the acceptance checkbox.
2. A product page (description, price in MVR, buy box with card marks).
3. Footer (business details, policy links, card marks, MVR, outlet country).
4. Each policy page: Terms, Returns/Refunds/Cancellations, Delivery, Privacy, Payment Security, About & Contact.

## Compared with Bake & Grill and Akuru

Both sites take BML card payments with this approach, and iruali now follows it:

| | Bake & Grill | Akuru | iruali |
|---|---|---|---|
| Card marks image (Amex, Visa, Mastercard, Maestro) | yes | yes (`card-brands.png`) | yes (same image) |
| Currency + outlet country at payment | yes | yes (payment trust bar) | yes (checkout payment box, footer) |
| Terms, Refund, Privacy pages | `/terms`, `/refund`, `/privacy` | Terms, Refund & Cancellation, Privacy | Terms, Returns/Refunds/Cancellations, Delivery, Privacy, Payment Security, About & Contact |
| Acceptance checkbox at checkout | yes | yes | yes |
| `paymentPortalExperience` (terms accepted + terms URL) | yes | yes | yes |
| `localId` letters and digits only | yes | yes | yes |
| Webhook as primary, API re-check | yes | yes | yes |

## Before sending to BML

- [ ] Fill in Admin → Settings → Business details and Contact (the checklist there must be empty).
- [ ] Have the policies reviewed by the business (they are drafts written for iruali's marketplace).
- [ ] Set `BML_API_KEY` (UAT), and in the BML portal set the webhook URL to `https://<site>/api/payments/bml/webhook`.
- [ ] Place a sandbox card order end to end and keep the screenshots.
- [ ] BML may also ask for company documents (registration certificate, ID of signatories, bank account details). Ask your BML relationship contact for their current list.
