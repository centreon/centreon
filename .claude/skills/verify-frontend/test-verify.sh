#!/usr/bin/env bash
# Tests for verify.sh, against stub biome, tsc and pnpm in a temporary git
# repository. Needs bash, git and jq only:
#   .claude/skills/verify-frontend/test-verify.sh
set -euo pipefail

SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify.sh"
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

# Prints yes when the stub calls log has a line matching the regex $1.
called() {
  grep -Eq -- "$1" "${CALLS}" 2> /dev/null && echo yes || echo no
}

# Prints yes when the process a stub started with STUB_SLEEP is still running.
alive() {
  [[ -f "${TMP}/sleep.pid" ]] && kill -0 "$(cat "${TMP}/sleep.pid")" 2> /dev/null && echo yes || echo no
}

# Prints yes when the last output has a line matching the regex $1.
said() {
  grep -Eq -- "$1" "${TMP}/out" && echo yes || echo no
}

# --- A repository laid out like centreon/centreon, with stubs. -------------
repo="${TMP}/repo"
web="${repo}/centreon"
src="${web}/www/front_src/src"
ui="${web}/packages/ui"
export CALLS="${TMP}/calls"
mkdir -p "${web}/node_modules/.bin" "${TMP}/bin" "${src}/Hosts/Specs" "${src}/Hosts/Form" \
  "${src}/Legacy/Listing" "${src}/Legacy/Specs" "${src}/Plain" "${ui}/src/Button"

stub='#!/usr/bin/env bash
name="$(basename "$0")"
printf "%s|%s %s\n" "$(pwd)" "${name}" "$*" >> "${CALLS}"
if [[ "${name}" == tsc && "$1" == -p ]]; then cp "$2" "${TMPDIR}/tsconfig-copy.json"; fi
if [[ -n "${STUB_SLEEP:-}" && "${name} $*" =~ ${STUB_SLEEP} ]]; then sleep 60 & echo $! > "${TMPDIR}/sleep.pid"; wait; fi
if [[ -n "${STUB_FAIL:-}" && "${name} $*" =~ ${STUB_FAIL} ]]; then printf "%b" "${STUB_OUT:-failed}"; exit 1; fi
exit 0'
for bin in "${web}/node_modules/.bin/biome" "${web}/node_modules/.bin/tsc" "${TMP}/bin/pnpm"; do
  printf '%s\n' "${stub}" > "${bin}"
  chmod +x "${bin}"
done
export PATH="${TMP}/bin:${PATH}"

cd "${repo}"
git init -q
git config user.email test@example.com
git config user.name test
printf 'node_modules\n' > .gitignore
for f in Hosts/utils.ts Hosts/Form/form.tsx Hosts/Specs/Hosts.browser.test.tsx Hosts/Specs/initialize.tsx \
  Legacy/Listing/listing.tsx Legacy/Specs/Legacy.browser.test.tsx Legacy/Listing/Listing.cypress.spec.tsx \
  Plain/plain.tsx Plain/style.css; do
  printf 'export const x = 1;\n' > "${src}/${f}"
done
printf 'export const b = 1;\n' > "${ui}/src/Button/index.ts"
printf 'export const t = 1;\n' > "${ui}/src/Button/index.test.ts"
printf '{}\n' > "${web}/biome.json"
for i in 1 2 3 4 5 6; do
  mkdir -p "${src}/Many${i}"
  printf 'export const x = 1;\n' > "${src}/Many${i}/a.tsx"
  printf 'export const x = 1;\n' > "${src}/Many${i}/A.cypress.spec.tsx"
done
git add -A
git commit -qm base
git update-ref refs/remotes/origin/develop HEAD

# Runs verify.sh from directory $1 with the remaining arguments; sets rc.
verify() {
  local dir="$1"
  shift
  : > "${CALLS}"
  rm -f "${TMP}/tsconfig-copy.json"
  rc=0
  (cd "${dir}" && "${SCRIPT}" "$@") > "${TMP}/out" 2>&1 || rc=$?
}

# Back to the base commit, keeping the stubs (ignored by git).
reset() {
  git -C "${repo}" reset -q --hard
  git -C "${repo}" clean -qfd
}

# --- Cases -----------------------------------------------------------------

# Nothing changed, bad options and a missing base are "not verified".
verify "${repo}"
check 2 "${rc}" 'no change: exit 2'
check yes "$(said '^RESULT: NOT VERIFIED$')" 'no change: NOT VERIFIED'
verify "${repo}" --fsat
check 2 "${rc}" 'unknown option: exit 2'
verify "${repo}" --base
check 2 "${rc}" '--base without a value: exit 2'
verify "${repo}" --base origin/nope
check 2 "${rc}" 'unknown base: exit 2'

# A tracked change and a new untracked file, run from centreon/: both checked.
printf 'export const y = 2;\n' >> "${src}/Hosts/utils.ts"
printf 'export const z = 3;\n' > "${src}/Hosts/new.ts"
verify "${web}"
check yes "$(called 'biome check .*Hosts/utils.ts')" 'from centreon/: changed file linted'
check yes "$(called 'biome check .*Hosts/new.ts')" 'from centreon/: untracked file linted'
check yes "$(called 'pnpm type-check')" 'full type-check by default'
check yes "$(called 'pnpm rstest:run .*Hosts/Specs/Hosts.browser.test.tsx')" 'related rstest test run'
check 0 "${rc}" 'all checks pass: exit 0'
check yes "$(said '^RESULT: PASS$')" 'all checks pass: PASS'

