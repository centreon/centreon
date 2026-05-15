#!/bin/bash
#
# 16-tls-gorgone.sh — append Perl DBI SSL attributes to gorgone's DB DSN in
# HTTPS mode.
#
# The Centreon install (15-installation.sh) writes gorgone's DB config to
# /etc/centreon/config.d/10-database.yaml with plaintext DSNs by default.
# When --require-secure-transport=ON is set on the DB, gorgone's plaintext
# connections are rejected. We patch the DSNs here so Perl DBI uses TLS.
#
# Numbering: 16 — runs after 15-installation.sh produces the YAML and well
# before 70-gorgone_background.sh starts gorgone.
#
# Stopgap until centreon-collect emits the SSL DSN attributes natively from
# the gorgone config generator (mirror of centreon/centreon PR #9237's pattern
# for the PHP path). When that lands this script becomes a no-op (its
# idempotency check sees mysql_ssl already present) and can be removed.

CERT_DIR=/etc/pki/centreon-tls
CA_PEM="$CERT_DIR/rootCA.pem"
CONFIG=/etc/centreon/config.d/10-database.yaml

# HTTP mode (no certs volume mounted) — no-op
if [ ! -f "$CA_PEM" ]; then
  return 0 2>/dev/null || exit 0
fi

# Defensive: install should have produced this, but skip cleanly if it didn't
if [ ! -f "$CONFIG" ]; then
  echo "[tls-gorgone] $CONFIG missing, skipping (install hasn't generated it yet)"
  return 0 2>/dev/null || exit 0
fi

# Idempotent — bail if mysql_ssl already in the file (native support landed,
# or the script ran a second time on a persistent volume)
if grep -q 'mysql_ssl' "$CONFIG"; then
  echo "[tls-gorgone] DSN already has mysql_ssl attrs, no patch needed"
  return 0 2>/dev/null || exit 0
fi

# Append ;mysql_ssl=1;mysql_ssl_ca=<CA> immediately before the closing quote
# of any `dsn: "mysql:..."` line. Covers both db_configuration and db_realtime.
sed -i \
  "s|\(dsn: \"mysql:[^\"]*\)\"|\1;mysql_ssl=1;mysql_ssl_ca=$CA_PEM\"|g" \
  "$CONFIG" || exit 1

echo "[tls-gorgone] Patched gorgone DSN with mysql_ssl=1 in $CONFIG"
