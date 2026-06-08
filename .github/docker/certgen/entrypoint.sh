#!/bin/sh
#
# Generates a persistent Root CA + a multi-SAN leaf for the centreon
# docker-compose stack. Same shape as centreon-images/aws/centreon-ova-builder/
# ova_files/centreon-central/gen_cert.sh, minus the Apache/systemctl pieces and
# the platform detection (we run only in this alpine image).
#
# Output layout in /out (mounted as the `certs` named volume):
#   /out/CA/rootCA.{key,pem}     — Root CA, persistent across runs
#   /out/rootCA.pem              — copy of CA cert for consumers
#   /out/server.{pem,key}        — multi-SAN leaf, regenerated only when
#                                  expired/expiring or when SANs change
#   /out/db/server.{pem,key}     — copy with bitnami MariaDB uid (1001:1001),
#                                  key mode 0600 so MariaDB accepts it
#   /out/certs.env               — sourceable manifest with consumer-side paths
#
# Args: SANs as positional parameters.
#   - Plain names ("web", "localhost") → DNS entries
#   - Plain dotted-numeric ("127.0.0.1") → IP entries
#   - Explicit "dns:<name>" or "ip:<addr>" prefixes always honored
#     (use the prefix form for IPv6 since the heuristic doesn't cover it).

set -eu

OUT="${OUT:-/out}"
CAROOT="$OUT/CA"
DB_UID="${DB_UID:-1001}"
DB_GID="${DB_GID:-1001}"
CA_DAYS="${CA_DAYS:-3650}"
LEAF_DAYS="${LEAF_DAYS:-397}"
CA_SUBJ="${CA_SUBJ:-/CN=Centreon Dev CA/O=Centreon/OU=R&D}"
LEAF_SUBJ_O="${LEAF_SUBJ_O:-Centreon}"
RENEW_THRESHOLD_DAYS="${RENEW_THRESHOLD_DAYS:-30}"

if [ "$#" -eq 0 ]; then
  echo "[certgen] FATAL: no SANs supplied. Pass them as args (e.g. web remote-server db localhost 127.0.0.1)" >&2
  exit 2
fi

mkdir -p "$CAROOT" "$OUT/db"

# Translate positional args into normalized DNS:/IP: tokens used both for SAN
# comparison and for building the openssl ext file below.
emit_sans() {
  for san in "$@"; do
    case "$san" in
      dns:*|DNS:*) echo "DNS:${san#*:}" ;;
      ip:*|IP:*)   echo "IP:${san#*:}" ;;
      *[!0-9.]*)   echo "DNS:$san" ;;
      *)           echo "IP:$san" ;;
    esac
  done
}

# Idempotency: reuse the leaf when it is still valid AND its SAN set matches
# what was requested. Regenerate otherwise.
#
# Reuse skips leaf reissuance only — the derived artifacts (steps 3-5: the
# /out/rootCA.pem copy, the /out/db/* MariaDB copies, and /out/certs.env) are
# re-provisioned unconditionally below so a partially-pruned cert volume
# self-heals instead of silently breaking DB TLS startup.
REGEN_LEAF=1
if [ -f "$OUT/server.pem" ] && [ -f "$OUT/server-key.pem" ] && [ -f "$OUT/rootCA.pem" ]; then
  if openssl x509 -checkend $((RENEW_THRESHOLD_DAYS * 86400)) -noout -in "$OUT/server.pem" >/dev/null 2>&1; then
    cur_sans=$(openssl x509 -noout -ext subjectAltName -in "$OUT/server.pem" 2>/dev/null \
      | grep -oE '(DNS|IP Address):[^,]+' \
      | sed 's/IP Address:/IP:/' \
      | tr -d ' ' | sort -u || true)
    want_sans=$(emit_sans "$@" | sort -u)
    if [ "$cur_sans" = "$want_sans" ]; then
      echo "[certgen] existing leaf valid >${RENEW_THRESHOLD_DAYS}d and SANs unchanged, reusing leaf (re-provisioning derived artifacts)"
      REGEN_LEAF=0
    else
      echo "[certgen] SAN set changed, regenerating leaf"
    fi
  else
    echo "[certgen] leaf expires within ${RENEW_THRESHOLD_DAYS}d, regenerating"
  fi
fi

