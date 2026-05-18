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
[tls] Use a WEB_IMAGE built from a branch that includes PR #9237, or omit docker-compose.tls.yml from your compose invocation.
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
ssl-verify-server-cert
EOF
echo "[tls] mysql client config written: $MYSQL_CLIENT_CFG"

# 3. Apache TLS vhost — generated from Centreon's official HTTPS template so the
#    CI HTTPS path is byte-identical to production (security headers, cipher list,
#    HSTS, FCGI to php-fpm:9042, HTTP→HTTPS redirect). Only cert paths differ.
#    The 00- prefix sorts before the install-step's 10-centreon.conf, making
#    our <VirtualHost *:80> redirect block Apache's default-:80 vhost (Apache
#    picks the first vhost in file order when no ServerName matches).
TEMPLATE=/usr/share/centreon/examples/centreon-apache-https.conf
if [ ! -f "$TEMPLATE" ]; then
  echo "[tls] FATAL: official HTTPS template not found at $TEMPLATE" >&2
  echo "[tls] The centreon-web image must ship this file (packaging/src/centreon-apache-https.conf)." >&2
  exit 1
fi

case "$PLATFORM" in
  rhel)
    # The alma9/alma10 centreon-web images don't ship mod_ssl. Install on demand
    # so the SSLEngine directive in the template isn't an unknown command.
    if ! rpm -q mod_ssl >/dev/null 2>&1; then
      echo "[tls] installing mod_ssl (not present in image)"
      if command -v dnf >/dev/null 2>&1; then
        dnf install -y mod_ssl >/dev/null || exit 1
      else
        yum install -y mod_ssl >/dev/null || exit 1
      fi
    fi
    # mod_ssl ships a default /etc/httpd/conf.d/ssl.conf with a "snake-oil" vhost
    # that points at /etc/pki/tls/certs/localhost.crt — a cert auto-generated on
    # a normal RHEL host by httpd-init, but absent in container images. Reduce
    # the file to just the Listen directive so :443 is bound; our 00-centreon-tls.conf
    # defines the actual <VirtualHost *:443>.
    cat > /etc/httpd/conf.d/ssl.conf <<'STUB'
Listen 443 https
STUB
    VHOST_DEST=/etc/httpd/conf.d/00-centreon-tls.conf
    ;;
  debian)
    VHOST_DEST=/etc/apache2/sites-available/00-centreon-tls.conf
    ;;
esac

# Substitute only the cert paths; keep every other directive (cipher list, HSTS,
# X-Frame-Options, Set-Cookie HttpOnly+SameSite, ServerTokens Prod, FCGI block,
# Directory blocks, :80→:443 RewriteRule) byte-identical to production.
#
# Guard against template drift: the sed substitution is keyed on exact string
# matches against the production cert/key paths. If Centreon ever renames those
# paths in centreon-apache-https.conf, the sed silently does nothing and Apache
# fails with a misleading "file not found" pointing at the old (unrewritten)
# path. Fail loud here instead with an actionable message.
if ! grep -q '/etc/pki/tls/certs/ca.crt' "$TEMPLATE"; then
  echo "[tls] FATAL: expected SSLCertificateFile path '/etc/pki/tls/certs/ca.crt' not found in $TEMPLATE." >&2
  echo "[tls] The template may have been updated. Update the sed expression in this script accordingly." >&2
  exit 1
fi
if ! grep -q '/etc/pki/tls/private/ca.key' "$TEMPLATE"; then
  echo "[tls] FATAL: expected SSLCertificateKeyFile path '/etc/pki/tls/private/ca.key' not found in $TEMPLATE." >&2
  echo "[tls] The template may have been updated. Update the sed expression in this script accordingly." >&2
  exit 1
fi
sed -e "s|/etc/pki/tls/certs/ca.crt|$SRV_PEM|g" \
    -e "s|/etc/pki/tls/private/ca.key|$SRV_KEY|g" \
    "$TEMPLATE" > "$VHOST_DEST" || exit 1

if [ "$PLATFORM" = "debian" ]; then
  # Modules the official template needs: ssl, proxy, proxy_fcgi (php-fpm:9042
  # handoff), headers (HSTS / X-Frame-Options / cookie rewrite), deflate
  # (AddOutputFilterByType), rewrite (the :80 redirect block).
  a2enmod ssl proxy proxy_fcgi headers deflate rewrite >/dev/null || exit 1
  a2ensite 00-centreon-tls >/dev/null || exit 1
fi

echo "[tls] Apache vhost generated for $PLATFORM from $TEMPLATE (redirect :80→:443 enabled)"

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
