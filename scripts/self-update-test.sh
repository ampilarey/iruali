#!/usr/bin/env bash
# Cron fallback for test.iruali.mv auto-deploy.
#
# Prefer immediate deploy: GitHub Actions calls POST /api/deploy/test-pull
# (see docs/TEST_AUTO_DEPLOY.md). This cron remains the fallback.
#
# Install once (cPanel Terminal):
#   bash /home/iruali/test/scripts/install-self-update-cron-test.sh
#
# Watch:  tail -f ~/self-update-test.log
export HOME="${HOME:-/home/iruali}"
set -uo pipefail

export PATH="$HOME/bin:/usr/local/bin:/opt/cpanel/ea-php84/root/usr/bin:/opt/cpanel/ea-php83/root/usr/bin:/opt/cpanel/ea-php82/root/usr/bin:/opt/cpanel/composer/bin:/usr/bin:/bin:${PATH:-}"
command -v git >/dev/null || { echo "$(date '+%F %T') git not found on PATH"; exit 1; }
command -v curl >/dev/null || { echo "$(date '+%F %T') curl not found on PATH"; exit 1; }

ROOT="${DEPLOY_TEST_ROOT:-$HOME/test}"
REPO="ampilarey/iruali"
PULL="$ROOT/scripts/pull-deploy-test.sh"

cd "$ROOT" || { echo "$(date '+%F %T') cannot cd to $ROOT"; exit 1; }

git fetch origin main --quiet || { echo "$(date '+%F %T') git fetch failed"; exit 1; }

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse FETCH_HEAD)
[ "$LOCAL" = "$REMOTE" ] && exit 0

# Anonymous API — repo is public. Holds if CI is red/running.
CHECKS=$(curl -fsS -m 20 -H 'Accept: application/vnd.github+json' \
    "https://api.github.com/repos/${REPO}/commits/${REMOTE}/check-runs?per_page=100") \
    || { echo "$(date '+%F %T') GitHub API unreachable — will retry next run"; exit 0; }

# The Tests checks decide (PHPUnit, browser, Larastan). Not yet created (seconds after a push): hold,
# never deploy an untested commit. The webhook deploy job itself is left out: when it fails, this cron
# is the fallback that must still deploy.
VERDICT=$(printf '%s' "$CHECKS" | php -r '
  $runs = json_decode(stream_get_contents(STDIN), true)["check_runs"] ?? null;
  if (! is_array($runs)) { echo "unreadable"; exit; }
  $runs = array_filter($runs, fn ($r) => ! str_starts_with((string) ($r["name"] ?? ""), "Webhook pull"));
  if ($runs === []) { echo "none"; exit; }
  foreach ($runs as $r) { if (($r["status"] ?? "") !== "completed") { echo "running"; exit; } }
  foreach ($runs as $r) { if (! in_array($r["conclusion"] ?? "", ["success", "neutral", "skipped"], true)) { echo "red"; exit; } }
  echo "green";
')
case "$VERDICT" in
  green) ;;
  none) echo "$(date '+%F %T') ${REMOTE:0:8}: no CI checks yet — holding."; exit 0 ;;
  running) echo "$(date '+%F %T') ${REMOTE:0:8}: CI still running — holding."; exit 0 ;;
  *) echo "$(date '+%F %T') ${REMOTE:0:8}: CI not green ($VERDICT) — holding."; exit 0 ;;
esac

if [[ ! -x "$PULL" ]]; then
  chmod +x "$PULL" 2>/dev/null || true
fi

exec bash "$PULL" "$REMOTE"