# A failing type-check fails the run and shows the error line.
STUB_FAIL='type-check' STUB_OUT='www/front_src/src/Hosts/utils.ts(2,14): error TS2322: bad\n' verify "${repo}"
check 1 "${rc}" 'type error: exit 1'
check yes "$(said 'utils.ts\(2,14\): error TS2322')" 'type error: excerpt shown'
check yes "$(said '^RESULT: FAIL$')" 'type error: FAIL'
reset

# A deleted file: never "nothing to verify", full type-check even with --fast.
git rm -q "${src}/Hosts/utils.ts"
verify "${repo}" --fast
check yes "$(called 'pnpm type-check')" 'deletion: full type-check despite --fast'
check yes "$(called 'pnpm rstest:run .*Hosts.browser.test.tsx')" 'deletion: tests of its directory run'
check 0 "${rc}" 'deletion: exit 0'
reset

# A configuration change: whole-package lint and type-check, reported partial.
printf '{"x":1}\n' > "${web}/biome.json"
verify "${repo}"
check yes "$(called "${web}\|pnpm lint")" 'config: app lint'
check yes "$(called "${ui}\|pnpm type-check")" 'config: packages/ui type-check'
check 3 "${rc}" 'config: exit 3 (suites not run)'
reset

# packages/ui: Biome and Jest from packages/ui, sibling test found.
printf 'export const c = 2;\n' >> "${ui}/src/Button/index.ts"
verify "${repo}"
check yes "$(called "${ui}\|biome check .*Button/index.ts")" 'ui: biome from packages/ui'
check yes "$(called "${ui}\|pnpm exec jest .*Button/index.test.ts")" 'ui: sibling jest test'
check 0 "${rc}" 'ui: exit 0'
reset

# The closest tests win: a Cypress spec next to the file over rstest above.
printf 'export const y = 2;\n' >> "${src}/Legacy/Listing/listing.tsx"
verify "${repo}"
check yes "$(called 'pnpm cypress:run --spec www/front_src/src/Legacy/Listing/Listing.cypress.spec.tsx')" 'closest: Cypress spec next to the file'
check no "$(called 'rstest:run')" 'closest: not the rstest test above'
reset

# More than 5 Cypress specs: 5 run, the rest listed, partial.
for i in 1 2 3 4 5 6; do printf 'export const y = 2;\n' >> "${src}/Many${i}/a.tsx"; done
verify "${repo}"
check 5 "$(grep -c 'cypress:run' "${CALLS}")" 'cap: 5 Cypress specs run'
check yes "$(said 'Cypress spec not run \(more than 5\)')" 'cap: skipped spec listed'
check 3 "${rc}" 'cap: exit 3'
reset

# No related test: listed, partial.
printf '.a{}\n' >> "${src}/Plain/style.css"
verify "${repo}"
check yes "$(said 'no related test: centreon/www/front_src/src/Plain/style.css')" 'untested file listed'
check 3 "${rc}" 'untested: exit 3'
reset

# --fast: scoped tsconfig extending the repo one, removed afterwards.
printf 'export const y = 2;\n' >> "${src}/Hosts/Form/form.tsx"
verify "${repo}" --fast
check ./tsconfig.typecheck.json "$(jq -r .extends "${TMP}/tsconfig-copy.json")" '--fast: extends the repo tsconfig'
check yes "$(jq -r '.include | index("./www/front_src/src/Hosts/Form/form.tsx") != null' "${TMP}/tsconfig-copy.json" | sed 's/true/yes/')" '--fast: changed file included'
check 0 "$(find "${web}" -name '.tsconfig.verify.*' | wc -l | tr -d ' ')" '--fast: temporary tsconfig removed'
check 3 "${rc}" '--fast: partial (importers not checked)'
reset

# A path with a space reaches the tools as one argument.
printf 'export const s = 1;\n' > "${src}/Hosts/with space.ts"
verify "${repo}"
check yes "$(called 'biome check .*Hosts/with space.ts')" 'space in a path kept whole'
reset

# A hung step is killed after the limit and fails the run.
printf 'export const y = 2;\n' >> "${src}/Hosts/utils.ts"
STUB_SLEEP='rstest' VERIFY_FRONTEND_TIMEOUT=2 verify "${repo}"
check 1 "${rc}" 'timeout: exit 1'
check yes "$(said 'TIMEOUT after 2s')" 'timeout: reported'
sleep 1
check no "$(alive)" 'timeout: process tree killed'
reset

# Interrupted: the running step and the temporary tsconfig are cleaned up.
printf 'export const y = 2;\n' >> "${src}/Hosts/Form/form.tsx"
(cd "${repo}" && exec env STUB_SLEEP='tsc' "${SCRIPT}" --fast > /dev/null 2>&1) &
pid=$!
for _ in 1 2 3 4 5 6 7 8 9 10; do [[ -f "${TMP}/sleep.pid" ]] && break; sleep 1; done
kill -TERM "${pid}"
wait "${pid}" 2> /dev/null || true
sleep 1
check no "$(alive)" 'interrupt: running step killed'
check 0 "$(find "${web}" -name '.tsconfig.verify.*' | wc -l | tr -d ' ')" 'interrupt: temporary tsconfig removed'
reset

printf '%d/%d checks passed\n' "$((total - failures))" "${total}"
((failures == 0))
