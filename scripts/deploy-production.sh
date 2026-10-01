#!/usr/bin/env bash
# PRODUCTION deploy for iruali.mv (cPanel) from a tagged release, with automatic rollback.
# Production is never deployed from GitHub; run this by hand in cPanel → Terminal (or over SSH)
# after the change has been checked on test.iruali.mv.
#
#   bash scripts/deploy-production.sh <tag | --latest-tag> [app root] [docroot]
#
#   tag           a release tag such as v2026.10.01 (made by scripts/release.sh)
#   --latest-tag  the newest v* tag on origin
#   app root      the Laravel checkout (folder with artisan and .env); default: the folder this
#                 script lives in, or $DEPLOY_ROOT
#   docroot       only if the domain's document root is a separate folder from <app root>/public:
#                 built assets, favicon, .htaccess and a front-controller index.php are synced there
#                 ($DEPLOY_DOCROOT works too)
#
# Steps: refuse anything that is not a tag → maintenance mode → git fetch --tags → detached checkout
# of the tag → composer install --no-dev → migrate --force → config/route/view/event caches →
# storage:link if missing → docroot sync → deploy stamp → queue:restart → up → /api/health →
# php artisan iruali:smoke. Any failure rolls back to the previous commit (checkout, composer
# install, caches; migrations are NOT reversed) and exits 1. Every run is appended to
# storage/app/deploys.log (tag, commit, previous commit, timestamp, who, result).
export HOME="${HOME:-/home/iruali}"
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TAG_ARG="${1:-}"
ROOT="${2:-${DEPLOY_ROOT:-$(dirname "$SCRIPT_DIR")}}"
DOCROOT="${3:-${DEPLOY_DOCROOT:-}}"

usage() {
  echo "Usage: bash scripts/deploy-production.sh <tag | --latest-tag> [app root] [docroot]" >&2
  exit 1
}
[[ -n "$TAG_ARG" ]] || usage
[[ -f "$ROOT/artisan" ]] || { echo "ERROR: $ROOT has no artisan; pass the app root as the second argument" >&2; exit 1; }

# shellcheck source=scripts/deploy-lib.sh
source "$SCRIPT_DIR/deploy-lib.sh"

for cmd in php git composer curl; do
  command -v "$cmd" >/dev/null || { say "ERROR: $cmd not found on PATH=$PATH"; exit 1; }
done

cd "$ROOT" || exit 1

if grep -q '^TEST_DEPLOY_WEBHOOK_SECRET=.\+' .env 2>/dev/null; then
  say "WARNING: this .env has TEST_DEPLOY_WEBHOOK_SECRET set. Is this really production?"
fi

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
  say "ERROR: the server checkout has local changes; commit or discard them first:"
  git status --short --untracked-files=no
  exit 1
fi

say "fetching tags"
git fetch --tags --prune origin --quiet || { say "git fetch failed"; exit 1; }

if [[ "$TAG_ARG" == "--latest-tag" ]]; then
  TAG=$(git tag -l 'v*' --sort=-v:refname | head -1)
  [[ -n "$TAG" ]] || { say "ERROR: no v* tag exists yet (make one with scripts/release.sh)"; exit 1; }
else
  TAG="$TAG_ARG"
fi

# Only a tag may be deployed: a branch name or a bare commit is refused.
if ! git rev-parse -q --verify "refs/tags/$TAG^{commit}" >/dev/null; then
  say "ERROR: '$TAG' is not a tag. Production deploys only from release tags (scripts/release.sh); use --latest-tag for the newest."
  exit 1
fi

NEXT=$(git rev-parse "refs/tags/$TAG^{commit}")
PREV=$(git rev-parse HEAD)
PREV_NAME=$(describe_head)

if [[ "$PREV" == "$NEXT" ]]; then
  say "already on $TAG (${NEXT:0:8}); rebuilding caches and re-running the checks"
fi

deploy_log deploy "tag=$TAG commit=${NEXT:0:7} previous=${PREV:0:7} previous_name=$PREV_NAME status=started"

rollback() {
  say "DEPLOY FAILED: $1"
  if [[ "$(git rev-parse HEAD)" != "$PREV" || "$PREV" == "$NEXT" ]]; then
    say "rolling back to $PREV_NAME (${PREV:0:8})"
    if checkout_commit "$PREV" && rebuild_app_without_migrate; then
      say "rolled back to ${PREV:0:8}. Database migrations from $TAG were NOT reversed; check them by hand if the release added any."
      deploy_log deploy "tag=$TAG commit=${NEXT:0:7} previous=${PREV:0:7} status=rolled-back reason=\"$1\""
    else
      say "ROLLBACK FAILED. Fix by hand: cd $ROOT && git checkout --detach $PREV && composer install --no-dev --optimize-autoloader && php artisan config:cache route:cache view:cache"
      deploy_log deploy "tag=$TAG commit=${NEXT:0:7} previous=${PREV:0:7} status=rollback-failed reason=\"$1\""
    fi
  else
    deploy_log deploy "tag=$TAG commit=${NEXT:0:7} previous=${PREV:0:7} status=failed reason=\"$1\""
  fi
  php artisan up >/dev/null 2>&1 || true
  exit 1
}

say "deploying $PREV_NAME (${PREV:0:8}) -> $TAG (${NEXT:0:8})"
php artisan down --retry=30 >/dev/null 2>&1 || say "WARN: could not enter maintenance mode"

checkout_commit "refs/tags/$TAG" || rollback "git checkout $TAG"
build_app || rollback "build (composer install / migrate / caches / docroot sync)"
php artisan up || rollback "php artisan up"
verify_site || rollback "health check or smoke test"

deploy_log deploy "tag=$TAG commit=${NEXT:0:7} previous=${PREV:0:7} previous_name=$PREV_NAME status=ok"
say "deploy complete: $TAG (${NEXT:0:8}). Roll back with: bash scripts/rollback-production.sh"
