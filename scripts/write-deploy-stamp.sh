#!/usr/bin/env bash
# Write storage/app/deploy-stamp.json so /api/health can report what is running.
# Usage: write-deploy-stamp.sh <repo-root>
# Called from pull-deploy-test.sh after every deploy (and no-op pulls).
set -euo pipefail

ROOT="${1:-}"
if [[ -z "$ROOT" || ! -d "$ROOT" ]]; then
  echo "write-deploy-stamp: repo root required" >&2
  exit 1
fi

FULL=$(git -C "$ROOT" rev-parse HEAD)
SHORT=$(git -C "$ROOT" rev-parse --short=7 HEAD)
BRANCH=$(git -C "$ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)
TS=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

mkdir -p "${ROOT}/storage/app"
# Atomic write so readers never see a partial JSON file.
TMP="${ROOT}/storage/app/deploy-stamp.json.tmp.$$"
cat > "$TMP" <<JSON
{"commit":"${FULL}","commit_short":"${SHORT}","branch":"${BRANCH}","deployed_at":"${TS}"}
JSON
mv -f "$TMP" "${ROOT}/storage/app/deploy-stamp.json"
