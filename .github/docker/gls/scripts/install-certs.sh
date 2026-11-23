#!/bin/sh
# Installs the central cbd TLS identity into its cert volume (run as root).
#   install-certs.sh central   CA-signed cert (normal run)
#   install-certs.sh rogue     cert NOT signed by the pollers' CA (negative test)
# Source: run-time throwaway certs from gls-e2e.sh certs, mounted read-only.
set -eu
set_name="${1:-central}"
src=/certs-src
dst=/mnt/broker-certs
[ -s "$src/$set_name.crt" ] || { echo "no $src/$set_name.crt: run gls-e2e.sh certs first" >&2; exit 1; }
cp "$src/ca.crt" "$dst/ca.crt"
cp "$src/$set_name.crt" "$dst/central.crt"
cp "$src/$set_name.key" "$dst/central.key"
chown -R 902:902 "$dst"
chmod 0755 "$dst"
chmod 0644 "$dst/ca.crt" "$dst/central.crt"
chmod 0640 "$dst/central.key"
echo "central cbd identity: $set_name"
