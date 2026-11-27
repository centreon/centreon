#!/bin/sh
# Throwaway CA + server certificate for the db-tls compose overlay
# (MON-214157) — PoC only. Regenerated into named volumes at `up` time,
# never committed to git: this script is the only thing checked in, no key
# or cert material lives in the repository.
set -eu

CA_DIR=/certs/ca
CA_KEY_DIR=/certs/ca-private
SERVER_DIR=/certs/server
mkdir -p "$CA_DIR" "$CA_KEY_DIR" "$SERVER_DIR"

if [ -f "$CA_DIR/ca.pem" ] && [ -f "$SERVER_DIR/server-cert.pem" ]; then
  echo "db-tls certs already present (reused across a restart without -v) — skipping."
  exit 0
fi

# ca-key.pem lives in its own volume (CA_KEY_DIR), mounted on no service
# other than this one — $CA_DIR (ca.pem, the public cert) is what every
# client mounts read-only, and it should never also carry the CA's private
# key just because they happen to share a directory.
openssl genrsa -out "$CA_KEY_DIR/ca-key.pem" 4096
openssl req -x509 -new -nodes -sha256 -days 3650 \
  -key "$CA_KEY_DIR/ca-key.pem" -out "$CA_DIR/ca.pem" \
  -subj "/CN=centreon-poc-db-tls-ca"

openssl genrsa -out "$SERVER_DIR/server-key.pem" 4096
openssl req -new -sha256 \
  -key "$SERVER_DIR/server-key.pem" -out /tmp/server.csr \
  -subj "/CN=db"
# SAN=db (the compose service name/hostname clients actually connect to) —
# a CN-only cert fails modern server-cert verification (CN fallback was
# removed from OpenSSL's hostname checking), so every client here that sets
# DATABASE_VERIFY_SERVER_CERT=1 would otherwise get a hostname mismatch.
printf 'subjectAltName=DNS:db\n' > /tmp/server-ext.cnf
openssl x509 -req -sha256 -days 3650 \
  -in /tmp/server.csr -CA "$CA_DIR/ca.pem" -CAkey "$CA_KEY_DIR/ca-key.pem" -CAcreateserial \
  -out "$SERVER_DIR/server-cert.pem" \
  -extfile /tmp/server-ext.cnf
rm -f /tmp/server.csr /tmp/server-ext.cnf "$CA_DIR/ca.srl"

chmod 600 "$CA_KEY_DIR/ca-key.pem"
# 644, not 600: confirmed the hard way — mariadbd runs as the `mysql` user
# from its very first exec (the official image's entrypoint does `exec
# gosu mysql ...` up front, not "start as root, drop later"), so a
# root-owned 600 key fails with "Unable to get private key" at startup.
# No UID to chown to is known from this container (a separate image), and
# this key backs a disposable, no-real-secret-value PoC cert regardless —
# world-readable is an acceptable trade here, not one to make for a real CA.
chmod 644 "$CA_DIR/ca.pem" "$SERVER_DIR/server-cert.pem" "$SERVER_DIR/server-key.pem"
echo "db-tls certs generated (CA + server cert, SAN=db)."
