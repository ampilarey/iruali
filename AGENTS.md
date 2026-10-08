# AGENTS.md

## Cursor Cloud specific instructions

This is a **Laravel 12 (PHP 8.2+) multi-vendor e-commerce app** ("Iruali") with a Vite 6 + Tailwind 4 frontend. There is a single web service plus its supporting datastores.

### Services & how to run them
- **Web app (Laravel):** `php artisan serve --host=0.0.0.0 --port=8000`. Serves the storefront at `http://127.0.0.1:8000` (homepage lists seeded products; `/login` for auth; admin dashboard under `/admin`).
- **Frontend assets (Vite dev server):** `npm run dev` (HMR on port 5173). The Blade layouts use `@vite(...)`, so for local browsing either run `npm run dev` or `npm run build` first, otherwise pages that reference the Vite manifest will error. Do **not** run `npm run dev` and `serve` in a way that blocks; run them in the background (e.g. tmux).
- `composer dev` runs server + queue listener + `pail` logs + vite concurrently; usually overkill — running `serve` + `npm run dev` is enough for manual testing.

### Environment gotchas (non-obvious)
- `.env.example` is a local-dev template (SQLite). Copy it to `.env` and run `php artisan key:generate`; the environment setup may already have created a working `.env` (gitignored).
- **`public/index.php`, `public/.htaccess`, and `public/build/` are tracked** (needed for cPanel Git deploys). A leftover Gatsby-style `public` ignore used to drop the whole folder from Git, which caused production `ViteManifestNotFoundException` on iruali.mv — that ignore is removed. Still ignored: `public/hot`, `public/storage`. After frontend changes, run `npm run build` (runs `fix-manifest.sh` to copy `.vite/manifest.json` → `build/manifest.json`) and commit `public/build/`.
- Runtime dirs `storage/framework/{cache,sessions,views}` and `storage/app/public` are not tracked and are created during setup. Run `php artisan storage:link` after they exist.
- **Dev database is SQLite** at `database/database.sqlite` (gitignored). Recreate with `touch database/database.sqlite` then `php artisan migrate --seed`. Seeders create an admin user `admin@example.com` / `password` plus sample categories/products/islands.

### Tests
- Run with `php artisan test` or `./vendor/bin/phpunit`.
- **`phpunit.xml` hardcodes MySQL** (`DB_CONNECTION=mysql`, `DB_DATABASE=iruali_test`, user `root`, empty password, host defaults to `127.0.0.1`) — it does **not** use the SQLite dev DB. A local MariaDB server with an empty-password root and an `iruali_test` database must be running for the suite to connect. MariaDB has no systemd here; start it with `sudo mysqld_safe &` (data dir `/var/lib/mysql` is already initialized).
- The full suite is expected to pass (545 tests). A failure is a real regression, not a known pre-existing issue.

### Lint / format / static analysis
- **Laravel Pint**: `./vendor/bin/pint` to fix, `./vendor/bin/pint --test` to check. The whole codebase is Pint-clean and CI (`.github/workflows/tests.yml`) fails on any violation, so run `./vendor/bin/pint` before committing; formatting the files you touched is enough, since everything else already passes.
- **Larastan** (PHPStan level 5 over `app/`): `composer analyse`. It is a locked dev dependency, app/ has zero findings and no ignore rules, and the CI job blocks on any finding. Give every Eloquent relation a generic return docblock (`/** @return BelongsTo<Product, $this> */`), and use `setAttribute()`/`getAttribute()` or `->toBase()` for computed values that are not columns (see `phpstan.neon`). Don't silence findings with ignores, baselines or `@var` overrides.

### Build
- Production assets: `npm run build` (runs `vite build` then `./fix-manifest.sh`). `vite.config.js` sets `base: '/iruali/public/'` when `NODE_ENV=production`, matching the cPanel deploy layout — keep `NODE_ENV` unset/`development` for local builds.
