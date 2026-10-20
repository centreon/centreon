#!/usr/bin/env bash
# PostToolUse (Edit|Write): run Biome on the frontend file Claude just edited,
# so it stays formatted and lint-clean without Claude spending a turn on it.
# Lint errors go back to Claude (exit 2). Everything else fails open with a
# one-time notice to the user: no jq, no Biome installed, a Biome older than
# the repo requires, or a Biome crash. Tests: .claude/hooks/test-hooks.sh.
set -euo pipefail

input="$(cat)"
session=''

notify_once() {
  local marker="${TMPDIR:-/tmp}/claude-hooks-$1-${session:-$(date +%Y%m%d)}"
  if [[ ! -e "${marker}" ]]; then
    : > "${marker}" 2> /dev/null || true
    printf '{"systemMessage":"%s"}\n' "$2"
  fi
}

# Prints 0 when version $1 is lower than version $2 (x.y.z), 1 otherwise.
version_lt() {
  local IFS=.
  local -a have=($1) want=($2)
  local i
  for i in 0 1 2; do
    if ((${have[i]:-0} != ${want[i]:-0})); then
      ((${have[i]:-0} < ${want[i]:-0})) && return 0 || return 1
    fi
  done
  return 1
}

if ! command -v jq > /dev/null 2>&1; then
  notify_once biome-nojq 'Auto-format on edit is off: jq is not installed.'
  exit 0
fi

file="$(printf '%s' "${input}" | jq -r '.tool_input.file_path // empty' 2> /dev/null || true)"
session="$(printf '%s' "${input}" | jq -r '.session_id // empty' 2> /dev/null || true)"
session="${session//[^A-Za-z0-9_-]/}"
if [[ -z "${file}" || ! -f "${file}" ]]; then
  exit 0
fi

root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
if command -v realpath > /dev/null 2>&1; then
  file="$(realpath "${file}")"
  root="$(realpath "${root}")"
fi

case "${file}" in
  "${root}"/centreon/www/front_src/*) pkg="${root}/centreon" ;;
  "${root}"/centreon/packages/ui/*) pkg="${root}/centreon/packages/ui" ;;
  *) exit 0 ;;
esac

case "${file}" in
  *.ts | *.tsx | *.js | *.jsx | *.json | *.css) ;;
  *) exit 0 ;;
esac

biome="${pkg}/node_modules/.bin/biome"
if [[ ! -x "${biome}" ]]; then
  biome="${root}/centreon/node_modules/.bin/biome"
fi
if [[ ! -x "${biome}" ]]; then
  notify_once biome-missing 'Auto-format on edit is off: Biome is not installed (run pnpm install in centreon/).'
  exit 0
fi

if output="$(cd "${pkg}" && "${biome}" check --write --no-errors-on-unmatched --max-diagnostics=10 "${file}" 2>&1)"; then
  exit 0
else
  rc=$?
fi

if ((rc != 1)); then
  notify_once biome-crash "Biome failed on an edited file (exit ${rc}); auto-format skipped."
  exit 0
fi

have="$("${biome}" --version 2> /dev/null | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+' | head -n 1 || true)"
want="$(jq -r '(.devDependencies // {})["@biomejs/biome"] // empty' "${root}/centreon/package.json" 2> /dev/null | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+' || true)"
if [[ -n "${have}" && -n "${want}" ]] && version_lt "${have}" "${want}"; then
  notify_once biome-stale "Auto-format on edit is off: Biome ${have} is older than ${want} (run pnpm install in centreon/)."
  exit 0
fi

heading="Biome still reports problems in ${file}"
if [[ "${output%%$'\n'*}" =~ biome\.jsonc?[:[:space:]] ]]; then
  heading='Biome configuration error (in biome.json, not in the edited file)'
fi
lines="$(printf '%s\n' "${output}" | wc -l | tr -d ' ')"
printf '%s:\n%s\n' "${heading}" "$(printf '%s\n' "${output}" | head -n 60)" >&2
if ((lines > 60)); then
  printf '... (truncated, run biome check on the file for the rest)\n' >&2
fi
exit 2
