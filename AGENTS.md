# AGENTS.md

## Cursor Cloud specific instructions

This is a **Laravel 12 (PHP 8.2+) multi-vendor e-commerce app** ("Iruali") with a Vite 6 + Tailwind 4 frontend. There is a single web service plus its supporting datastores.

### Services & how to run them
- **Web app (Laravel):** `php artisan serve --host=0.0.0.0 --port=8000`. Serves the storefront at `http://127.0.0.1:8000` (homepage lists seeded products; `/login` for auth; admin dashboard under `/admin`).
- **Frontend assets (Vite dev server):** `npm run dev` (HMR on port 5173). The Blade layouts use `@vite(...)`, so for local browsing either run `npm run dev` or `npm run build` first, otherwise pages that reference the Vite manifest will error. Do **not** run `npm run dev` and `serve` in a way that blocks; run them in the background (e.g. tmux).
- `composer dev` runs server + queue listener + `pail` logs + vite concurrently; usually overkill — running `serve` + `npm run dev` is enough for manual testing.

### Environment gotchas (non-obvious)
- **`.env.example` contains an unresolved git merge conflict.** Do not `cp .env.example .env` blindly. A working local dev `.env` (SQLite, `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000`) is created during environment setup; it is gitignored and persists in the VM snapshot.
- **The entire `public/` directory is gitignored** (`.gitignore` line ~143 `public`), so `public/index.php` / `public/.htaccess` are **not** in the repo. They are recreated during environment setup (standard Laravel 12 front controller). If `public/index.php` is missing, `php artisan serve` returns 404 for everything — recreate it.
- Runtime dirs `storage/framework/{cache,sessions,views}` and `storage/app/public` are not tracked and are created during setup. Run `php artisan storage:link` after they exist.
- **Dev database is SQLite** at `database/database.sqlite` (gitignored). Recreate with `touch database/database.sqlite` then `php artisan migrate --seed`. Seeders create an admin user `admin@example.com` / `password` plus sample categories/products/islands.

### Tests
- Run with `php artisan test` or `./vendor/bin/phpunit`.
- **`phpunit.xml` hardcodes MySQL** (`DB_CONNECTION=mysql`, `DB_DATABASE=iruali_test`, user `root`, empty password, host defaults to `127.0.0.1`) — it does **not** use the SQLite dev DB. A local MariaDB server with an empty-password root and an `iruali_test` database must be running for the suite to connect. MariaDB has no systemd here; start it with `sudo mysqld_safe &` (data dir `/var/lib/mysql` is already initialized).
- As of setup, a subset of tests fail on a clean checkout due to **pre-existing test-code bugs** (e.g. `Cart::factory()` FK violations under `RefreshDatabase`, an undefined `$variantId` in `NotificationSystemTest`, and the default `ExampleTest` hitting `/` without seeding). These are not environment problems — MySQL connects and ~72 tests / 321 assertions pass. Do not "fix" the environment to chase them.

### Lint / format
- **Laravel Pint**: `./vendor/bin/pint` to fix, `./vendor/bin/pint --test` to check. The existing codebase has many pre-existing style violations; only run Pint on files you touch to avoid a huge unrelated diff.

### Build
- Production assets: `npm run build` (runs `vite build` then `./fix-manifest.sh`). `vite.config.js` sets `base: '/iruali/public/'` when `NODE_ENV=production`, matching the cPanel deploy layout — keep `NODE_ENV` unset/`development` for local builds.
