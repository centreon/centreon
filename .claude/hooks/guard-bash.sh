#!/usr/bin/env bash
# PreToolUse (Bash): block the shortcuts that cost review cycles, whatever the
# position of the flag in the command (permission deny rules only match a
# prefix). Exit 2 blocks the command and shows the reason to Claude.
# Fails open when jq is missing.
set -euo pipefail

block() {
  printf 'Blocked by .claude/hooks/guard-bash.sh: %s\n' "$1" >&2
  exit 2
}

if ! command -v jq > /dev/null 2>&1; then
  exit 0
fi

cmd="$(jq -r '.tool_input.command // empty' 2> /dev/null || true)"
if [[ -z "${cmd}" ]]; then
  exit 0
fi

# Git hooks run gitleaks: never skip them.
if [[ "${cmd}" =~ (^|[^[:alnum:]_-])git[[:space:]].*(commit|push) && "${cmd}" =~ (^|[[:space:]])--no-verify([[:space:]]|$) ]]; then
  block 'do not bypass the git hooks with --no-verify; fix what the hook reports.'
fi

# Visual snapshots: update one spec at a time, after reviewing the diff.
if [[ "${cmd}" =~ cypress:run:updateSnapshots?|updateSnapshots=true ]]; then
  if [[ ! "${cmd}" =~ --spec[[:space:]=]+[^[:space:]]+ ]]; then
    block 'update snapshots for one spec only (--spec <file>), after reviewing its diff.'
  fi
  if [[ "${cmd}" =~ --spec[[:space:]=]+[^[:space:]]*[*,] ]]; then
    block 'update snapshots for one spec only, not a glob or a list.'
  fi
fi
