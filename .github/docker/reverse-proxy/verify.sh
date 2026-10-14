#!/usr/bin/env bash
#
# Verify the reverse-proxy wiring from the client side, in pure bash.
#
# Relies on the X-Effective-Scheme diagnostic header exposed by apache
# (apache-debug-scheme.conf, mounted by the proxy overlay). Curls Centreon
# through the proxy (HTTPS, certificate validated with --cacert) and directly
# (HTTP), and asserts the effective scheme is exactly https / http. Exits
# non-zero if either check fails.
#
# Usage:
#   ./verify.sh
# Env overrides: PROXY_URL, DIRECT_URL, CACERT
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
PROXY_URL="${PROXY_URL:-https://localhost:4443/centreon/}"
DIRECT_URL="${DIRECT_URL:-http://localhost:4000/centreon/}"
CACERT="${CACERT:-$DIR/certs/proxy.crt}"

fail=0

# check <label> <expected-scheme> <curl args...>
check() {
    local label="$1" expected="$2"
    shift 2
    local value
    value="$(curl -sI "$@" 2>/dev/null | tr -d '\r' \
        | awk -F': ' 'tolower($1) == "x-effective-scheme" { print $2 }')" || true
    if [ "$value" = "$expected" ]; then
        echo "  PASS ${label} → X-Effective-Scheme: ${value}"
    else
        echo "  FAIL ${label} → X-Effective-Scheme: ${value:-<absent>} (expected ${expected})"
        fail=1
    fi
}

echo "» Through the proxy (HTTPS) — expect https"
check "proxy" "https" --cacert "$CACERT" "$PROXY_URL"

echo "» Direct to web (HTTP) — expect http"
check "direct" "http" "$DIRECT_URL"

exit "$fail"
