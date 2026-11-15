#!/usr/bin/env bash
# SELinux enforcing smoke test for an EL Centreon central with the *-selinux
# packages installed. Run as root on a disposable VM; --help lists the settings.
set -euo pipefail

usage() {
  cat <<'EOF'
Usage: selinux-enforcing-smoke-test.sh   (as root, settings via environment)

Relabels the Centreon paths, switches SELinux to enforcing, restarts the stack,
calls the API, then fails on any AVC denial logged since the start.

  CENTREON_API_PASSWORD  required, password of CENTREON_API_USER
  CENTREON_API_USER      default: admin
  CENTREON_URL           default: http://127.0.0.1 (CURL_INSECURE=1 adds -k)
  SERVICES               units restarted, default: php-fpm httpd cbd centengine gorgoned
  DAEMON_UNITS           units that must not run unconfined, default: cbd centengine gorgoned
  FAIL_ON_UNCONFINED     1: unconfined_service_t daemon is a failure, default: 0 (warning)
  RUN_CONFIG_EXPORT      1: generate-and-reload all pollers via the API, default: 1
  SETTLE_SECONDS         wait after restarts and export, default: 15
  KEEP_ENFORCING         1: stay enforcing on exit, default: 0 (restore initial mode)
  AUDIT_LOG              default: /var/log/audit/audit.log

Relabelled paths come from the loaded policy (semanage fcontext -l) because the
-selinux packages ship compiled .pp modules only, not the .fc sources.
EOF
}
[[ "${1:-}" != "-h" && "${1:-}" != "--help" ]] || { usage; exit 0; }

CENTREON_URL=${CENTREON_URL:-http://127.0.0.1}
CENTREON_API_USER=${CENTREON_API_USER:-admin}
SERVICES=${SERVICES:-php-fpm httpd cbd centengine gorgoned}
DAEMON_UNITS=${DAEMON_UNITS:-cbd centengine gorgoned}
FAIL_ON_UNCONFINED=${FAIL_ON_UNCONFINED:-0}
RUN_CONFIG_EXPORT=${RUN_CONFIG_EXPORT:-1}
SETTLE_SECONDS=${SETTLE_SECONDS:-15}
KEEP_ENFORCING=${KEEP_ENFORCING:-0}
AUDIT_LOG=${AUDIT_LOG:-/var/log/audit/audit.log}

# Paths matching this (or labelled with a Centreon type) are relabelled.
PATH_PATTERN='centreon|centengine|cbd|cbwd|gorgone|centagent'
# Never restorecon -R these, a regex like /usr/lib/centreon[^/]* would yield them.
TOO_BROAD_ROOTS=" / /etc /opt /run /usr /usr/bin /usr/sbin /usr/lib /usr/lib64 /usr/share /var /var/cache /var/lib /var/log /var/run /var/spool "

declare -a RESULTS=()
failures=0
warnings=0
work=$(mktemp -d)
original_mode=""

log() { printf '\n==> %s\n' "$*"; }
record() { # record STATUS CHECK DETAIL
  RESULTS+=("$(printf '%-5s %-28s %s' "$1" "$2" "$3")")
  case $1 in
    FAIL) failures=$((failures + 1)) ;;
    WARN) warnings=$((warnings + 1)) ;;
  esac
}

cleanup() {
  if [[ -n "$original_mode" && "$KEEP_ENFORCING" != 1 && "$original_mode" != Enforcing ]]; then
    setenforce 0 || true
    echo "SELinux mode restored to $original_mode"
  fi
  rm -rf "$work"
}
trap cleanup EXIT

die() { echo "ERROR: $*" >&2; exit 2; }

