#!/usr/bin/env bash
#
# Generate the self-signed certificate used by the reverse proxy.
# Certificates land in reverse-proxy/certs/ and are git-ignored.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
CERTS="$DIR/certs"

mkdir -p "$CERTS"
openssl req -x509 -nodes -newkey rsa:2048 \
  -keyout "$CERTS/proxy.key" -out "$CERTS/proxy.crt" \
  -days 825 -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

echo "Certificate written to $CERTS/{proxy.crt,proxy.key}"
