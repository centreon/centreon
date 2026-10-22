#!/usr/bin/env bash
# Verify a frontend change: Biome, the type-check and the related tests, on
# what changed since the branch left its base (committed, uncommitted,
# untracked and deleted files) in centreon/www/front_src, centreon/packages/ui
# and the frontend configuration.
#
#   .claude/skills/verify-frontend/verify.sh [--fast] [--base <ref>]
#
#   --fast   type-check the changed files only (misses errors in importers)
#   --base   branch to compare with (default: origin/develop)
#
# Result, last line and exit code:
#   RESULT: PASS           0  everything that changed was checked and passed
#   RESULT: FAIL           1  a check failed (first failures printed above)
#   RESULT: NOT VERIFIED   2  nothing to check, setup missing or bad option
#   RESULT: PARTIAL        3  checks passed, but the "Not checked" list is not
#                             empty (no related test, skipped spec, --fast...)
#
# Tests: .claude/skills/verify-frontend/test-verify.sh
set -uo pipefail

usage() {
  sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
}

not_verified() {
  printf '%s\nRESULT: NOT VERIFIED\n' "$1"
  exit 2
}

full=1
base='origin/develop'
while (($# > 0)); do
  case $1 in
    --fast)
      full=0
      shift
      ;;
    --base)
      (($# >= 2)) || not_verified '--base needs a value.'
      base="$2"
      shift 2
      ;;
    --base=*)
      base="${1#--base=}"
      shift
      ;;
    -h | --help)
      usage
      exit 0
      ;;
    *)
      not_verified "Unknown option: $1 (see --help)."
      ;;
  esac
done

root="$(git rev-parse --show-toplevel 2> /dev/null)" || not_verified 'Not in a git repository.'
# Every git path below is relative to the repository root.
cd "${root}" || not_verified "Cannot enter ${root}."
web="${root}/centreon"
ui="${web}/packages/ui"
logs="$(mktemp -d "${TMPDIR:-/tmp}/verify-frontend.XXXXXX")"

failed=0
gaps=()  # what was not checked, listed before the result
job=''   # process group of the running step, killed on interrupt
temps=() # temporary tsconfig files, removed on exit

