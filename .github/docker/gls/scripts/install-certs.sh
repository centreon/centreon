#!/bin/sh
# Negative TLS test only (run as root): swaps the central cbd server pair.
#   install-certs.sh rogue   server pair signed by another CA (from /certs-src)
#   install-certs.sh clear   removes the server pair: cbd re-signs one with its kept CA
# Never touches ca.crt / ca.key (contracts § 8.1).
set -eu
dst=/mnt/broker-certs
case "${1:?rogue|clear}" in
  rogue)
    [ -s /certs-src/rogue.crt ] || { echo "no /certs-src/rogue.crt" >&2; exit 1; }
    cp /certs-src/rogue.crt "$dst/central.crt"
    cp /certs-src/rogue.key "$dst/central.key"
    chown 902:902 "$dst/central.crt" "$dst/central.key"
    chmod 0644 "$dst/central.crt"
    chmod 0600 "$dst/central.key"
    ;;
  clear) rm -f "$dst/central.crt" "$dst/central.key" ;;
  *) echo "unknown mode $1" >&2; exit 2 ;;
esac
ls -ln "$dst"
