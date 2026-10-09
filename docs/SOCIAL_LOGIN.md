# Sign in with Google, Facebook and Apple

Customers can sign in, or sign up, with **Continue with Google / Facebook / Apple** on the
sign-in and sign-up pages (English and Dhivehi). Each button appears only once that provider's
keys are in `.env`, so the providers can be switched on one at a time. No extra packages are
used: the OAuth 2 / OpenID Connect flows are in `app/Services/SocialLogin/` and
`app/Services/SocialLoginService.php`.

## Redirect URLs

Register exactly these with each provider (both sites can share one app per provider):

| Provider | iruali.mv | test.iruali.mv |
|---|---|---|
| Google | `https://iruali.mv/auth/google/callback` | `https://test.iruali.mv/auth/google/callback` |
| Facebook | `https://iruali.mv/auth/facebook/callback` | `https://test.iruali.mv/auth/facebook/callback` |
| Apple | `https://iruali.mv/auth/apple/callback` | `https://test.iruali.mv/auth/apple/callback` |

The site builds the URL from the address it is opened at, so it must be served over `https` on
that exact host. Apple and Facebook accept only `https` addresses on a real domain (Google allows
`http://localhost` for a development client only), so try the buttons on test.iruali.mv.

## Keys (`.env`)

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

FACEBOOK_CLIENT_ID=          # App ID
FACEBOOK_CLIENT_SECRET=      # App secret

APPLE_CLIENT_ID=             # the Services ID, e.g. mv.iruali.signin
APPLE_TEAM_ID=
APPLE_KEY_ID=
APPLE_PRIVATE_KEY_PATH=storage/app/private/AuthKey_XXXXXXXXXX.p8
# or the key itself, line breaks written as \n:
# APPLE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIGT...\n-----END PRIVATE KEY-----"
```

After changing `.env` on the server run `php artisan config:cache`.

## Google

1. Open the [Google Cloud console](https://console.cloud.google.com/) and create a project
   (e.g. "iruali").
2. **Google Auth Platform → Branding** (the OAuth consent screen): app name *iruali*, support
   email, logo, home page `https://iruali.mv`, privacy policy `https://iruali.mv/privacy-policy`,
   terms `https://iruali.mv/terms`, authorised domain `iruali.mv`.
3. **Audience**: External, then **Publish app** (in "Testing" only listed test users can sign in).
4. **Data access**: the scopes `openid`, `.../auth/userinfo.email` and `.../auth/userinfo.profile`
   (non-sensitive: no Google review needed beyond the brand check).
5. **Clients → Create client → Web application**. Authorised redirect URIs: the two Google URLs
   above. Copy the client ID and secret into `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.

## Facebook

1. On [Meta for Developers](https://developers.facebook.com/apps) create an app with the use case
   **Authenticate and request data from users with Facebook Login**.
2. **App settings → Basic**: app domains `iruali.mv` and `test.iruali.mv`, privacy policy URL
   `https://iruali.mv/privacy-policy`, terms URL `https://iruali.mv/terms`, an icon and a category.
   Facebook also asks for **User data deletion**: give the privacy policy URL (it explains how to
   ask for deletion). Copy the **App ID** and **App secret** into `FACEBOOK_CLIENT_ID` /
   `FACEBOOK_CLIENT_SECRET`.
3. **Use cases → Facebook Login → Settings**: Client OAuth login and Web OAuth login on, Enforce
   HTTPS on, Strict mode for redirect URIs on; **Valid OAuth Redirect URIs**: the two Facebook URLs
   above. Permissions: `email` and `public_profile` (standard access, no App Review needed).
4. Switch the app to **Live** (publish it). In development mode only the app's own admins and
   testers can sign in.

The sign-in uses Facebook's OpenID Connect flow with PKCE (the `openid` scope). Should Facebook
ever reject that (an error page mentioning `code_challenge` or `openid`), set `FACEBOOK_PKCE=false`
to use the classic code flow. `FACEBOOK_GRAPH_VERSION` (default `v25.0`) can move to a newer Graph
API version when Facebook retires this one.

