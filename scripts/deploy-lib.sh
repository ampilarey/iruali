#!/usr/bin/env bash
# Shared steps for scripts/deploy-production.sh and scripts/rollback-production.sh (sourced, not run).
#
# Expects: ROOT (app root with artisan), optional DOCROOT (separate document root). Provides:
#   say <msg>                      timestamped line
#   deploy_log <kind> <k=v>...     append one line to storage/app/deploys.log
#   last_deploy_previous           the "previous=" commit of the last successful deploy (for rollback)
#   build_app                      composer install --no-dev, migrate, caches, storage:link, docroot sync, stamp, queue:restart
#   rebuild_app_without_migrate    same but no migrate (rollback: migrations are never reversed)
#   checkout_commit <ref>          detached checkout of a tag or commit
#   verify_site                    /api/health + php artisan iruali:smoke (and --place-order when SMOKE_USER_EMAIL is set)
#   describe_head                  tag name when HEAD is exactly a tag, else the short commit

export PATH="${HOME:-/home/iruali}/bin:/usr/local/bin:/opt/cpanel/ea-php84/root/usr/bin:/opt/cpanel/ea-php83/root/usr/bin:/opt/cpanel/ea-php82/root/usr/bin:/opt/cpanel/composer/bin:/usr/bin:/bin:${PATH:-}"

say() { echo "$(date '+%F %T') $*"; }

DEPLOY_LOG="${ROOT}/storage/app/deploys.log"

deploy_who() {
  echo "${DEPLOY_BY:-${SUDO_USER:-${USER:-$(id -un 2>/dev/null || echo unknown)}}}@$(hostname -s 2>/dev/null || echo server)"
}

# deploy_log <kind> key=value...   e.g. deploy_log deploy tag=v2026.10.01 commit=abc previous=def status=ok
deploy_log() {
  mkdir -p "$(dirname "$DEPLOY_LOG")"
  local kind="$1"; shift
  echo "$(date -u +%FT%TZ) $kind $* by=$(deploy_who)" >> "$DEPLOY_LOG"
}

# The commit that was running before the last successful deploy (what rollback goes back to).
last_deploy_previous() {
  [[ -f "$DEPLOY_LOG" ]] || return 1
  local line name
  line=$(grep -E '^[^ ]+ deploy .*status=ok' "$DEPLOY_LOG" | tail -1)
  [[ -n "$line" ]] || return 1
  # Prefer the tag name when the previous release was one; else the short commit.
  name=$(echo "$line" | sed -n 's/.*previous_name=\([^ ]*\).*/\1/p')
  if [[ -n "$name" ]] && git -C "$ROOT" rev-parse -q --verify "refs/tags/$name^{commit}" >/dev/null; then
    echo "$name"
  else
    echo "$line" | sed -n 's/.*previous=\([^ ]*\).*/\1/p'
  fi
}

describe_head() {
  git -C "$ROOT" describe --tags --exact-match HEAD 2>/dev/null || git -C "$ROOT" rev-parse --short=7 HEAD
}

checkout_commit() {
  git -C "$ROOT" checkout -q --detach "$1"
}

_composer_install() {
  composer install --no-dev --optimize-autoloader --no-interaction --no-progress
}

_caches() {
  php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache
}

_storage_link() {
  if [[ ! -e "$ROOT/public/storage" ]]; then
    php artisan storage:link || say "WARN: storage:link failed"
  fi
}

_sync_docroot() {
  [[ -n "${DOCROOT:-}" ]] || return 0
  [[ -d "$DOCROOT" ]] || { say "docroot $DOCROOT does not exist"; return 1; }
  mkdir -p "$DOCROOT/build" "$DOCROOT/images"
  cp -a "$ROOT/public/build/." "$DOCROOT/build/"
  [[ -d "$ROOT/public/images" ]] && cp -a "$ROOT/public/images/." "$DOCROOT/images/"
  # Uploaded files are served by the app at /storage/... (LiteSpeed refuses to follow a docroot
  # symlink into the app folder). Any leftover storage link/dir in the docroot would shadow that route.
  if [[ -L "$DOCROOT/storage" ]]; then rm -f "$DOCROOT/storage"; fi
  if [[ -d "$DOCROOT/storage" ]]; then rmdir "$DOCROOT/storage" 2>/dev/null || say "WARNING: $DOCROOT/storage is a non-empty directory and will shadow /storage/* uploads"; fi
  mkdir -p "$ROOT/storage/app/public" && echo "ok $(date -u +%FT%TZ)" > "$ROOT/storage/app/public/healthcheck.txt"
  for f in favicon.svg site.webmanifest robots.txt .htaccess; do
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
}

_stamp_and_restart() {
  bash "$ROOT/scripts/write-deploy-stamp.sh" "$ROOT" || say "WARN: deploy stamp not written"
  php artisan queue:restart >/dev/null 2>&1 || say "WARN: queue:restart failed"
}

# Full build of the checked-out code. Returns non-zero on the first failing step.
build_app() {
  ( cd "$ROOT" \
    && _composer_install \
    && php artisan migrate --force \
    && _caches \
    && _storage_link \
    && _sync_docroot \
    && _stamp_and_restart )
}

# Rollback build: everything except migrate (the database is left as it is).
rebuild_app_without_migrate() {
  ( cd "$ROOT" \
    && _composer_install \
    && _caches \
    && _storage_link \
    && _sync_docroot \
    && _stamp_and_restart )
}

app_url() {
  grep -E '^APP_URL=' "$ROOT/.env" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"
}

# Health endpoint must answer and report the running commit, then the smoke test must pass.
verify_site() {
  local url expected health
  url="$(app_url)"
  expected="$(git -C "$ROOT" rev-parse --short=7 HEAD)"
  if [[ -z "$url" ]]; then
    say "WARN: APP_URL not set in .env; skipping the health check and smoke test"
    return 0
  fi
  sleep 2
  health=$(curl -fsS -m 20 "$url/api/health" 2>&1) || { say "health check $url/api/health failed: $health"; return 1; }
  say "health: $health"
  echo "$health" | grep -q "\"commit\":\"${expected}" || { say "health reports a different commit than ${expected}"; return 1; }

  local smoke=(php artisan iruali:smoke "--base-url=$url")
  if grep -qE '^SMOKE_USER_EMAIL=.+' "$ROOT/.env" 2>/dev/null; then
    smoke+=(--place-order)
  else
    say "note: set SMOKE_USER_EMAIL/SMOKE_USER_PASSWORD and run 'php artisan iruali:smoke --setup' to also smoke-test checkout"
  fi
  ( cd "$ROOT" && "${smoke[@]}" ) || { say "smoke test failed"; return 1; }
}
