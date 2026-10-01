#!/usr/bin/env bash
# Boot the app for the Playwright browser tests: a fresh SQLite database (migrated + seeded),
# BML faked (BML_FAKE=1), a browser-test customer, and PHP's built-in server on
# 127.0.0.1:${BROWSER_TEST_PORT:-8001}. Playwright starts this as its webServer and stops it.
#
#   bash tests/browser/setup.sh            # serve until killed
#   SETUP_ONLY=1 bash tests/browser/setup.sh   # just prepare the database
#
# The dev database (database/database.sqlite) is left alone: the tests use database/browser.sqlite.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

PORT="${BROWSER_TEST_PORT:-8001}"
DB="$ROOT/database/browser.sqlite"

# Environment variables win over .env, and the built-in server inherits them (php artisan serve
# would not pass them on, so it is not used).
export APP_ENV=local APP_DEBUG=true APP_URL="http://127.0.0.1:${PORT}"
export DB_CONNECTION=sqlite DB_DATABASE="$DB"
export BML_FAKE=1 BML_API_KEY=
export SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync MAIL_MAILER=log
export STAFF_REQUIRE_2FA=false APP_MAINTENANCE_DRIVER=file LOG_CHANNEL=single
export SMOKE_USER_EMAIL="${BROWSER_TEST_EMAIL:-browser@iruali.test}" SMOKE_USER_PASSWORD="${BROWSER_TEST_PASSWORD:-browser-pass-123}"

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public bootstrap/cache
if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --no-interaction --quiet
fi
if ! grep -qE '^APP_KEY=.+' .env; then
  php artisan key:generate --no-interaction --quiet
fi
[[ -f public/build/manifest.json ]] || { echo "public/build/manifest.json is missing: run npm run build first" >&2; exit 1; }

php artisan config:clear --quiet
php artisan route:clear --quiet
php artisan view:clear --quiet

rm -f "$DB" && touch "$DB"
echo "browser-test: migrating and seeding $DB"
php artisan migrate:fresh --seed --force --quiet
php artisan iruali:smoke --setup
[[ -e public/storage ]] || php artisan storage:link --quiet || true

if [[ "${SETUP_ONLY:-}" == "1" ]]; then
  echo "browser-test: database ready (SETUP_ONLY=1, not serving)"
  exit 0
fi

echo "browser-test: serving $APP_URL"
# Laravel's router script for the built-in server expects public/ as the working directory
cd "$ROOT/public"
exec php -S "127.0.0.1:${PORT}" "$ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