## Apple

Needs a paid Apple Developer Program membership.

1. [Certificates, Identifiers & Profiles](https://developer.apple.com/account/resources/identifiers/list)
   → **Identifiers → +  → App IDs → App**: description "iruali", an explicit bundle ID such as
   `mv.iruali.app`, tick **Sign in with Apple**, register.
2. **Identifiers → + → Services IDs**: description "iruali sign-in", identifier such as
   `mv.iruali.signin` (this is `APPLE_CLIENT_ID`). Open it, tick **Sign in with Apple →
   Configure**: primary App ID from step 1; **Domains and subdomains** `iruali.mv`,
   `test.iruali.mv`; **Return URLs** the two Apple URLs above. Save.
3. **Keys → +**: name it, tick **Sign in with Apple → Configure** with the same App ID, register,
   and **download the `.p8` file** (Apple lets you download it only once). The Key ID shown is
   `APPLE_KEY_ID`.
4. Your **Team ID** is in the top right of the developer account (Membership details):
   `APPLE_TEAM_ID`.
5. Put the `.p8` on the server outside the web root, e.g. `storage/app/private/AuthKey_….p8`,
   readable only by the site user (`chmod 600`), and set `APPLE_PRIVATE_KEY_PATH` to it (relative
   to the app folder, or absolute). Or paste the file's contents into `APPLE_PRIVATE_KEY`.
6. **Emails to Apple's private relay**: customers may choose "Hide My Email", giving an address
   like `x7k2@privaterelay.appleid.com`. Apple only forwards mail to those addresses from senders
   you register: **Services → Sign in with Apple for Email Communication → Configure**, add the
   domain `iruali.mv` (or the exact `MAIL_FROM_ADDRESS`), and make sure SPF passes for it.
   Without this, order emails to those customers are dropped.

The client secret Apple wants is made on the fly for each sign-in (an ES256 token signed with the
`.p8` key, valid five minutes), so it never needs renewing. Apple sends the customer's name only
the first time they use Apple with iruali; later sign-ins keep the name the account already has.

## How accounts are matched

- A provider account that signed in before goes straight to the same iruali account (matched by
  the provider's own id, the `subject` in `social_accounts`, so a changed email does not matter).
- Otherwise, when an iruali account already has that email, it is joined **only if the provider
  says the email is verified** (Google and Apple do). Facebook does not say, so a Facebook sign-in
  never joins an existing account: the customer is told to sign in with their password instead.
- Otherwise a new **customer** account is made with the name and email from the provider. It has
  no password of its own: My Account → Security offers "Email me a link to set a password". A
  Facebook-made account verifies its email with the usual emailed code.
- **Staff** (admin, support, finance) can never use these buttons; they are told to use their
  password (and two-step sign-in). A staff account is never joined to a provider.
- Customers with **two-step sign-in** on get the code page after the provider, as with a password.
- **Banned or inactive** accounts are refused, as with a password.
- A guest's cart moves into the account on sign-in, and the customer returns to the page that
  asked them to sign in (e.g. checkout).
- My Account → Security lists the connected accounts. One can be unlinked while the customer keeps
  a password or another connected account; the last one stays until a password is set.

## Security notes

- Every sign-in carries a random `state` (checked on return, single use, ten minutes), a `nonce`
  checked in the ID token, and PKCE for Google and Facebook (Apple does not document PKCE for the
  web flow). ID tokens are read from the providers' token endpoints over TLS and their issuer,
  audience, expiry and nonce are checked.
- Apple answers with a form post from `appleid.apple.com`. Browsers do not send the session cookie
  with a cross-site post, so only `POST /auth/apple/callback` runs without the session and without
  CSRF protection; it keeps the posted fields for five minutes under a one-time key and sends the
  browser on to the normal callback, where the session's `state` is checked.
