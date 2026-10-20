#!/usr/bin/env bash
# Tests for the Biome hook in this directory. Needs bash and jq only:
#   .claude/hooks/test-hooks.sh
set -euo pipefail

HOOKS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT
export TMPDIR="${TMP}"

failures=0
total=0

check() {
  local expected="$1" actual="$2" label="$3"
  total=$((total + 1))
  if [[ "${actual}" != "${expected}" ]]; then
    failures=$((failures + 1))
    printf 'FAIL (expected %s, got %s): %s\n' "${expected}" "${actual}" "${label}"
  fi
}

# Biome hook, against a stub Biome in a temporary project.
project="${TMP}/project"
mkdir -p "${project}/centreon/www/front_src/src" "${project}/centreon/packages/ui/src" \
  "${project}/centreon/node_modules/.bin"
printf '{"devDependencies":{"@biomejs/biome":"^2.5.14"}}\n' > "${project}/centreon/package.json"
cat > "${project}/centreon/node_modules/.bin/biome" << 'STUB'
#!/usr/bin/env bash
if [[ "$1" == '--version' ]]; then
  echo "Version: ${STUB_VERSION:-2.5.14}"
  exit 0
fi
pwd > "${TMPDIR}/biome-called"
printf '%s\n' "$*" > "${TMPDIR}/biome-args"
printf '%b' "${STUB_OUT:-}"
exit "${STUB_RC:-0}"
STUB
chmod +x "${project}/centreon/node_modules/.bin/biome"
project="$(cd "${project}" && pwd -P)"
app_file="${project}/centreon/www/front_src/src/a.tsx"
ui_file="${project}/centreon/packages/ui/src/b.ts"
touch "${app_file}" "${ui_file}" "${project}/centreon/www/front_src/src/c.md" "${project}/other.ts"

biome() {
  local expected="$1" file="$2" label="$3" rc=0 session="s${total}"
  rm -f "${TMPDIR}/biome-called"
  jq -n --arg f "${file}" --arg s "${session}" '{session_id: $s, tool_input: {file_path: $f}}' |
    CLAUDE_PROJECT_DIR="${project}" "${HOOKS_DIR}/biome-on-edit.sh" > "${TMPDIR}/out" 2> "${TMPDIR}/err" || rc=$?
  check "${expected}" "${rc}" "biome: ${label}"
}

biome 0 "${app_file}" 'clean app file'
check "${project}/centreon" "$(cat "${TMPDIR}/biome-called")" 'biome: runs from centreon/'
check 'yes' "$(grep -q -- '--error-on-warnings' "${TMPDIR}/biome-args" && echo yes || echo no)" 'biome: warnings fail like in CI'
biome 0 "${ui_file}" 'clean packages/ui file'
check "${project}/centreon/packages/ui" "$(cat "${TMPDIR}/biome-called")" 'biome: runs from packages/ui/'
biome 0 "${project}/centreon/www/front_src/src/c.md" 'markdown is skipped'
check 'no' "$([[ -e "${TMPDIR}/biome-called" ]] && echo yes || echo no)" 'biome: not called on markdown'
biome 0 "${project}/other.ts" 'file outside the frontend is skipped'
check 'no' "$([[ -e "${TMPDIR}/biome-called" ]] && echo yes || echo no)" 'biome: not called outside the frontend'
biome 0 "${project}/centreon/www/front_src/src/missing.tsx" 'missing file is skipped'
biome 0 "${project}/centreon/www/front_src/src/../../../../other.ts" 'dot-dot path outside the frontend is skipped'
check 'no' "$([[ -e "${TMPDIR}/biome-called" ]] && echo yes || echo no)" 'biome: not called on a dot-dot path'

STUB_RC=1 STUB_OUT='a.tsx:2:3 lint/suspicious/noDebugger\n' biome 2 "${app_file}" 'lint error is reported'
check 'yes' "$(grep -q 'still reports problems' "${TMPDIR}/err" && echo yes || echo no)" 'biome: lint message'
STUB_RC=1 STUB_OUT='biome.json:76:9 deserialize\n' biome 2 "${app_file}" 'config error with an up-to-date Biome is reported'
check 'yes' "$(grep -q 'configuration error' "${TMPDIR}/err" && echo yes || echo no)" 'biome: config message'
STUB_RC=1 STUB_VERSION=2.3.9 STUB_OUT='biome.json:76:9 deserialize\n' biome 0 "${app_file}" 'stale Biome fails open'
check 'yes' "$(grep -q 'older than 2.5.14' "${TMPDIR}/out" && echo yes || echo no)" 'biome: stale notice'
STUB_RC=101 biome 0 "${app_file}" 'crash fails open'
check 'yes' "$(grep -q 'exit 101' "${TMPDIR}/out" && echo yes || echo no)" 'biome: crash notice'

rm "${project}/centreon/node_modules/.bin/biome"
biome 0 "${app_file}" 'missing Biome fails open'
check 'yes' "$(grep -q 'not installed' "${TMPDIR}/out" && echo yes || echo no)" 'biome: missing notice'

printf '%d/%d checks passed\n' "$((total - failures))" "${total}"
((failures == 0))
