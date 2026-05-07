#!/bin/bash
#
# 04-tls.sh — HTTPS-mode entrypoint hook.
#
# Sourced by /usr/share/centreon/container.sh in numeric order, BEFORE
# 05-php_background.sh and 06-apache_background.sh start the daemons. When the
# `certs` named volume is mounted at /etc/pki/centreon-tls (HTTPS mode), this
# script:
#   1. Fails fast if centreon/centreon PR #9237's DatabaseTLSResolver is absent
#      from the image (that PR provides PHP→DB TLS support).
#   2. Installs the Root CA into the OS trust store.
#   3. Drops a [client] my.cnf so the mysql/mariadb CLI uses TLS by default.
#   4. Drops an Apache TLS vhost that terminates TLS on :443 and reverse-proxies
#      to the existing :80 vhost (avoids re-creating Centreon's full Apache
#      config — TLS termination is the only concern here).
#   5. Writes /usr/share/centreon/.env with DATABASE_SSL_* keys consumed by
#      DatabaseTLSResolver via Symfony Dotenv.
#
# In HTTP mode (no `certs` mount), this script returns immediately — zero impact.

CERT_DIR=/etc/pki/centreon-tls
CA_PEM="$CERT_DIR/rootCA.pem"
SRV_PEM="$CERT_DIR/server.pem"
SRV_KEY="$CERT_DIR/server-key.pem"
DOTENV=/usr/share/centreon/.env
RESOLVER_PHP=/usr/share/centreon/src/Core/Infrastructure/Common/DatabaseTLSResolver.php

# HTTP mode: no certs mounted, no-op
if [ ! -f "$CA_PEM" ]; then
  return 0 2>/dev/null || exit 0
fi

echo "[tls] HTTPS mode detected, certificates present at $CERT_DIR"

# Failsafe: HTTPS mode requires PR #9237 (centreon/centreon).
# Without DatabaseTLSResolver, PHP→DB connections silently bypass TLS and break
# against a require_secure_transport=ON server. Fail loud, fail specific.
if [ ! -f "$RESOLVER_PHP" ]; then
  cat >&2 <<'ERR'
[tls] FATAL: HTTPS mode requires centreon/centreon PR #9237.
[tls] Expected file not found: /usr/share/centreon/src/Core/Infrastructure/Common/DatabaseTLSResolver.php
[tls] Use a WEB_IMAGE built from a branch that includes PR #9237, or unset CENTREON_PROTOCOL.
ERR
  exit 1
fi

# Detect platform
if [ -f /etc/redhat-release ]; then
  PLATFORM=rhel
elif [ -f /etc/debian_version ]; then
  PLATFORM=debian
else
  echo "[tls] FATAL: unsupported platform (neither /etc/redhat-release nor /etc/debian_version found)" >&2
  exit 1
fi

# 1. Install Root CA into the system trust store
case "$PLATFORM" in
  rhel)
    cp "$CA_PEM" /etc/pki/ca-trust/source/anchors/centreon-dev.crt || exit 1
    update-ca-trust extract || exit 1
    ;;
  debian)
    cp "$CA_PEM" /usr/local/share/ca-certificates/centreon-dev.crt || exit 1
    update-ca-certificates >/dev/null || exit 1
    ;;
esac
echo "[tls] Root CA installed into $PLATFORM trust store"

# 2. mysql/mariadb [client] config — points to our CA so CLI calls (used by
#    10-mysql.sh, install steps, etc.) negotiate TLS with verification by default.
case "$PLATFORM" in
  rhel)   MYSQL_CLIENT_CFG=/etc/my.cnf.d/centreon-tls-client.cnf ;;
  debian) MYSQL_CLIENT_CFG=/etc/mysql/conf.d/centreon-tls-client.cnf ;;
esac
mkdir -p "$(dirname "$MYSQL_CLIENT_CFG")"
cat > "$MYSQL_CLIENT_CFG" <<EOF
[client]
ssl-ca=$CA_PEM
EOF
echo "[tls] mysql client config written: $MYSQL_CLIENT_CFG"

# 3. Apache TLS vhost — terminates TLS on :443, reverse-proxies to :80 (loop-back).
#    mod_ssl provides Listen 443 via its own conf, so we don't redeclare it here.
case "$PLATFORM" in
  rhel)
    cat > /etc/httpd/conf.d/centreon-tls.conf <<EOF
<VirtualHost *:443>
  ServerName centreon-tls
  SSLEngine on
  SSLCertificateFile      $SRV_PEM
  SSLCertificateKeyFile   $SRV_KEY
  ProxyPreserveHost On
  ProxyPass        / http://127.0.0.1:80/
  ProxyPassReverse / http://127.0.0.1:80/
</VirtualHost>
EOF
    ;;
  debian)
    cat > /etc/apache2/sites-available/centreon-tls.conf <<EOF
<VirtualHost *:443>
  ServerName centreon-tls
  SSLEngine on
  SSLCertificateFile      $SRV_PEM
  SSLCertificateKeyFile   $SRV_KEY
  ProxyPreserveHost On
  ProxyPass        / http://127.0.0.1:80/
  ProxyPassReverse / http://127.0.0.1:80/
</VirtualHost>
EOF
    a2enmod ssl proxy proxy_http >/dev/null || exit 1
    a2ensite centreon-tls >/dev/null || exit 1
    ;;
esac
echo "[tls] Apache TLS vhost configured for $PLATFORM (proxy 443 -> 127.0.0.1:80)"

# 4. Symfony .env keys consumed by DatabaseTLSResolver (PR #9237). Idempotent:
#    strip any pre-existing DATABASE_SSL_* lines before appending fresh values.
if [ -f "$DOTENV" ]; then
  sed -i '/^DATABASE_SSL_ENABLED=/d;/^DATABASE_VERIFY_SERVER_CERT=/d;/^DATABASE_CA_PATH=/d' "$DOTENV"
fi
cat >> "$DOTENV" <<EOF
DATABASE_SSL_ENABLED=1
DATABASE_VERIFY_SERVER_CERT=1
DATABASE_CA_PATH=$CA_PEM
EOF
echo "[tls] $DOTENV updated with DATABASE_SSL_* keys"

echo "[tls] HTTPS-mode setup complete"
