#!/usr/bin/env bash
# Runs git in <repo> authenticated as the telmoDev GitHub account, whatever account gh has active.
# Usage: bash .orchestrator/scripts/gh-git.sh <repo> <git args>
# The token goes in a one-shot credential helper for this call only; git and gh config are never changed.
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
repo=$1; shift
token=$(gh auth token --user telmoDev)
GH_TOKEN_TELMODEV="$token" git -C "$root/$repo" \
  -c credential.helper= \
  -c 'credential.helper=!f() { echo username=telmoDev; echo "password=$GH_TOKEN_TELMODEV"; }; f' \
  "$@"
