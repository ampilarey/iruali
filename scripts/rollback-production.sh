#!/usr/bin/env bash
# Roll PRODUCTION back to an earlier release by hand.
#
#   bash scripts/rollback-production.sh [tag-or-commit] [app root] [docroot]
#
#   tag-or-commit  where to go back to; default: the commit that was running before the last
#                  successful deploy (the "previous=" of the last status=ok line in
#                  storage/app/deploys.log)
#   app root       default: the folder this script lives in, or $DEPLOY_ROOT
#   docroot        separate document root, if any ($DEPLOY_DOCROOT works too)
#
# Does: maintenance mode → detached checkout → composer install --no-dev → caches → storage:link →
# docroot sync → stamp → queue:restart → up → health + smoke test. Database migrations are NOT
# reversed: if the release you are leaving added a migration, the old code runs against the new
# schema (usually fine, as migrations here only add) — check `php artisan migrate:status` and
# roll the migration back by hand if it must go.
export HOME="${HOME:-/home/iruali}"
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_ARG="${1:-}"
ROOT="${2:-${DEPLOY_ROOT:-$(dirname "$SCRIPT_DIR")}}"
DOCROOT="${3:-${DEPLOY_DOCROOT:-}}"

[[ -f "$ROOT/artisan" ]] || { echo "ERROR: $ROOT has no artisan; pass the app root as the second argument" >&2; exit 1; }

# shellcheck source=scripts/deploy-lib.sh
source "$SCRIPT_DIR/deploy-lib.sh"

for cmd in php git composer curl; do
  command -v "$cmd" >/dev/null || { say "ERROR: $cmd not found on PATH=$PATH"; exit 1; }
done

cd "$ROOT" || exit 1

git fetch --tags --prune origin --quiet || say "WARN: git fetch failed; using what is already on the server"

if [[ -n "$TARGET_ARG" ]]; then
  TARGET_NAME="$TARGET_ARG"
else
  TARGET_NAME=$(last_deploy_previous || true)
  if [[ -z "$TARGET_NAME" ]]; then
    say "ERROR: no successful deploy in $DEPLOY_LOG to go back from; pass a tag or commit:"
    say "  bash scripts/rollback-production.sh v2026.09.30"
    exit 1
  fi
  say "no target given: going back to ${TARGET_NAME} (what ran before the last successful deploy)"
fi

if git rev-parse -q --verify "refs/tags/$TARGET_NAME^{commit}" >/dev/null; then
  TARGET=$(git rev-parse "refs/tags/$TARGET_NAME^{commit}")
elif git rev-parse -q --verify "$TARGET_NAME^{commit}" >/dev/null; then
  TARGET=$(git rev-parse "$TARGET_NAME^{commit}")
else
  say "ERROR: '$TARGET_NAME' is neither a tag nor a commit here"
  exit 1
fi

CURRENT=$(git rev-parse HEAD)
CURRENT_NAME=$(describe_head)

say "WARNING: database migrations are not reversed by a rollback."
say "         Migrations applied since ${TARGET_NAME}:"
git log --oneline --no-merges "$TARGET..$CURRENT" -- database/migrations 2>/dev/null | sed 's/^/           /' || true

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
  say "ERROR: the server checkout has local changes; commit or discard them first"
  exit 1
fi

say "rolling back $CURRENT_NAME (${CURRENT:0:8}) -> $TARGET_NAME (${TARGET:0:8})"
deploy_log rollback "to=$TARGET_NAME commit=${TARGET:0:7} previous=${CURRENT:0:7} status=started"

fail() {
  say "ROLLBACK FAILED: $1"
  deploy_log rollback "to=$TARGET_NAME commit=${TARGET:0:7} previous=${CURRENT:0:7} status=failed reason=\"$1\""
  php artisan up >/dev/null 2>&1 || true
  exit 1
}

php artisan down --retry=30 >/dev/null 2>&1 || say "WARN: could not enter maintenance mode"
checkout_commit "$TARGET" || fail "git checkout $TARGET_NAME"
rebuild_app_without_migrate || fail "build (composer install / caches / docroot sync)"
php artisan up || fail "php artisan up"
verify_site || say "WARN: health check or smoke test failed on the rolled-back code; look at the output above"

deploy_log rollback "to=$TARGET_NAME commit=${TARGET:0:7} previous=${CURRENT:0:7} status=ok"
say "rolled back to $TARGET_NAME (${TARGET:0:8}). Migrations were not reversed (see php artisan migrate:status)."
