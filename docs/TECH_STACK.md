# 🛠️ Iruali - Tech Stack Documentation

## 📋 Overview

This page lists what the Iruali marketplace is actually built with. The source of truth is
`composer.json` / `composer.lock` and `package.json` / `package-lock.json`; keep this page in step with them.

## 🏗️ Architecture Overview

### Backend Architecture
- **Framework**: Laravel 12 (PHP 8.4 in production and CI; `composer.json` requires `^8.2`)
- **Pattern**: Model-View-Controller with a service layer (`app/Services`)
- **Database**: MySQL / MariaDB in production and for the test suite; SQLite for local development (`.env.example`)
- **Authentication**: Session auth for the website, Laravel Sanctum tokens for the JSON API, optional TOTP 2FA
- **File Storage**: Local disk (`storage/app/public`), served through `/storage/{path}` by `StorageController` because the cPanel host does not follow the docroot symlink

### Frontend Architecture
- **Templates**: Blade (server-rendered), with small vanilla-JS enhancements in `resources/js`
- **CSS Framework**: TailwindCSS 4 via the `@tailwindcss/vite` plugin (no PostCSS config or Tailwind v3 plugins)
- **Build Tool**: Vite 6 with `laravel-vite-plugin`; `npm run build` also runs `fix-manifest.sh` and the result in `public/build/` is committed
- **Responsive**: Mobile-first layouts; RTL support for Dhivehi

## 🔧 Backend Technologies

### Core Framework
```json
{
  "php": "^8.2",
  "laravel/framework": "^12.0"
}
```

Laravel features in use: Eloquent with soft deletes, route model binding, form requests and policies,
the notification system (mail), the scheduler (`routes/console.php`), queued jobs with the `sync`/`database`
drivers, and Artisan commands for maintenance (`orders:cancel-unpaid-card`, `products:generate-slugs`, `mail:test`).

### Production Dependencies

| Package | Version constraint | Used for |
| --- | --- | --- |
| `laravel/sanctum` | `^4.1` | Bearer tokens for `/api/v1/*`; tokens expire after 30 days and `sanctum:prune-expired` runs daily |
| `pragmarx/google2fa` | `^8.0` | TOTP two-step sign-in (`/profile/2fa/*`, `/2fa/verify`) |
| `bacon/bacon-qr-code` | `^3.0` | QR code shown when enabling 2FA |
| `spatie/laravel-translatable` | `^6.11` | English/Dhivehi product and category fields |
| `intervention/image` | `^3.11` | Resizing and converting uploaded product images |
| `spatie/laravel-backup` | `^9.3` | Nightly database backup (`backup:run --only-db`, `backup:clean`) to the `local` disk per `config/backup.php` |
| `guzzlehttp/guzzle` | `^7.9` | HTTP client used for the BML Connect payment gateway (`App\Services\BmlConnect`) |
| `laravel/tinker` | `^2.10` | REPL |

Roles and permissions are the app's own `Role` / `Permission` models (`role:` middleware), not a package.
There is no PDF, spreadsheet, reCAPTCHA or social-login package.

### Development Dependencies

| Package | Used for |
| --- | --- |
| `phpunit/phpunit` `^11.5` | Test suite (`php artisan test`); tests use PHPUnit attributes, not Pest |
| `laravel/pint` | Code style (`./vendor/bin/pint`) |
| `fakerphp/faker`, `mockery/mockery` | Factories and mocks |
| `laravel/pail` | Log tailing (`composer dev`) |
| `laravel/sail` | Optional Docker environment (not used by the deploy scripts) |
| `nunomaduro/collision` | Error output |

## 🎨 Frontend Technologies

```json
{
  "devDependencies": {
    "@tailwindcss/vite": "^4.0.0",
    "concurrently": "^9.0.1",
    "laravel-vite-plugin": "^1.2.0",
    "tailwindcss": "^4.1.11",
    "vite": "^6.2.4"
  },
  "dependencies": {
    "sweetalert2": "^11.22.2"
  }
}
```

- **TailwindCSS 4**: brand palette, fonts and animations are defined in `tailwind.config.js`; `resources/css/app.css` holds the component classes
- **SweetAlert2**: the flash/notification dialogs driven by `resources/js/notifications.js`
- **Vite 6**: `npm run dev` for HMR, `npm run build` for production assets (`vite.config.js` sets `base: '/iruali/public/'` only when `NODE_ENV=production`, matching the cPanel layout)
- No JavaScript framework (no Alpine, Vue or React) and no axios: the pages are server-rendered Blade with plain `fetch` where needed

## 🗄️ Database

- **Production**: MySQL / MariaDB
- **Tests**: MySQL database `iruali_test` (user `root`, empty password) hard-coded in `phpunit.xml`, with `RefreshDatabase`
- **Local development**: SQLite (`database/database.sqlite`), as set up by `.env.example`
- Schema is managed by migrations in `database/migrations`; seeders create reference data (permissions, roles, categories, banners, islands) and, outside production, demo shops and an admin user

Features relied on: foreign keys, JSON columns for translatable fields and product attributes, soft deletes on
products, orders and users, and `lockForUpdate` on vouchers and payout rows.

## 🔐 Security

- CSRF protection, escaped Blade output and Eloquent parameter binding (Laravel defaults)
- `role:admin` / `role:seller` middleware on the admin and seller areas, policies for orders, products and vouchers
- Throttling on login, registration, OTP, password reset, reviews, questions and the BML webhook
- Signed URLs for public order tracking
- Session auth for the site; Sanctum bearer tokens with abilities for the API
- Optional TOTP 2FA with recovery codes; email OTP for verification

## 💳 Payments

Card payments only, through **BML Connect** (Bank of Maldives): redirect to BML's hosted page, return URL and
signed webhook handled by `App\Http\Controllers\Customer\BmlPaymentController` and `App\Services\PaymentService`.
See `PAYMENTS_BML.md`. Enabled by setting `BML_API_KEY`.

## 🔄 Development Workflow

```bash
# Development
npm run dev          # Vite dev server with HMR
php artisan serve    # Laravel development server
composer dev         # server + queue listener + pail + vite together

# Checks
php artisan test     # needs the iruali_test MySQL database
./vendor/bin/pint    # code style (run on the files you touch)

# Production assets (commit public/build/ afterwards)
npm run build
```

Deployment: `scripts/deploy-production.sh` (see `PRODUCTION_DEPLOY.md`) and the TEST auto-deploy in `TEST_AUTO_DEPLOY.md`.

## 🔧 Configuration

Key environment variables (see `.env.example` for the full list):

```env
APP_NAME="Iruali"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iruali.mv

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=iruali_production

SANCTUM_STATEFUL_DOMAINS=iruali.mv,www.iruali.mv
SANCTUM_EXPIRATION=43200

# BML Connect
BML_API_KEY=
BML_ENVIRONMENT=sandbox
BML_WEBHOOK_SECRET=

# Admin seeded by `php artisan db:seed --class=UserSeeder`
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

The scheduler (`* * * * * php artisan schedule:run`) must run on the server for unpaid-order cancellation,
backups, token pruning and guest-cart cleanup (`routes/console.php`).

---

**Laravel Version**: 12.x  
**PHP Version**: 8.4 (8.2+ required)
