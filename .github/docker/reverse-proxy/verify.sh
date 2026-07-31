#!/usr/bin/env bash
#
# Verify the reverse-proxy wiring from the client side, in pure bash.
#
# Relies on the X-Effective-Scheme diagnostic header exposed by apache
# (apache-debug-scheme.conf, mounted by the proxy overlay). Curls Centreon
# through the proxy (HTTPS) and directly (HTTP) and prints the scheme headers.
#
# Usage:
#   ./verify.sh
# Env overrides: PROXY_URL, DIRECT_URL
set -euo pipefail

PROXY_URL="${PROXY_URL:-https://localhost:4443/centreon/}"
DIRECT_URL="${DIRECT_URL:-http://localhost:4000/centreon/}"

echo "» Through the proxy (HTTPS) — expect X-Effective-Scheme: https"
curl -skI "$PROXY_URL" | grep -i 'x-effective-scheme' || echo "  (header not found)"

echo
echo "» Direct to web (HTTP) — expect X-Effective-Scheme: http"
curl -sI "$DIRECT_URL" | grep -i 'x-effective-scheme' || echo "  (header not found)"