json_escape() {
  local s=$1
  s=${s//\\/\\\\}
  s=${s//\"/\\\"}
  printf '%s' "$s"
}

# regex_to_root REGEX: longest literal directory/file prefix of an fcontext regex.
regex_to_root() {
  local re=$1 prefix
  prefix=${re%%[\(\[\*\?\+\\\|\{\^\$]*}
  if [[ "$prefix" == "$re" ]]; then
    printf '%s\n' "$re"
  elif [[ "${re:${#prefix}:2}" == "(/" ]]; then
    printf '%s\n' "$prefix"
  else
    printf '%s\n' "${prefix%/*}"
  fi
}

# api METHOD PATH [OUTFILE] -> prints the HTTP code, body in OUTFILE.
api() {
  local method=$1 path=$2 out=${3:-$work/body.json}
  local -a opts=(-sS -o "$out" -w '%{http_code}' --max-time 120 -X "$method"
    -H 'Content-Type: application/json' -H "X-AUTH-TOKEN: ${token:-}")
  [[ "${CURL_INSECURE:-0}" != 1 ]] || opts+=(-k)
  local code
  code=$(curl "${opts[@]}" "${CENTREON_URL}${path}") || true
  printf '%s\n' "${code:-000}"
}

# --- preconditions --------------------------------------------------------
[[ $EUID -eq 0 ]] || die "must run as root"
: "${CENTREON_API_PASSWORD:?CENTREON_API_PASSWORD must be set}"
for cmd in curl getenforce setenforce semanage semodule restorecon systemctl ps awk; do
  command -v "$cmd" >/dev/null || die "missing command: $cmd"
done
[[ -r "$AUDIT_LOG" ]] || die "cannot read $AUDIT_LOG (is auditd running?)"
original_mode=$(getenforce)
[[ "$original_mode" != Disabled ]] || die "SELinux is disabled, a reboot with selinux=1 is required"

START_EPOCH=$(date +%s)
log "Start epoch $START_EPOCH, SELinux mode $original_mode"
echo "Loaded Centreon modules:"
semodule -l | grep -i centreon | sed 's/^/  /' || echo "  none"

# --- 1. relabel -------------------------------------------------------------
log "Relabelling Centreon paths from the loaded file contexts"
semanage fcontext -l \
  | awk -v pat="$PATH_PATTERN" 'NF >= 3 && $1 ~ "^/" && ($1 ~ pat || $NF ~ /:centreon_[a-z_]+_t:/) { print $1 }' \
  | sort -u >"$work/regexes.txt"
declare -A roots=()
while IFS= read -r re; do
  root=$(regex_to_root "$re")
  [[ -n "$root" && "$root" != "/" ]] || continue
  root=${root%/}
  if [[ "$TOO_BROAD_ROOTS" == *" $root "* ]]; then
    echo "  skip too broad root $root (from $re)"
    continue
  fi
  if [[ -e "$root" ]]; then
    roots[$root]=1
  fi
done <"$work/regexes.txt"
mapfile -t relabel_roots < <(printf '%s\n' "${!roots[@]}" | LC_ALL=C sort)
echo "  $(wc -l <"$work/regexes.txt") fcontext entries -> ${#relabel_roots[@]} existing roots"
if (( ${#relabel_roots[@]} == 0 )); then
  record FAIL "relabel" "no Centreon file context found, are the -selinux packages installed?"
else
  restorecon -R -v "${relabel_roots[@]}" >"$work/restorecon.txt" 2>&1 || true
  relabelled=$(grep -c '^Relabeled' "$work/restorecon.txt" || true)
  if (( relabelled > 0 )); then
    record WARN "relabel" "$relabelled file(s) had a wrong label before restorecon"
    head -n 20 "$work/restorecon.txt" | sed 's/^/  /'
  else
    record PASS "relabel" "all Centreon files were already correctly labelled"
  fi
fi

# --- 2. enforce and restart ------------------------------------------------
log "Switching SELinux to enforcing"
setenforce 1
if [[ "$(getenforce)" == Enforcing ]]; then
  record PASS "setenforce" "Enforcing"
else
  record FAIL "setenforce" "mode is $(getenforce)"
fi

log "Restarting services: $SERVICES"
for svc in $SERVICES; do
  if ! systemctl cat "$svc" >/dev/null 2>&1; then
    record WARN "restart $svc" "unit not found, skipped"
    continue
  fi
  if systemctl restart "$svc" && systemctl is-active --quiet "$svc"; then
    record PASS "restart $svc" "active"
  else
    record FAIL "restart $svc" "not active after restart"
    systemctl status --no-pager --lines=10 "$svc" || true
  fi
done
sleep "$SETTLE_SECONDS"

# --- 3. API checks ---------------------------------------------------------
log "API checks against $CENTREON_URL"
token=""
login_body=$(printf '{"security":{"credentials":{"login":"%s","password":"%s"}}}' \
  "$(json_escape "$CENTREON_API_USER")" "$(json_escape "$CENTREON_API_PASSWORD")")
login_opts=(-sS -o "$work/login.json" -w '%{http_code}' --max-time 60
  -H 'Content-Type: application/json' --data-binary @-)
[[ "${CURL_INSECURE:-0}" != 1 ]] || login_opts+=(-k)
code=$(curl "${login_opts[@]}" "${CENTREON_URL}/centreon/api/latest/login" <<<"$login_body") || true
code=${code:-000}
token=$(sed -n 's/.*"token"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$work/login.json" 2>/dev/null || true)
if [[ "$code" == 200 && -n "$token" ]]; then
  record PASS "api login" "HTTP $code"
else
  record FAIL "api login" "HTTP $code, no token"
fi

if [[ -n "$token" ]]; then
  code=$(api GET "/centreon/api/latest/monitoring/resources?limit=10")
  if [[ "$code" == 200 ]] && grep -q '"result"' "$work/body.json"; then
    record PASS "api monitoring/resources" "HTTP $code"
  else
    record FAIL "api monitoring/resources" "HTTP $code"
  fi

  if [[ "$RUN_CONFIG_EXPORT" == 1 ]]; then
    code=$(api GET "/centreon/api/latest/configuration/monitoring-servers/generate-and-reload")
    if [[ "$code" =~ ^20[0-9]$ ]]; then
      record PASS "api generate-and-reload" "HTTP $code"
    else
      record FAIL "api generate-and-reload" "HTTP $code: $(head -c 300 "$work/body.json" 2>/dev/null)"
    fi
    sleep "$SETTLE_SECONDS"
  fi
else
  record FAIL "api checks" "skipped, login failed"
fi

# --- 4. daemon contexts ----------------------------------------------------
log "Process contexts of $DAEMON_UNITS"
for unit in $DAEMON_UNITS; do
  cgroup=$(systemctl show -p ControlGroup --value "$unit" 2>/dev/null || true)
  if [[ -n "$cgroup" && -r "/sys/fs/cgroup${cgroup}/cgroup.procs" ]]; then
    mapfile -t pids <"/sys/fs/cgroup${cgroup}/cgroup.procs"
  else
    mapfile -t pids < <(systemctl show -p MainPID --value "$unit" 2>/dev/null | grep -v '^0$')
  fi
  if (( ${#pids[@]} == 0 )); then
    record WARN "context $unit" "no running process"
    continue
  fi
  pid_list=$(IFS=,; printf '%s' "${pids[*]}")
  types=$(ps -o label= -p "$pid_list" 2>/dev/null | awk -F: '{ print $3 }' | sort -u | tr '\n' ' ' || true)
  echo "  $unit: ${types% }"
  if [[ " $types " == *" unconfined_service_t "* ]]; then
    if [[ "$FAIL_ON_UNCONFINED" == 1 ]]; then
      record FAIL "context $unit" "runs as unconfined_service_t"
    else
      record WARN "context $unit" "runs as unconfined_service_t (FAIL_ON_UNCONFINED=1 to fail)"
    fi
  else
    record PASS "context $unit" "${types% }"
  fi
done

# --- 5. AVC denials since start ---------------------------------------------
# ausearch -ts proved unreliable, so filter audit.log on msg=audit(EPOCH.xxx:id).
log "SELinux denials since $START_EPOCH in $AUDIT_LOG"
awk -v start="$START_EPOCH" '
  /(^| )type=(AVC|USER_AVC|SELINUX_ERR|USER_SELINUX_ERR) / {
    if ($0 ~ /(^| )type=(AVC|USER_AVC) / && $0 !~ /denied/) next
    if (match($0, /msg=audit\([0-9]+/) && substr($0, RSTART + 10, RLENGTH - 10) + 0 >= start) print
  }' "$AUDIT_LOG" >"$work/avc.txt"
avc_count=$(wc -l <"$work/avc.txt")
if (( avc_count == 0 )); then
  record PASS "avc denials" "none"
else
  record FAIL "avc denials" "$avc_count record(s)"
  echo "Distinct denials (count comm perms scontext -> tcontext:tclass permissive):"
  sed -nE 's/.*denied +\{ ([^}]*) \}.* comm="([^"]*)".* scontext=[^:]*:[^:]*:([^: ]*)[^ ]* tcontext=[^:]*:[^:]*:([^: ]*)[^ ]* tclass=([^ ]*)( permissive=([01]))?.*/\2 {\1} \3 -> \4:\5 \7/p' \
    "$work/avc.txt" | sort | uniq -c | sort -rn | sed 's/^/  /'
  echo "Raw records (first 30):"
  head -n 30 "$work/avc.txt" | sed 's/^/  /'
  if command -v audit2allow >/dev/null; then
    echo "audit2allow suggestion (review, do not paste blindly):"
    audit2allow <"$work/avc.txt" 2>/dev/null | sed 's/^/  /' || true
  fi
fi

# --- summary ---------------------------------------------------------------
summary=$(
  echo "SELinux enforcing smoke test summary"
  printf '%s\n' "${RESULTS[@]}"
  echo "Result: $failures failure(s), $warnings warning(s)"
)
log "Summary"
echo "$summary"
if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
  fence='```'
  printf '%s\n%s\n%s\n' "$fence" "$summary" "$fence" >>"$GITHUB_STEP_SUMMARY" || true
fi
(( failures == 0 ))
