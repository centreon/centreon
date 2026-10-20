#!/usr/bin/env bash
# PreToolUse (Bash): block the shortcuts that cost review cycles, wherever
# they appear in the command (permission deny rules only match a prefix):
# skipping the git hooks, force pushes, and snapshot updates beyond one spec.
# Exit 2 blocks the command and shows the reason to Claude. Fails open, with a
# one-time notice, when jq is missing. Tests: .claude/hooks/test-hooks.sh.
# This is a guardrail against shortcuts, not a security boundary.
set -euo pipefail

# Characters that cannot be part of a flag or a word next to it.
B='[^[:alnum:]_-]'
RE_GIT_COMMIT="(^|${B})git([[:space:]].*)?[[:space:]]commit(${B}|$)"
RE_GIT_PUSH="(^|${B})git([[:space:]].*)?[[:space:]]push(${B}|$)"
RE_NO_VERIFY="(^|${B})--no-veri[[:alpha:]]*(${B}|$)"
RE_SHORT_N="(^|[[:space:]])-[[:alpha:]]*n[[:alpha:]]*(${B}|$)"
RE_FORCE="(^|[[:space:]])(--force[^[:space:]]*|--mirror|-[[:alpha:]]*f[[:alpha:]]*|\+[^[:space:]]+)(${B}|$)"
RE_HOOKS_BYPASS="core\.hookspath|(^|${B})husky=0|git_config_(key|parameters|count)|(^|[[:space:]])-c[[:space:]]+alias\.|remote\.[^[:space:]]*\.push(${B}|$)"
RE_SNAPSHOT_UPDATE='cypress:run:updateSnapshots?|updateSnapshots=true'
RE_SPEC='(^|[[:space:]])(--spec|-s)([[:space:]]+|=)([^[:space:]]+)'
RE_SPEC_FILE='^[^*?,{[]+\.(ts|tsx|js|jsx)$'
RE_FLAG_TOKEN='^[-+][^[:space:]]*$'
# A command that runs git, after optional variable assignments or wrappers.
RE_RUNS_GIT='^[[:space:]]*[({]?[[:space:]]*(([A-Za-z_][A-Za-z0-9_]*=[^[:space:]]*|env|command|nohup|time|exec|sudo|timeout[[:space:]]+[^[:space:]]+)[[:space:]]+)*([^[:space:]]*/)?git([[:space:]]|$)'
# The quoted command run by a nested shell: bash -c "...", eval '...'.
RE_NESTED="(^|${B})(bash|sh|zsh|eval)([[:space:]]+-[[:alpha:]]*c)?[[:space:]]+(\"([^\"]*)\"|'([^']*)')"

block() {
  printf 'Blocked by .claude/hooks/guard-bash.sh: %s\n' "$1" >&2
  exit 2
}

notify_once() {
  local marker="${TMPDIR:-/tmp}/claude-hooks-$1"
  if (set -C && : > "${marker}") 2> /dev/null; then
    printf '{"systemMessage":"%s"}\n' "$2"
  fi
}

# Prints the command with quoted strings replaced by Q, so separators, flags
# and line breaks inside a commit message are ignored. A quoted string that is
# itself a single flag ("-n", '--force') is kept.
flag_view() {
  local s="$1" out='' quoted='' ch state='' i
  for ((i = 0; i < ${#s}; i++)); do
    ch="${s:i:1}"
    case "${state}" in
      "'")
        if [[ "${ch}" == "'" ]]; then
          state=''
          [[ "${quoted}" =~ ${RE_FLAG_TOKEN} ]] && out+="${quoted}" || out+='Q'
          quoted=''
        else
          quoted+="${ch}"
        fi
        ;;
      '"')
        if [[ "${ch}" == '\' ]]; then
          quoted+="${s:i:2}"
          i=$((i + 1))
        elif [[ "${ch}" == '"' ]]; then
          state=''
          [[ "${quoted}" =~ ${RE_FLAG_TOKEN} ]] && out+="${quoted}" || out+='Q'
          quoted=''
        else
          quoted+="${ch}"
        fi
        ;;
      *)
        if [[ "${ch}" == '\' ]]; then
          [[ "${s:i+1:1}" == $'\n' ]] && out+=' ' || out+="${s:i+1:1}"
          i=$((i + 1))
        elif [[ "${ch}" == "'" || "${ch}" == '"' ]]; then
          state="${ch}"
        else
          out+="${ch}"
        fi
        ;;
    esac
  done
  printf '%s' "${out}"
}

check_git() {
  local segment="$1"
  # Only a command that runs git, not one that mentions it (echo git push).
  if [[ ! "${segment}" =~ ${RE_RUNS_GIT} ]]; then
    return 0
  fi
  if [[ "${segment}" =~ ${RE_GIT_COMMIT} || "${segment}" =~ ${RE_GIT_PUSH} ]]; then
    if [[ "${segment}" =~ ${RE_NO_VERIFY} ]]; then
      block 'do not bypass the git hooks (gitleaks); fix what the hook reports.'
    fi
  fi
  if [[ "${segment}" =~ ${RE_GIT_COMMIT} && "${segment}" =~ ${RE_SHORT_N} ]]; then
    block 'git commit -n skips the git hooks (gitleaks); fix what the hook reports.'
  fi
  if [[ "${segment}" =~ ${RE_GIT_PUSH} && "${segment}" =~ ${RE_FORCE} ]]; then
    block 'never force push; integrate the remote changes instead.'
  fi
  shopt -s nocasematch
  if [[ "${segment}" =~ ${RE_HOOKS_BYPASS} ]]; then
    block 'do not disable or redirect the git hooks, push refspecs or aliases.'
  fi
  shopt -u nocasematch
}

check_snapshot() {
  local segment="$1" spec rest
  if [[ ! "${segment}" =~ ${RE_SPEC} ]]; then
    block 'update snapshots for one spec only (--spec <file>), after reviewing its diff.'
  fi
  spec="${BASH_REMATCH[4]}"
  rest="${segment#*"${BASH_REMATCH[0]}"}"
  spec="${spec//\"/}"
  spec="${spec//\'/}"
  if [[ ! "${spec}" =~ ${RE_SPEC_FILE} || "${rest}" =~ ${RE_SPEC} ]]; then
    block 'update snapshots for one spec file only, not a directory, a glob or a list.'
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

check_command() {
  local command="$1" depth="$2" view segment rest nested

  view="$(flag_view "${command}")"
  while IFS= read -r segment; do
    check_git "${segment}"
  done <<< "${view//[;&|]/$'\n'}"

  # Snapshot updates: detected on the flag view, spec read from the command.
  if [[ "${view}" =~ ${RE_SNAPSHOT_UPDATE} ]]; then
    while IFS= read -r segment; do
      if [[ "${segment}" =~ ${RE_SNAPSHOT_UPDATE} ]]; then
        check_snapshot "${segment}"
      fi
    done <<< "${command//[;&|]/$'\n'}"
  fi

  # Commands run by a nested shell (bash -c "...", eval '...') are checked too.
  if ((depth < 3)); then
    rest="${command}"
    while [[ "${rest}" =~ ${RE_NESTED} ]]; do
      nested="${BASH_REMATCH[5]}${BASH_REMATCH[6]}"
      rest="${rest#*"${BASH_REMATCH[0]}"}"
      check_command "${nested}" $((depth + 1))
    done
  fi
}

check_command "${cmd}" 0