cleanup() {
  if [[ -n "${job}" ]]; then
    kill -TERM -- "-${job}" 2> /dev/null
  fi
  if ((${#temps[@]} > 0)); then
    rm -f "${temps[@]}"
  fi
}
trap cleanup EXIT
trap 'cleanup; trap - EXIT; exit 130' INT
trap 'cleanup; trap - EXIT; exit 143' TERM

# Prints the step result and, on failure, the first relevant lines of its log.
report() {
  local status="$1" step="$2" log="${3:-}"
  printf '%-5s %s\n' "${status}" "${step}"
  if [[ "${status}" == 'FAIL' ]]; then
    failed=1
    if [[ -n "${log}" ]]; then
      { grep -E 'TIMEOUT|[[:alnum:]_./-]+:[0-9]+:[0-9]+|[[:alnum:]_./-]+\([0-9]+,[0-9]+\)|error|Error|✕|×|FAIL|failing|Expected|Received|AssertionError' "${log}" || tail -n 25 "${log}"; } |
        head -n 25 | sed 's/^/      /'
    fi
  fi
}

# Runs a command in a directory, logging to a file, killed with its whole
# process tree (browsers included) after ${limit} seconds; returns its status,
# 124 on timeout. VERIFY_FRONTEND_TIMEOUT overrides every limit.
run() {
  local log="$1" dir="$2" max="${VERIFY_FRONTEND_TIMEOUT:-${limit}}" waited=0 rc
  shift 2
  set -m
  (cd "${dir}" && exec "$@") > "${log}" 2>&1 &
  job=$!
  set +m
  while kill -0 "${job}" 2> /dev/null; do
    if ((waited >= max)); then
      exec 3>&2 2> /dev/null # no "Terminated" job notice
      kill -TERM -- "-${job}"
      sleep 2
      kill -KILL -- "-${job}"
      wait "${job}"
      exec 2>&3 3>&-
      job=''
      printf '\nTIMEOUT after %ss: killed (a hung browser or watcher?). Run it again.\n' "${max}" >> "${log}"
      return 124
    fi
    sleep 1
    waited=$((waited + 1))
  done
  wait "${job}"
  rc=$?
  job=''
  return "${rc}"
}

# Prints its arguments once each, sorted, one per line.
unique() {
  (($# > 0)) && printf '%s\n' "$@" | sort -u
}

# Prints the related tests of a file $1: those of the closest directory,
# walking up to the package source root, that has rstest browser tests or
# Cypress specs (browser tests first at the same level). The first line is
# the kind, "browser" or "cypress"; nothing when none is found.
related_tests() {
  local dir stop found kind
  dir="$(dirname "$1")"
  case "${dir}" in
    "${ui}"/*) stop="${ui}/src" ;;
    *) stop="${web}/www/front_src/src" ;;
  esac
  while [[ ! -d "${dir}" && "${dir}" == "${stop}"/* ]]; do
    dir="$(dirname "${dir}")"
  done
  while [[ "${dir}" == "${stop}" || "${dir}" == "${stop}"/* ]]; do
    for kind in browser cypress; do
      if [[ "${kind}" == 'browser' ]]; then
        found="$(ls "${dir}"/*.browser.test.tsx "${dir}"/Specs/*.browser.test.tsx 2> /dev/null || true)"
      else
        found="$(ls "${dir}"/*.cypress.spec.tsx "${dir}"/Specs/*.cypress.spec.tsx 2> /dev/null || true)"
      fi
      if [[ -n "${found}" ]]; then
        printf '%s\n%s\n' "${kind}" "${found}"
        return 0
      fi
    done
    [[ "${dir}" == "${stop}" ]] && break
    dir="$(dirname "${dir}")"
  done
  return 0
}

# Type-checks files with a temporary tsconfig extending $2 in directory $1.
typecheck_scoped() {
  local dir="$1" config="$2" log="$3" ambient="$4" tmp includes file rc
  shift 4
  tmp="$(mktemp "${dir}/.tsconfig.verify.XXXXXX")"
  temps+=("${tmp}")
  includes="${ambient}"
  for file in "$@"; do
    includes="${includes},\"./${file#"${dir}"/}\""
  done
  printf '{"extends":"./%s","include":[%s]}\n' "${config}" "${includes}" > "${tmp}"
  run "${log}" "${dir}" "${web}/node_modules/.bin/tsc" -p "${tmp}" --noEmit --pretty false
  rc=$?
  rm -f "${tmp}"
  return "${rc}"
}

[[ -x "${web}/node_modules/.bin/biome" ]] ||
  not_verified 'Dependencies are not installed: run pnpm install in centreon/.'
merge_base="$(git merge-base HEAD "${base}" 2> /dev/null)" ||
  not_verified "Unknown base ${base}: git fetch origin, or pass --base."

# Changed paths, NUL-separated so any file name is safe.
{ git diff -z --name-only --diff-filter=ACMR "${merge_base}" > "${logs}/changed" &&
  git ls-files -z --others --exclude-standard >> "${logs}/changed" &&
  git diff -z --name-only --diff-filter=D "${merge_base}" > "${logs}/deleted"; } ||
  not_verified 'git could not list the changed files.'

code=() deleted=() configs=() outside=0
while IFS= read -r -d '' path; do
  case "${path}" in
    centreon/package.json | centreon/pnpm-lock.yaml | centreon/biome.json | centreon/tsconfig*.json | \
      centreon/*.config.ts | centreon/*.config.js | centreon/*.config.*.js | centreon/setupTest.js | \
      centreon/packages/js-config/* | centreon/packages/ui-context/* | centreon/test-setup/* | centreon/cypress/* | \
      centreon/packages/ui/package.json | centreon/packages/ui/biome.json | centreon/packages/ui/tsconfig*.json | \
      centreon/packages/ui/*.config.* | centreon/packages/ui/.storybook/*)
      configs+=("${path}")
      ;;
    centreon/www/front_src/* | centreon/packages/ui/*) code+=("${root}/${path}") ;;
    *) outside=$((outside + 1)) ;;
  esac
done < <(sort -zu "${logs}/changed")
while IFS= read -r -d '' path; do
  case "${path}" in
    centreon/www/front_src/* | centreon/packages/ui/*) deleted+=("${root}/${path}") ;;
    centreon/*) configs+=("${path} (deleted)") ;;
  esac
done < "${logs}/deleted"

if ((${#code[@]} + ${#deleted[@]} + ${#configs[@]} == 0)); then
  not_verified "Nothing to verify: no frontend change since ${base} (${outside} other changed path(s))."
fi

# Route the changed files to the checks.
lint=() app_code=() app_specs=() ui_code=() jest=() browser=() cypress=() untested=() ui_touched=0
for file in "${code[@]+"${code[@]}"}" "${deleted[@]+"${deleted[@]}"}"; do
  [[ "${file}" == "${ui}"/* ]] && ui_touched=1
  if [[ -f "${file}" ]]; then
    case "${file}" in
      *.ts | *.tsx | *.js | *.jsx | *.mjs | *.cjs | *.json | *.css) lint+=("${file}") ;;
    esac
    case "${file}" in
      *.cypress.spec.tsx | */testsUtils.* | *.stories.ts | *.stories.tsx | *.cy.tsx)
        gaps+=("not type-checked (excluded by the repo tsconfig): ${file#"${root}"/}")
        ;;
      *.browser.test.tsx | "${web}"/www/front_src/*/Specs/* | "${web}"/www/front_src/*/specs/*) app_specs+=("${file}") ;;
      "${ui}"/*.ts | "${ui}"/*.tsx) ui_code+=("${file}") ;;
      *.ts | *.tsx) app_code+=("${file}") ;;
    esac
    case "${file}" in
      *.browser.test.tsx)
        browser+=("${file}")
        continue
        ;;
      *.cypress.spec.tsx)
        cypress+=("${file}")
        continue
        ;;
      *.test.ts | *.test.tsx)
        jest+=("${file}")
        continue
        ;;
    esac
    for sibling in "${file%.*}.test.ts" "${file%.*}.test.tsx"; do
      [[ -f "${sibling}" ]] && jest+=("${sibling}")
    done
  fi
  related="$(related_tests "${file}")"
  if [[ -z "${related}" ]]; then
    if [[ -f "${file}" && ! -f "${file%.*}.test.ts" && ! -f "${file%.*}.test.tsx" ]]; then
      untested+=("${file#"${root}"/}")
    fi
    continue
  fi
  kind="${related%%$'\n'*}"
  while IFS= read -r test; do
    if [[ "${kind}" == 'browser' ]]; then browser+=("${test}"); else cypress+=("${test}"); fi
  done <<< "${related#*$'\n'}"
done

printf 'verify-frontend since %s: %d changed, %d deleted, %d config file(s)\n' \
  "${base}" "${#code[@]}" "${#deleted[@]}" "${#configs[@]}"

# 1. Biome, as CI runs it; the whole package when the configuration changed.
limit=120
if ((${#configs[@]} > 0)); then
  if run "${logs}/biome-app.log" "${web}" pnpm lint; then report PASS 'biome (whole app, config changed)'; else report FAIL 'biome (whole app)' "${logs}/biome-app.log"; fi
  if run "${logs}/biome-ui.log" "${ui}" pnpm lint; then report PASS 'biome (whole packages/ui, config changed)'; else report FAIL 'biome (whole packages/ui)' "${logs}/biome-ui.log"; fi
elif ((${#lint[@]} > 0)); then
  app_lint=() ui_lint=()
  for file in "${lint[@]}"; do
    if [[ "${file}" == "${ui}"/* ]]; then ui_lint+=("${file}"); else app_lint+=("${file}"); fi
  done
  if ((${#app_lint[@]} > 0)); then
    if run "${logs}/biome-app.log" "${web}" node_modules/.bin/biome check --error-on-warnings --no-errors-on-unmatched -- "${app_lint[@]}"; then
      report PASS "biome (${#app_lint[@]} app file(s))"
    else
      report FAIL 'biome (app)' "${logs}/biome-app.log"
    fi
  fi
  if ((${#ui_lint[@]} > 0)); then
    if run "${logs}/biome-ui.log" "${ui}" "${web}/node_modules/.bin/biome" check --error-on-warnings --no-errors-on-unmatched -- "${ui_lint[@]}"; then
      report PASS "biome (${#ui_lint[@]} packages/ui file(s))"
    else
      report FAIL 'biome (packages/ui)' "${logs}/biome-ui.log"
    fi
  fi
fi

# 2. Type-check: full by default, and always when files were deleted or the
# configuration changed (the importers are what breaks).
limit=300
if ((full == 0 && ${#deleted[@]} + ${#configs[@]} > 0)); then
  full=1
  echo 'NOTE  --fast ignored: files were deleted or the configuration changed'
fi
if ((full == 1)); then
  if run "${logs}/tsc-app.log" "${web}" pnpm type-check; then report PASS 'type-check (full, app)'; else report FAIL 'type-check (full, app)' "${logs}/tsc-app.log"; fi
  if ((ui_touched == 1 || ${#configs[@]} > 0)); then
    if run "${logs}/tsc-ui.log" "${ui}" pnpm type-check; then report PASS 'type-check (full, packages/ui)'; else report FAIL 'type-check (full, packages/ui)' "${logs}/tsc-ui.log"; fi
  fi
else
  gaps+=('type-check limited to the changed files (--fast): files importing them were not checked')
  if ((${#app_code[@]} > 0)); then
    if typecheck_scoped "${web}" tsconfig.typecheck.json "${logs}/tsc-app.log" '"./types","./www/front_src/src/**/*.d.ts"' "${app_code[@]}"; then
      report PASS "type-check (${#app_code[@]} app file(s), --fast)"
    else
      report FAIL 'type-check (app)' "${logs}/tsc-app.log"
    fi
  fi
  if ((${#app_specs[@]} > 0)); then
    spec_tests=()
    for file in "${app_specs[@]}"; do
      case "${file}" in
        *.browser.test.tsx) spec_tests+=("${file}") ;;
        *)
          found=0
          for test in "$(dirname "${file}")"/*.browser.test.tsx; do
            if [[ -f "${test}" ]]; then
              spec_tests+=("${test}")
              found=1
            fi
          done
          ((found == 1)) || gaps+=("not type-checked (no browser test next to it): ${file#"${root}"/}")
          ;;
      esac
    done
    if ((${#spec_tests[@]} > 0)); then
      unique_tests=()
      while IFS= read -r test; do unique_tests+=("${test}"); done < <(unique "${spec_tests[@]}")
      if typecheck_scoped "${web}" tsconfig.rstest.json "${logs}/tsc-specs.log" '"./types","./node_modules/@rstest/core/globals.d.ts","./packages/ui/src/@types","./test-setup"' "${unique_tests[@]}"; then
        report PASS "type-check (${#unique_tests[@]} browser test(s), --fast)"
      else
        report FAIL 'type-check (browser tests)' "${logs}/tsc-specs.log"
      fi
    fi
  fi
  if ((${#ui_code[@]} > 0)); then
    if typecheck_scoped "${ui}" tsconfig.typecheck.json "${logs}/tsc-ui.log" '"./src/**/*.d.ts"' "${ui_code[@]}"; then
      report PASS "type-check (${#ui_code[@]} packages/ui file(s), --fast)"
    else
      report FAIL 'type-check (packages/ui)' "${logs}/tsc-ui.log"
    fi
  fi
fi

# 3. Related tests.
if ((${#jest[@]} > 0)); then
  app_jest=() ui_jest=()
  while IFS= read -r test; do
    if [[ "${test}" == "${ui}"/* ]]; then ui_jest+=("${test}"); else app_jest+=("${test}"); fi
  done < <(unique "${jest[@]}")
  if ((${#app_jest[@]} > 0)); then
    if run "${logs}/jest-app.log" "${web}" pnpm exec jest --silent -- "${app_jest[@]}"; then report PASS "jest (${#app_jest[@]} app file(s))"; else report FAIL 'jest (app)' "${logs}/jest-app.log"; fi
  fi
  if ((${#ui_jest[@]} > 0)); then
    if run "${logs}/jest-ui.log" "${ui}" pnpm exec jest --silent -- "${ui_jest[@]}"; then report PASS "jest (${#ui_jest[@]} packages/ui file(s))"; else report FAIL 'jest (packages/ui)' "${logs}/jest-ui.log"; fi
  fi
fi

if ((${#browser[@]} > 0)); then
  tests=()
  while IFS= read -r test; do tests+=("${test#"${web}"/}"); done < <(unique "${browser[@]}")
  if run "${logs}/rstest.log" "${web}" pnpm rstest:run "${tests[@]}"; then
    report PASS "rstest (${#tests[@]} file(s))"
  else
    report FAIL "rstest (${#tests[@]} file(s))" "${logs}/rstest.log"
    grep -q "Executable doesn't exist" "${logs}/rstest.log" && echo '      hint: run pnpm rstest:install in centreon/ once'
  fi
fi

if ((${#cypress[@]} > 0)); then
  limit=600
  count=0
  while IFS= read -r spec; do
    count=$((count + 1))
    if ((count > 5)); then
      gaps+=("Cypress spec not run (more than 5): ${spec#"${root}"/}")
      continue
    fi
    if [[ "${spec}" == "${ui}"/* ]]; then dir="${ui}"; else dir="${web}"; fi
    if run "${logs}/cypress-${count}.log" "${dir}" pnpm cypress:run --spec "${spec#"${dir}"/}"; then
      report PASS "cypress ${spec#"${root}"/}"
    else
      report FAIL "cypress ${spec#"${root}"/}" "${logs}/cypress-${count}.log"
      grep -qi 'cypress binary is missing\|No version of Cypress is installed' "${logs}/cypress-${count}.log" &&
        echo '      hint: run pnpm cypress:install in centreon/ once'
    fi
  done < <(unique "${cypress[@]}")
fi

if ((${#untested[@]} > 0)); then
  while IFS= read -r file; do gaps+=("no related test: ${file}"); done < <(unique "${untested[@]}")
fi
if ((${#configs[@]} > 0)); then
  gaps+=("configuration changed (${configs[*]}): its test suites were not run, CI will")
fi

printf 'Logs: %s\n' "${logs}"
if ((failed == 1)); then
  echo 'RESULT: FAIL'
  exit 1
fi
if ((${#gaps[@]} > 0)); then
  echo 'Not checked:'
  printf '  - %s\n' "${gaps[@]}"
  echo 'RESULT: PARTIAL'
  exit 3
fi
echo 'RESULT: PASS'
