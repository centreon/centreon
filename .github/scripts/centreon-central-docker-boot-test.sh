#!/usr/bin/env bash
# shellcheck disable=SC2016 # check() commands are expanded inside the container, not by this shell.
# Boot test of one split central image (httpd / fpm / cron / rrdcached) on one platform.
#
# Standalone checks only (no database, no other container): the image's own
# tooling starts and its configuration parses. The end-to-end behaviour is
# covered by centreon-central-docker-compose-smoke.sh.
#
# Env:
#   IMAGE                 image reference to test (already pulled)
#   ROLE                  httpd | fpm | cron | rrdcached
#   PLATFORM              linux/amd64 | linux/arm64
#   SUMMARY_FRAGMENT_FILE optional markdown fragment written for the job summary
set -euo pipefail

: "${IMAGE:?IMAGE is required}"
: "${ROLE:?ROLE is required}"
: "${PLATFORM:?PLATFORM is required}"
SUMMARY_FRAGMENT_FILE="${SUMMARY_FRAGMENT_FILE:-/dev/null}"
LOG_FILE="${LOG_FILE:-/tmp/centreon-central-boot-test.log}"
: > "$LOG_FILE"

failures=0
results=()

# Runs a command inside a throwaway container with the image's entrypoint
# bypassed: the entrypoints need a database and sibling containers.
check() {
  check_as root "$@"
}

# Same as check, as another user (bin/console refuses to run as root).
check_as() {
  local user="$1" name="$2"; shift 2
  if docker run --rm --platform "$PLATFORM" --user "$user" --entrypoint /bin/sh "$IMAGE" -c "$*" >>"$LOG_FILE" 2>&1; then
    results+=("| ${name} | :white_check_mark: |")
  else
    results+=("| ${name} | :x: |")
    failures=$((failures + 1))
    echo "::error::${ROLE} (${PLATFORM}): ${name} failed, see ${LOG_FILE}"
  fi
  echo "----- ${name}" >>"$LOG_FILE"
}

arch=$(docker image inspect --format '{{.Architecture}}' "$IMAGE")
expected_arch="${PLATFORM#linux/}"
if [[ "$arch" != "$expected_arch" ]]; then
  echo "::error::${IMAGE} is ${arch}, expected ${expected_arch}"
  exit 1
fi

case "$ROLE" in
  httpd)
    check "static tree present" 'test -f /usr/share/centreon/www/index.html && test -d /usr/share/centreon/www/static'
    check "no PHP source shipped" '! find /usr/share/centreon/www -name "*.php" -print -quit | grep -q .'
    check "httpd binary runs" 'httpd -v'
    check "entrypoint present" 'test -x /usr/local/lib/centreon-httpd/container.sh'
    ;;
  fpm)
    check "php-fpm config valid" 'php-fpm -t'
    check "PHP starts without warning" 'out=$(php -m 2>&1) && ! printf "%s" "$out" | grep -qi warning'
    check "application tree present" 'test -f /usr/share/centreon/bin/console && test -d /usr/share/centreon/vendor'
    check_as www-data "Symfony console boots (www-data)" 'cd /usr/share/centreon && php bin/console --version'
    check "bin/ scripts are executable (git tracks them 644)" 'test -x /usr/share/centreon/bin/generateSqlLite && test -x /usr/share/centreon/bin/centFillTrapDB'
    check "real centengine for config-verification" 'centengine -V'
    check "redis extension loaded (MON-214095, Valkey sessions)" 'php -m | grep -qix redis'
    check "entrypoint present" 'test -x /usr/local/lib/centreon-fpm/container.sh'
    ;;
  cron)
    check "PHP CLI starts without warning" 'out=$(php -m 2>&1) && ! printf "%s" "$out" | grep -qi warning'
    check "cron daemon present" 'command -v cron'
    check "Perl present" 'perl -v'
    check "application tree present" 'test -d /usr/share/centreon/cron'
    check "entrypoint present" 'test -x /usr/local/lib/centreon-cron/container.sh'
    ;;
  rrdcached)
    check "rrdcached binary present" 'command -v rrdcached'
    check_as 902 "runs as the broker UID (902)" 'test "$(id -u)" = 902'
    ;;
  *)
    echo "::error::unknown ROLE '${ROLE}'"
    exit 1
    ;;
esac

{
  echo "#### ${ROLE} (${PLATFORM})"
  echo
  echo "| Check | Result |"
  echo "| --- | --- |"
  printf '%s\n' "${results[@]}"
  echo
} >"$SUMMARY_FRAGMENT_FILE"

if (( failures > 0 )); then
  echo "${failures} check(s) failed for ${ROLE} on ${PLATFORM}"
  exit 1
fi
echo "All checks passed for ${ROLE} on ${PLATFORM}"
