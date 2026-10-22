#!/usr/bin/env bash
# Verify a frontend change: Biome, a type-check and the related tests, on the
# files changed since the branch left its base (committed, uncommitted and
# untracked), in centreon/www/front_src and centreon/packages/ui.
#
#   .claude/skills/verify-frontend/verify.sh [--fast] [--base <ref>]
#
#   --fast   type-check the changed files only (misses errors in importers)
#   --base   branch to compare with (default: origin/develop)
#
# Prints one line per step and the first failures; full logs stay in a temp
# directory. Exit 0 when everything passes, 1 otherwise.
set -uo pipefail

full=1
base='origin/develop'
while (($# > 0)); do
  case $1 in
    --fast)
      full=0
      shift
      ;;
    --base)
      base="$2"
      shift 2
      ;;
    -h | --help)
      sed -n '2,13p' "$0"
      exit 0
      ;;
    *)
      shift
      ;;
  esac
done

root="$(git rev-parse --show-toplevel)"
web="${root}/centreon"
ui="${web}/packages/ui"
logs="$(mktemp -d "${TMPDIR:-/tmp}/verify-frontend.XXXXXX")"
failed=0
max_cypress=5

# Prints the step result and, on failure, the first relevant lines of its log.
report() {
  local status="$1" step="$2" log="${3:-}"
  printf '%-5s %s\n' "${status}" "${step}"
  if [[ "${status}" == 'FAIL' ]]; then
    failed=1
    if [[ -n "${log}" ]]; then
      { grep -E 'TIMEOUT|[[:alnum:]_./-]+:[0-9]+:[0-9]+|error|Error|✕|×|FAIL|failing|Expected|Received|AssertionError' "${log}" || tail -n 25 "${log}"; } |
        head -n 25 | sed 's/^/      /'
    fi
  fi
}

# Runs a command in a directory, logging to a file, killed with its whole
# process tree (browsers included) after ${limit:-300} seconds; returns its
# status, 124 on timeout.
run() {
  local log="$1" dir="$2" pid waited=0 rc
  shift 2
  set -m
  (cd "${dir}" && exec "$@") > "${log}" 2>&1 &
  pid=$!
  set +m
  while kill -0 "${pid}" 2> /dev/null; do
    if ((waited >= ${limit:-300})); then
      exec 3>&2 2> /dev/null # no "Terminated" job notice
      kill -TERM -- "-${pid}"
      sleep 2
      kill -KILL -- "-${pid}"
      wait "${pid}"
      exec 2>&3 3>&-
      printf '\nTIMEOUT after %ss: killed (a hung browser or watcher?). Run it again.\n' "${limit:-300}" >> "${log}"
      return 124
    fi
    sleep 1
    waited=$((waited + 1))
  done
  wait "${pid}"
  rc=$?
  return "${rc}"
}

