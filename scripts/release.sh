#!/usr/bin/env bash
# Cut a release: an annotated v* tag on main, pushed to origin. Run on your machine, not the server.
#
#   bash scripts/release.sh [vYYYY.MM.DD[.N]] [-m "message"]
#
# Default tag: today's date (v2026.10.01); if that tag exists already, .2, .3, ... is appended.
# Checks: on main, clean tree, main == origin/main, and (when GITHUB_TOKEN or GH_TOKEN is set)
# every GitHub check run for that commit has finished green. Without a token it just tags and
# says so. Pushing the tag starts .github/workflows/release.yml (tests + GitHub Release);
# production is then deployed by hand: bash scripts/deploy-production.sh <tag> on the server.
set -euo pipefail

TAG=""
MESSAGE=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    -m|--message) MESSAGE="${2:-}"; shift 2 ;;
    -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
    v*) TAG="$1"; shift ;;
    *) echo "Unknown argument: $1" >&2; exit 1 ;;
  esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

say() { echo "release: $*"; }

BRANCH=$(git rev-parse --abbrev-ref HEAD)
[[ "$BRANCH" == "main" ]] || { say "ERROR: releases are cut from main (you are on $BRANCH)"; exit 1; }
[[ -z "$(git status --porcelain --untracked-files=no)" ]] || { say "ERROR: commit or stash your changes first"; git status --short --untracked-files=no; exit 1; }

say "fetching origin"
git fetch --tags --prune origin --quiet
HEAD_SHA=$(git rev-parse HEAD)
REMOTE_SHA=$(git rev-parse origin/main)
[[ "$HEAD_SHA" == "$REMOTE_SHA" ]] || { say "ERROR: main (${HEAD_SHA:0:8}) differs from origin/main (${REMOTE_SHA:0:8}); pull or push first"; exit 1; }

if [[ -z "$TAG" ]]; then
  TAG="v$(date -u +%Y.%m.%d)"
  if git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
    n=2
    while git rev-parse -q --verify "refs/tags/$TAG.$n" >/dev/null; do n=$((n + 1)); done
    TAG="$TAG.$n"
  fi
fi
[[ "$TAG" =~ ^v[0-9]{4}\.[0-9]{2}\.[0-9]{2}(\.[0-9]+)?$ ]] || { say "ERROR: tag must look like v2026.10.01 or v2026.10.01.2 (got $TAG)"; exit 1; }
if git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
  say "ERROR: tag $TAG already exists ($(git rev-parse --short "refs/tags/$TAG^{commit}"))"; exit 1
fi

# --- CI must be green for this commit (GitHub API; skipped without a token) -------------------
TOKEN="${GITHUB_TOKEN:-${GH_TOKEN:-}}"
REMOTE_URL=$(git remote get-url origin)
REPO=$(echo "$REMOTE_URL" | sed -E 's#^(git@github\.com:|https://github\.com/)##; s#\.git$##')
if [[ -n "$TOKEN" && "$REPO" == */* ]]; then
  say "checking GitHub check runs for ${HEAD_SHA:0:8} on $REPO"
  JSON=$(curl -fsS -m 30 -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/$REPO/commits/$HEAD_SHA/check-runs?per_page=100") || { say "ERROR: GitHub API call failed"; exit 1; }
  if command -v python3 >/dev/null; then
    VERDICT=$(printf '%s' "$JSON" | python3 -c '
import json, sys
runs = json.load(sys.stdin).get("check_runs", [])
if not runs:
    print("none"); sys.exit()
bad = [r for r in runs if r.get("status") != "completed" or r.get("conclusion") not in ("success", "neutral", "skipped")]
print("ok" if not bad else "bad " + ", ".join(f"{r.get(\"name\")}={r.get(\"status\")}/{r.get(\"conclusion\")}" for r in bad))
')
  elif command -v jq >/dev/null; then
    VERDICT=$(printf '%s' "$JSON" | jq -r '
      if (.check_runs | length) == 0 then "none"
      else ([.check_runs[] | select(.status != "completed" or (.conclusion | IN("success","neutral","skipped") | not)) | "\(.name)=\(.status)/\(.conclusion)"]
            | if length == 0 then "ok" else "bad " + join(", ") end) end')
  else
    VERDICT="unknown"
  fi
  case "$VERDICT" in
    ok) say "CI is green for ${HEAD_SHA:0:8}" ;;
    none) say "ERROR: no check runs found for ${HEAD_SHA:0:8}; has the Tests workflow run? (push main and wait)"; exit 1 ;;
    bad*) say "ERROR: CI is not green: ${VERDICT#bad }"; exit 1 ;;
    *) say "WARN: could not read the check runs (no python3 or jq); tagging anyway" ;;
  esac
else
  say "no GITHUB_TOKEN / GH_TOKEN: skipping the CI check (release.yml will run the tests on the tag anyway)"
fi

[[ -n "$MESSAGE" ]] || MESSAGE="Release $TAG"
git tag -a "$TAG" -m "$MESSAGE" "$HEAD_SHA"
git push origin "refs/tags/$TAG"

say "tagged ${HEAD_SHA:0:8} as $TAG and pushed. GitHub will run the tests and publish the release notes."
say "deploy it on the server when ready:  bash scripts/deploy-production.sh $TAG"