# 1. Root CA — persisted across runs in the named volume.
if [ ! -f "$CAROOT/rootCA.key" ] || [ ! -f "$CAROOT/rootCA.pem" ]; then
  echo "[certgen] generating Root CA"
  openssl genrsa -out "$CAROOT/rootCA.key" 4096
  chmod 400 "$CAROOT/rootCA.key"
  openssl req -x509 -new -nodes -key "$CAROOT/rootCA.key" \
    -sha256 -days "$CA_DAYS" \
    -subj "$CA_SUBJ" \
    -out "$CAROOT/rootCA.pem"
fi

# 2. Leaf — fresh on every regeneration. CSR and ext file live in tmpfs and
#    are removed on exit so they never leak into the cert volume. Skipped on
#    reuse (REGEN_LEAF=0); the copy/manifest steps below still run.
if [ "$REGEN_LEAF" = 1 ]; then
  EXT=$(mktemp)
  CSR=$(mktemp)
  trap 'rm -f "$EXT" "$CSR"' EXIT

  {
    echo "[v3_req]"
    echo "basicConstraints = CA:FALSE"
    echo "keyUsage         = digitalSignature, keyEncipherment"
    echo "extendedKeyUsage = serverAuth"
    echo "subjectAltName   = @alt_names"
    echo
    echo "[alt_names]"
    d=0
    i=0
    for san in "$@"; do
      case "$san" in
        dns:*|DNS:*) d=$((d + 1)); echo "DNS.$d = ${san#*:}" ;;
        ip:*|IP:*)   i=$((i + 1)); echo "IP.$i  = ${san#*:}" ;;
        *[!0-9.]*)   d=$((d + 1)); echo "DNS.$d = $san" ;;
        *)           i=$((i + 1)); echo "IP.$i  = $san" ;;
      esac
    done
  } > "$EXT"

  echo "[certgen] generating leaf for SANs: $*"
  openssl genrsa -out "$OUT/server-key.pem" 2048
  openssl req -new -key "$OUT/server-key.pem" -out "$CSR" \
    -subj "/CN=${1#*:}/O=$LEAF_SUBJ_O"
  openssl x509 -req -in "$CSR" \
    -CA "$CAROOT/rootCA.pem" -CAkey "$CAROOT/rootCA.key" \
    -CAserial "$CAROOT/rootCA.srl" -CAcreateserial \
    -out "$OUT/server.pem" -days "$LEAF_DAYS" -sha256 \
    -extfile "$EXT" -extensions v3_req
fi

# 3. Expose the CA cert next to the leaf for easy mounting.
# Cert + CA are public artifacts — world-readable is fine.
# The leaf KEY is the only sensitive bit on this shared volume: keep it
# root-readable only (0640 root:root → owner rw, group r, others none).
# Apache reads it via its root master process before dropping privileges to
# apache/www-data workers, so 0640 is sufficient. MariaDB has its own chowned
# copy under /out/db/ below (uid 1001, 0600); other consumers that need the
# key as non-root should follow the same per-consumer copy pattern.
cp "$CAROOT/rootCA.pem" "$OUT/rootCA.pem"
chmod 0644 "$OUT/rootCA.pem" "$OUT/server.pem"
chmod 0640 "$OUT/server-key.pem"

# 4. Per-consumer copy for DB services. Bitnami MariaDB runs as uid 1001 and
#    refuses a world-readable key. The cp+chown leaves the shared leaf
#    untouched so web/remote-server can still read it.
cp "$OUT/server.pem"     "$OUT/db/server.pem"
cp "$OUT/server-key.pem" "$OUT/db/server-key.pem"
chmod 0644 "$OUT/db/server.pem"
chmod 0600 "$OUT/db/server-key.pem"
chown "$DB_UID:$DB_GID" "$OUT/db/server.pem" "$OUT/db/server-key.pem"

# 5. Sourceable manifest with the consumer-side mount paths.
cat > "$OUT/certs.env" <<EOF
ROOTCA_PATH=/etc/pki/centreon-tls/rootCA.pem
SERVER_CERT_PATH=/etc/pki/centreon-tls/server.pem
SERVER_KEY_PATH=/etc/pki/centreon-tls/server-key.pem
DB_CERT_PATH=/certs/db/server.pem
DB_KEY_PATH=/certs/db/server-key.pem
EOF

echo "[certgen] done"