# Prints the related test files of $1 for kind $2 (browser|cypress): those of
# the closest directory, walking up to the package source root, that has any.
related_tests() {
  local file="$1" kind="$2" stop dir found
  case "${file}" in
    "${ui}"/*) stop="${ui}/src" ;;
    *) stop="${web}/www/front_src/src" ;;
  esac
  dir="$(dirname "${file}")"
  while [[ "${dir}" == "${stop}" || "${dir}" == "${stop}"/* ]]; do
    if [[ "${kind}" == 'browser' ]]; then
      found="$(ls "${dir}"/*.browser.test.tsx "${dir}"/Specs/*.browser.test.tsx 2> /dev/null || true)"
    else
      found="$(ls "${dir}"/*.cypress.spec.tsx "${dir}"/Specs/*.cypress.spec.tsx 2> /dev/null || true)"
    fi
    if [[ -n "${found}" ]]; then
      printf '%s\n' "${found}"
      return 0
    fi
    [[ "${dir}" == "${stop}" ]] && break
    dir="$(dirname "${dir}")"
  done
  return 0
}

# Type-checks a list of files with a temporary tsconfig extending $2 in $1.
typecheck_scoped() {
  local dir="$1" config="$2" log="$3" ambient="$4"
  shift 4
  local tmp="${dir}/tsconfig.verify-tmp.json" includes file rc
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

if [[ ! -x "${web}/node_modules/.bin/biome" ]]; then
  echo 'SKIP  everything: dependencies are not installed (run pnpm install in centreon/).'
  exit 1
fi

merge_base="$(git merge-base HEAD "${base}" 2> /dev/null || true)"
if [[ -z "${merge_base}" ]]; then
  echo "SKIP  everything: unknown base ${base} (git fetch origin, or pass --base)."
  exit 1
fi

changed=()
while IFS= read -r path; do
  [[ -f "${root}/${path}" ]] || continue
  case "${path}" in
    centreon/www/front_src/* | centreon/packages/ui/*) ;;
    *) continue ;;
  esac
  case "${path}" in
    *.ts | *.tsx | *.js | *.jsx | *.json | *.css) changed+=("${root}/${path}") ;;
  esac
done < <({
  git diff --name-only --diff-filter=ACMR "${merge_base}"
  git ls-files --others --exclude-standard
} | sort -u)

if ((${#changed[@]} == 0)); then
  echo "Nothing to verify: no frontend file changed since ${base}."
  exit 0
fi

app=() ui_files=() app_code=() app_specs=() ui_code=() jest_tests=() browser_tests=() cypress_specs=()
for file in "${changed[@]}"; do
  case "${file}" in
    "${ui}"/*) ui_files+=("${file}") ;;
    *) app+=("${file}") ;;
  esac
  case "${file}" in
    *.ts | *.tsx) ;;
    *) continue ;;
  esac
  case "${file}" in
    *.cypress.spec.tsx | */testsUtils.*) ;;
    "${web}"/www/front_src/*/Specs/* | "${web}"/www/front_src/*/specs/* | *.browser.test.tsx) app_specs+=("${file}") ;;
    "${ui}"/*) ui_code+=("${file}") ;;
    *) app_code+=("${file}") ;;
  esac
  case "${file}" in
    *.browser.test.tsx) ;;
    *.test.ts | *.test.tsx) jest_tests+=("${file}") ;;
    *)
      for sibling in "${file%.*}.test.ts" "${file%.*}.test.tsx"; do
        [[ -f "${sibling}" ]] && jest_tests+=("${sibling}")
      done
      ;;
  esac
  case "${file}" in
    *.cypress.spec.tsx) cypress_specs+=("${file}") ;;
    *.browser.test.tsx) browser_tests+=("${file}") ;;
    *)
      browser="$(related_tests "${file}" browser)"
      if [[ -n "${browser}" ]]; then
        while IFS= read -r test; do browser_tests+=("${test}"); done <<< "${browser}"
      else
        cypress="$(related_tests "${file}" cypress)"
        [[ -n "${cypress}" ]] && while IFS= read -r spec; do cypress_specs+=("${spec}"); done <<< "${cypress}"
      fi
      ;;
  esac
done

printf 'verify-frontend: %d changed file(s) since %s (app: %d, packages/ui: %d)\n' \
  "${#changed[@]}" "${base}" "${#app[@]}" "${#ui_files[@]}"

# 1. Biome, as CI runs it.
limit=120
if ((${#app[@]} > 0)); then
  if run "${logs}/biome-app.log" "${web}" node_modules/.bin/biome check --error-on-warnings --no-errors-on-unmatched "${app[@]}"; then
    report PASS 'biome (app)'
  else
    report FAIL 'biome (app)' "${logs}/biome-app.log"
  fi
fi
if ((${#ui_files[@]} > 0)); then
  if run "${logs}/biome-ui.log" "${ui}" "${web}/node_modules/.bin/biome" check --error-on-warnings --no-errors-on-unmatched "${ui_files[@]}"; then
    report PASS 'biome (packages/ui)'
  else
    report FAIL 'biome (packages/ui)' "${logs}/biome-ui.log"
  fi
fi

# 2. Type-check.
limit=300
if ((full == 1)); then
  if run "${logs}/tsc-full.log" "${web}" pnpm type-check; then report PASS 'type-check (full, app)'; else report FAIL 'type-check (full, app)' "${logs}/tsc-full.log"; fi
  if ((${#ui_files[@]} > 0)); then
    if run "${logs}/tsc-full-ui.log" "${ui}" pnpm type-check; then report PASS 'type-check (full, packages/ui)'; else report FAIL 'type-check (full, packages/ui)' "${logs}/tsc-full-ui.log"; fi
  fi
else
  if ((${#app_code[@]} > 0)); then
    if typecheck_scoped "${web}" tsconfig.typecheck.json "${logs}/tsc-app.log" '"./types","./www/front_src/src/**/*.d.ts"' "${app_code[@]}"; then
      report PASS "type-check (${#app_code[@]} app file(s) only, --fast)"
    else
      report FAIL 'type-check (app)' "${logs}/tsc-app.log"
    fi
  fi
  if ((${#app_specs[@]} > 0)); then
    spec_tests=()
    for file in "${app_specs[@]}"; do
      case "${file}" in
        *.browser.test.tsx) spec_tests+=("${file}") ;;
        *) for test in "$(dirname "${file}")"/*.browser.test.tsx; do [[ -f "${test}" ]] && spec_tests+=("${test}"); done ;;
      esac
    done
    if ((${#spec_tests[@]} > 0)); then
      if typecheck_scoped "${web}" tsconfig.rstest.json "${logs}/tsc-specs.log" '"./types","./node_modules/@rstest/core/globals.d.ts","./packages/ui/src/@types","./test-setup"' $(printf '%s\n' "${spec_tests[@]}" | sort -u); then
        report PASS 'type-check (browser tests)'
      else
        report FAIL 'type-check (browser tests)' "${logs}/tsc-specs.log"
      fi
    fi
  fi
  if ((${#ui_code[@]} > 0)); then
    if typecheck_scoped "${ui}" tsconfig.typecheck.json "${logs}/tsc-ui.log" '"./src/**/*.d.ts"' "${ui_code[@]}"; then
      report PASS "type-check (${#ui_code[@]} packages/ui file(s) only, --fast)"
    else
      report FAIL 'type-check (packages/ui)' "${logs}/tsc-ui.log"
    fi
  fi
fi

# 3. Related tests.
if ((${#jest_tests[@]} > 0)); then
  app_jest=() ui_jest=()
  for test in $(printf '%s\n' "${jest_tests[@]}" | sort -u); do
    case "${test}" in
      "${ui}"/*) ui_jest+=("${test}") ;;
      *) app_jest+=("${test}") ;;
    esac
  done
  if ((${#app_jest[@]} > 0)); then
    if run "${logs}/jest-app.log" "${web}" pnpm exec jest --silent "${app_jest[@]}"; then report PASS "jest (${#app_jest[@]} app file(s))"; else report FAIL 'jest (app)' "${logs}/jest-app.log"; fi
  fi
  if ((${#ui_jest[@]} > 0)); then
    if run "${logs}/jest-ui.log" "${ui}" pnpm exec jest --silent "${ui_jest[@]}"; then report PASS "jest (${#ui_jest[@]} packages/ui file(s))"; else report FAIL 'jest (packages/ui)' "${logs}/jest-ui.log"; fi
  fi
fi

if ((${#browser_tests[@]} > 0)); then
  tests=()
  for test in $(printf '%s\n' "${browser_tests[@]}" | sort -u); do tests+=("${test#"${web}"/}"); done
  if run "${logs}/rstest.log" "${web}" pnpm rstest:run "${tests[@]}"; then
    report PASS "rstest (${#tests[@]} file(s))"
  else
    report FAIL "rstest (${#tests[@]} file(s))" "${logs}/rstest.log"
    grep -q "Executable doesn't exist" "${logs}/rstest.log" && echo '      hint: run pnpm rstest:install in centreon/ once'
  fi
fi

if ((${#cypress_specs[@]} > 0)); then
  limit=600
  count=0
  for spec in $(printf '%s\n' "${cypress_specs[@]}" | sort -u); do
    count=$((count + 1))
    if ((count > max_cypress)); then
      report SKIP "cypress ${spec#"${root}"/} (more than ${max_cypress} specs; run it on its own)"
      continue
    fi
    case "${spec}" in
      "${ui}"/*) dir="${ui}" ;;
      *) dir="${web}" ;;
    esac
    name="$(basename "${spec}" .tsx)"
    if run "${logs}/cypress-${name}.log" "${dir}" pnpm cypress:run --spec "${spec#"${dir}"/}"; then
      report PASS "cypress ${spec#"${root}"/}"
    else
      report FAIL "cypress ${spec#"${root}"/}" "${logs}/cypress-${name}.log"
      grep -qi 'cypress binary is missing\|No version of Cypress is installed' "${logs}/cypress-${name}.log" &&
        echo '      hint: run pnpm cypress:install in centreon/ once'
    fi
  done
fi

if ((${#browser_tests[@]} == 0 && ${#cypress_specs[@]} == 0 && ${#jest_tests[@]} == 0)); then
  report NOTE 'no related test found: consider adding an rstest browser test'
fi

printf 'Logs: %s\n' "${logs}"
if ((failed == 1)); then
  echo 'RESULT: FAIL'
  exit 1
fi
echo 'RESULT: PASS'
