#!/bin/sh
# Generates a Root CA + multi-SAN leaf for the centreon docker-compose stack.
# Outputs land in /out (volume-mounted as the `certs` named volume).
# All positional args are passed to mkcert as SANs (DNS names + IPs).

set -eu

OUT="${OUT:-/out}"
CAROOT="$OUT/CA"
DB_UID="${DB_UID:-1001}"
DB_GID="${DB_GID:-1001}"
RENEW_THRESHOLD_DAYS="${RENEW_THRESHOLD_DAYS:-30}"

export CAROOT

mkdir -p "$CAROOT" "$OUT/db"

# Idempotency: reuse existing certs if leaf is valid for more than the renewal threshold.
if [ -f "$OUT/rootCA.pem" ] && [ -f "$OUT/server.pem" ] && [ -f "$OUT/server-key.pem" ]; then
  if openssl x509 -checkend $((RENEW_THRESHOLD_DAYS * 86400)) -noout -in "$OUT/server.pem" >/dev/null 2>&1; then
    echo "[certgen] existing leaf cert valid >${RENEW_THRESHOLD_DAYS}d, skipping reissuance"
    exit 0
  fi
  echo "[certgen] existing leaf cert expires within ${RENEW_THRESHOLD_DAYS}d, regenerating"
fi

if [ "$#" -eq 0 ]; then
  echo "[certgen] FATAL: no SANs supplied. Pass them as args (e.g. web remote-server db localhost 127.0.0.1)" >&2
  exit 2
fi

echo "[certgen] generating Root CA and leaf cert for SANs: $*"

# mkcert auto-generates the CA on first invocation in CAROOT.
mkcert -cert-file "$OUT/server.pem" -key-file "$OUT/server-key.pem" "$@"

cp "$CAROOT/rootCA.pem" "$OUT/rootCA.pem"
chmod 0644 "$OUT/rootCA.pem" "$OUT/server.pem" "$OUT/server-key.pem"

# Per-consumer copy for DB services (bitnami MariaDB runs as uid 1001 and refuses
# world-readable keys). cp + chown keeps the shared leaf untouched.
cp "$OUT/server.pem"     "$OUT/db/server.pem"
cp "$OUT/server-key.pem" "$OUT/db/server-key.pem"
chmod 0644 "$OUT/db/server.pem"
chmod 0600 "$OUT/db/server-key.pem"
chown "$DB_UID:$DB_GID" "$OUT/db/server.pem" "$OUT/db/server-key.pem"

# Sourceable manifest with the consumer-side paths. Optional but cheap.
cat > "$OUT/certs.env" <<EOF
ROOTCA_PATH=/etc/pki/centreon-tls/rootCA.pem
SERVER_CERT_PATH=/etc/pki/centreon-tls/server.pem
SERVER_KEY_PATH=/etc/pki/centreon-tls/server-key.pem
DB_CERT_PATH=/certs/db/server.pem
DB_KEY_PATH=/certs/db/server-key.pem
EOF

echo "[certgen] done"
