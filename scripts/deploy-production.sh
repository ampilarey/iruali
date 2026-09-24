#!/usr/bin/env bash
# One-command PRODUCTION deploy for iruali.mv (cPanel). Production is never auto-deployed;
# run this by hand in cPanel Terminal after a merge to main has been checked on test.iruali.mv.
#
#   bash scripts/deploy-production.sh /home/iruali/<app-folder> [/home/iruali/<docroot-folder>]
#
#   arg 1  APP ROOT   the Laravel checkout (the folder with artisan and .env)          required
#   arg 2  DOCROOT    only if the domain's document root is a separate folder from
#                     <app root>/public: built assets, favicon and index.php are synced there
#
# What it does: maintenance mode → git pull (fast-forward main only) → composer install
# (--no-dev) → migrate → caches → docroot sync → deploy stamp → up → /api/health check.
# On failure it brings the site back up and prints the command to roll back.
export HOME="${HOME:-/home/iruali}"
set -uo pipefail

export PATH="$HOME/bin:/usr/local/bin:/opt/cpanel/ea-php84/root/usr/bin:/opt/cpanel/ea-php83/root/usr/bin:/opt/cpanel/ea-php82/root/usr/bin:/opt/cpanel/composer/bin:/usr/bin:/bin:${PATH:-}"

ROOT="${1:-}"
DOCROOT="${2:-}"
if [[ -z "$ROOT" || ! -f "$ROOT/artisan" ]]; then
  echo "Usage: bash scripts/deploy-production.sh <app root with artisan> [separate docroot]" >&2
  exit 1
fi
for cmd in php git composer curl; do
  command -v "$cmd" >/dev/null || { echo "ERROR: $cmd not found on PATH=$PATH" >&2; exit 1; }
done

cd "$ROOT" || exit 1
say() { echo "$(date '+%F %T') $*"; }

if grep -q '^TEST_DEPLOY_WEBHOOK_SECRET=.\+' .env 2>/dev/null; then
  say "WARNING: this .env has TEST_DEPLOY_WEBHOOK_SECRET set. Is this really production?"
fi

git fetch origin main --quiet || { say "git fetch failed"; exit 1; }
PREV=$(git rev-parse HEAD)
NEXT=$(git rev-parse FETCH_HEAD)
if [[ "$PREV" == "$NEXT" ]]; then
  say "already on ${PREV:0:8}; refreshing caches only"
fi

fail() {
  say "DEPLOY FAILED: $1"
  php artisan up >/dev/null 2>&1 || true
  if [[ "$(git rev-parse HEAD)" != "$PREV" ]]; then
    say "To roll back the code:  cd $ROOT && git reset --hard $PREV && composer install --no-dev --optimize-autoloader && php artisan config:cache"
    say "(Database migrations are not rolled back automatically.)"
  fi
  exit 1
}

say "deploying ${PREV:0:8} -> ${NEXT:0:8}"
php artisan down --retry=30 >/dev/null 2>&1 || say "WARN: could not enter maintenance mode"

git merge --ff-only FETCH_HEAD || fail "fast-forward failed (local changes on the server?)"

composer install --no-dev --optimize-autoloader --no-interaction || fail "composer install"

php artisan migrate --force || fail "migrate"
php artisan storage:link --force >/dev/null 2>&1 || say "WARN: storage:link failed"
php artisan config:cache || fail "config:cache"
php artisan route:clear >/dev/null
php artisan view:clear >/dev/null
php artisan cache:clear >/dev/null 2>&1 || true

if [[ -n "$DOCROOT" ]]; then
  [[ -d "$DOCROOT" ]] || fail "docroot $DOCROOT does not exist"
  mkdir -p "$DOCROOT/build" "$DOCROOT/images"
  cp -a "$ROOT/public/build/." "$DOCROOT/build/"
  cp -a "$ROOT/public/images/." "$DOCROOT/images/"
  for f in favicon.svg site.webmanifest .htaccess; do
    [[ -f "$ROOT/public/$f" ]] && cp -a "$ROOT/public/$f" "$DOCROOT/$f"
  done
  # Front controller in the docroot points at the app root
  cat > "$DOCROOT/index.php" <<PHP
<?php
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
if (file_exists(\$maintenance = '$ROOT/storage/framework/maintenance.php')) {
    require \$maintenance;
}
require '$ROOT/vendor/autoload.php';
/** @var Application \$app */
\$app = require_once '$ROOT/bootstrap/app.php';
\$app->handleRequest(Request::capture());
PHP
  say "synced public files -> $DOCROOT"
fi

bash "$ROOT/scripts/write-deploy-stamp.sh" "$ROOT" || say "WARN: deploy stamp not written"

php artisan up || fail "php artisan up"

APP_URL=$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"')
if [[ -n "$APP_URL" ]]; then
  sleep 2
  HEALTH=$(curl -fsS -m 20 "$APP_URL/api/health" 2>&1) || fail "health check $APP_URL/api/health: $HEALTH"
  say "health: $HEALTH"
  echo "$HEALTH" | grep -q "\"commit\":\"${NEXT:0:7}" || say "WARN: health reports a different commit than ${NEXT:0:7}"
fi

say "deploy complete: ${NEXT:0:8}"
