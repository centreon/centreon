#!/usr/bin/env bash
# PreToolUse (Bash): block the shortcuts that cost review cycles, wherever
# they appear in the command (permission deny rules only match a prefix):
# skipping the git hooks, force pushes, and snapshot updates beyond one spec.
# Exit 2 blocks the command and shows the reason to Claude. Fails open, with a
# one-time notice, when jq is missing. Tests: .claude/hooks/test-hooks.sh.
set -euo pipefail

# Characters that cannot be part of a flag or a word next to it.
B='[^[:alnum:]_-]'
RE_GIT_COMMIT="(^|${B})git([[:space:]].*)?[[:space:]]commit(${B}|$)"
RE_GIT_PUSH="(^|${B})git([[:space:]].*)?[[:space:]]push(${B}|$)"
RE_NO_VERIFY="(^|${B})--no-veri[[:alpha:]]*(${B}|$)"
RE_SHORT_N="(^|[[:space:]])-[[:alpha:]]*n[[:alpha:]]*(${B}|$)"
RE_HOOKS_PATH='core\.hooksPath'
RE_FORCE="(^|[[:space:]])(--force[^[:space:]]*|-[[:alpha:]]*f[[:alpha:]]*|\+[^[:space:]]+)(${B}|$)"
RE_SNAPSHOT_UPDATE='cypress:run:updateSnapshots?|updateSnapshots=true'
RE_SPEC='(^|[[:space:]])(--spec|-s)([[:space:]]+|=)([^[:space:]]+)'
RE_SPEC_FILE='^[^*?,{[]+\.(ts|tsx|js|jsx)$'

block() {
  printf 'Blocked by .claude/hooks/guard-bash.sh: %s\n' "$1" >&2
  exit 2
}

notify_once() {
  local marker="${TMPDIR:-/tmp}/claude-hooks-$1"
  if [[ ! -e "${marker}" ]]; then
    : > "${marker}" 2> /dev/null || true
    printf '{"systemMessage":"%s"}\n' "$2"
  fi
}

check_segment() {
  local segment="$1" spec rest

  if [[ "${segment}" =~ ${RE_GIT_COMMIT} || "${segment}" =~ ${RE_GIT_PUSH} ]]; then
    if [[ "${segment}" =~ ${RE_NO_VERIFY} || "${segment}" =~ ${RE_HOOKS_PATH} ]]; then
      block 'do not bypass the git hooks (gitleaks); fix what the hook reports.'
    fi
  fi
  if [[ "${segment}" =~ ${RE_GIT_COMMIT} && "${segment}" =~ ${RE_SHORT_N} ]]; then
    block 'git commit -n skips the git hooks (gitleaks); fix what the hook reports.'
  fi
  if [[ "${segment}" =~ ${RE_GIT_PUSH} && "${segment}" =~ ${RE_FORCE} ]]; then
    block 'never force push; integrate the remote changes instead.'
  fi

  if [[ "${segment}" =~ ${RE_SNAPSHOT_UPDATE} ]]; then
    if [[ ! "${segment}" =~ ${RE_SPEC} ]]; then
      block 'update snapshots for one spec only (--spec <file>), after reviewing its diff.'
    fi
    spec="${BASH_REMATCH[4]}"
    spec="${spec//\"/}"
    spec="${spec//\'/}"
    rest="${segment#*"${BASH_REMATCH[0]}"}"
    if [[ ! "${spec}" =~ ${RE_SPEC_FILE} || "${rest}" =~ ${RE_SPEC} ]]; then
      block 'update snapshots for one spec file only, not a directory, a glob or a list.'
    fi
  fi
}

input="$(cat)"
if ! command -v jq > /dev/null 2>&1; then
  notify_once "guard-nojq-$(date +%Y%m%d)" 'guard-bash.sh is inactive: jq is not installed.'
  exit 0
fi

cmd="$(printf '%s' "${input}" | jq -r '.tool_input.command // empty' 2> /dev/null || true)"
if [[ -z "${cmd}" ]]; then
  exit 0
fi

# Join line continuations, then check each command of the chain separately.
cmd="${cmd//\\$'\n'/ }"
while IFS= read -r segment; do
  check_segment "${segment}"
done <<< "${cmd//[;&|]/$'\n'}"
