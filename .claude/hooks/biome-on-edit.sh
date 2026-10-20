#!/usr/bin/env bash
# PostToolUse (Edit|Write): run Biome on the frontend file Claude just edited,
# so it stays formatted and lint-clean without Claude spending a turn on it.
# Fails open: no jq, no installed Biome or a file outside the frontend means
# nothing happens. Remaining lint errors are reported back to Claude (exit 2).
set -euo pipefail

if ! command -v jq > /dev/null 2>&1; then
  exit 0
fi

file="$(jq -r '.tool_input.file_path // empty' 2> /dev/null || true)"
if [[ -z "${file}" || ! -f "${file}" ]]; then
  exit 0
fi

root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
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
  exit 0
fi

if ! output="$(cd "${pkg}" && "${biome}" check --write --no-errors-on-unmatched "${file}" 2>&1)"; then
  # A Biome older than the repo config (stale node_modules) fails on the
  # configuration itself: not the edit's fault, so do not block on it.
  if [[ "${output}" =~ biome\.json.*(deserialize|configuration) ]]; then
    exit 0
  fi
  printf 'Biome still reports problems in %s:\n%s\n' "${file}" "$(printf '%s\n' "${output}" | head -n 30)" >&2
  exit 2
fi
