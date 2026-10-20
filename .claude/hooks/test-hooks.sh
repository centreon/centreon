#!/usr/bin/env bash
# Tests for the Claude Code hooks in this directory. Needs bash and jq only:
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

guard() {
  local expected="$1" command="$2" rc=0
  jq -n --arg c "${command}" '{tool_input: {command: $c}}' |
    "${HOOKS_DIR}/guard-bash.sh" > /dev/null 2>&1 || rc=$?
  check "${expected}" "${rc}" "guard: ${command}"
}

# Skipping the git hooks.
guard 2 'git commit --no-verify -m x'
guard 2 'git commit -m x --no-verify'
guard 2 'git push --no-verify origin x'
guard 2 'git -C /x commit --no-verify'
guard 2 'git commit -am x --no-verify&&ls'
guard 2 'git push origin HEAD --no-verify;'
guard 2 '(git commit -m x --no-verify)'
guard 2 'bash -c "git commit --no-verify"'
guard 2 'git commit --no-verif -m x'
guard 2 $'git commit -m x \\\n  --no-verify'
guard 2 'cd a && git commit -n -m x'
guard 2 'git commit -nm x'
guard 2 'git -c core.hooksPath=/dev/null commit -m x'
guard 0 'git commit -m x'
guard 0 'git commit --amend --no-edit'
guard 0 'git push origin x -n'
guard 0 'git commit -m x && git push -n origin x'
guard 0 'git log --grep=no-verify-ish'
guard 0 'echo --no-verify'
guard 0 'ls'

# Commit messages: their content is not a flag, a separator or a line break.
guard 2 $'git commit -m "$(cat <<\'EOF\'\nfix: x\n\nbody\nEOF\n)" --no-verify'
guard 2 $'git commit -m "$(cat <<\'EOF\'\nfix: x\nEOF\n)" -n'
guard 2 $'git commit -m "fix: x\n\nbody" --no-verify'
guard 2 'git commit -m "x && y" --no-verify'
guard 2 'git commit -m "a;b" --no-verify'
guard 2 'git commit -m "a|b" -n'
guard 0 $'git commit -m "$(cat <<\'EOF\'\nfix(web): handle a && b; c | d\n\nRefs: MON-1\nEOF\n)"'
guard 0 'git commit -m "Revert -n changes"'
guard 0 'git commit -m "fix: ui - fix -nan display"'
guard 0 'git commit -m "docs: mention core.hooksPath and --no-verify"'
guard 0 "git commit -m 'note: run pnpm cypress:run:updateSnapshot later'"
guard 0 'git commit -m "it'"'"'s fine" && git push -u origin x'

# Quoted flags and other ways around the git hooks.
guard 2 'git commit "-n" -m x'
guard 2 "git push \"--force\""
guard 2 "git push origin '+HEAD:main'"
guard 2 'git push --mirror'
guard 2 'git -c core.hookspath=/dev/null commit -m x'
guard 2 'git config core.hooksPath /tmp/e; git commit -m x'
guard 2 'HUSKY=0 git commit -m x'
guard 2 'GIT_CONFIG_KEY_0=core.hookspath GIT_CONFIG_VALUE_0=/x git commit -m x'
guard 2 "git -c alias.p='push --force' p"
guard 2 'git config remote.origin.push +HEAD:refs/heads/main && git push'
guard 0 'git config remote.origin.pushurl git@github.com:x/y.git'
guard 0 'git push --follow-tags'
guard 0 'git commit --fixup HEAD~1'
guard 0 'git checkout -f develop'
guard 0 'npm run x -- -n'

# Force pushes.
guard 2 'git push --force'
guard 2 'git push origin x --force-with-lease'
guard 2 'git push origin -f'
guard 2 'git push origin +main'
guard 0 'git push -u origin MON-1-web-feature-fix'

# Snapshot updates: one spec file only.
guard 2 'pnpm cypress:run:updateSnapshot'
guard 2 'pnpm cypress:run:updateSnapshots'
guard 2 'cypress run --env updateSnapshots=true'
guard 2 'pnpm cypress:run:updateSnapshot --spec "www/**/*.cypress.spec.tsx"'
guard 2 'pnpm cypress:run:updateSnapshots --spec a.tsx,b.tsx'
guard 2 'pnpm cypress:run:updateSnapshot --spec www/front_src'
guard 2 'pnpm cypress:run:updateSnapshot --spec "src/a b/*.cypress.spec.tsx"'
guard 2 'pnpm cypress:run:updateSnapshot --spec a.cypress.spec.tsx --spec b.cypress.spec.tsx'
guard 0 'pnpm cypress:run:updateSnapshot --spec www/a/Foo.cypress.spec.tsx'
guard 0 'pnpm cypress:run:updateSnapshots --spec=src/Foo.cypress.spec.tsx'
guard 0 'pnpm cypress:run:updateSnapshot -s www/a/Foo.cypress.spec.tsx'
guard 0 'pnpm cypress:run --spec "www/**/*.cypress.spec.tsx"'

# Fail open on unexpected input.
rc=0
printf 'not json' | "${HOOKS_DIR}/guard-bash.sh" > /dev/null 2>&1 || rc=$?
check 0 "${rc}" 'guard: invalid JSON'

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
