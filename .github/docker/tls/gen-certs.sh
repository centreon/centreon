#!/usr/bin/env bash
# Generates self-signed CA + server cert for local TLS testing of MON-11007.
# Intentionally permissive (chmod 644 on key) - dev local only, not for prod.
set -euo pipefail

CERT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/certs"
mkdir -p "$CERT_DIR"
cd "$CERT_DIR"

if [[ -f ca.pem && -f server-cert.pem && -f server-key.pem ]]; then
  echo "Certs already present in $CERT_DIR, nothing to do."
  echo "Remove them manually to regenerate."
  exit 0
fi

echo "Generating CA..."
openssl genrsa -out ca-key.pem 2048 2>/dev/null
openssl req -new -x509 -nodes -days 3650 -key ca-key.pem -out ca.pem \
  -subj "/CN=centreon-tls-test-ca" 2>/dev/null

echo "Generating server cert (SAN: db, db-remote, localhost, 127.0.0.1)..."
openssl genrsa -out server-key.pem 2048 2>/dev/null

cat > server-ext.cnf <<EOF
subjectAltName = DNS:db,DNS:db-remote,DNS:localhost,IP:127.0.0.1
EOF

openssl req -new -key server-key.pem -out server.csr \
  -subj "/CN=centreon-tls-test-db" 2>/dev/null

openssl x509 -req -in server.csr -days 3650 \
  -CA ca.pem -CAkey ca-key.pem -CAcreateserial \
  -out server-cert.pem -extfile server-ext.cnf 2>/dev/null

chmod 644 ca.pem server-cert.pem ca-key.pem server-key.pem

rm -f server.csr server-ext.cnf ca.srl

echo ""
echo "Done. Files in $CERT_DIR:"
ls -la "$CERT_DIR"
echo ""
echo "Quick verification:"
openssl x509 -in server-cert.pem -noout -subject -issuer -ext subjectAltName
