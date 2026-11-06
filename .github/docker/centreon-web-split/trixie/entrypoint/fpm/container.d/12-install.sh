#!/bin/sh
# This file is sourced by container.sh (". $file"), not executed as its own
# process — `exit` here would kill the whole container, not just skip this
# step. Use `return` for the early-exit guards below.
set -eu

# Opt-in only: this is a dev/CI convenience to get a real database behind the
# split images for manual testing, NOT the real T9 (productised automated
# install). Trimmed down from the monolithic image's own
# .github/docker/centreon-web/trixie/entrypoint/container.d/15-installation.sh
# (same JSON answer-file fixtures, copied into this image at build time) —
# the gorgone/sysconfig/engine-context steps are dropped here on purpose:
# this image has no gorgone/engine package at all, they belong to Pilier 2.
if [ "${RUN_INSTALL:-false}" != "true" ]; then
  return 0
fi

if [ -f /etc/centreon/centreon.conf.php ]; then
  echo "Centreon already installed (centreon.conf.php exists) — skipping install."
  return 0
fi

: "${MYSQL_HOST:?RUN_INSTALL=true requires MYSQL_HOST}"
: "${MYSQL_ROOT_PASSWORD:?RUN_INSTALL=true requires MYSQL_ROOT_PASSWORD}"

export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"
# INSTALL_DB_STEPS=false: per-container steps only (config files, APP_SECRET,
# cache, engine-context.json, removing www/install), no schema install,
# upgrade, module registration or SQL patch. For N fpm replicas where a
# single install Job owns the database (helm chart); default keeps the
# one-container behavior compose relies on.
INSTALL_DB_STEPS="${INSTALL_DB_STEPS:-true}"
# Exported: the inline PHP blocks below read them with getenv(), and compose
# does not set them (the Helm chart does).
export MYSQL_DB_CONFIGURATION="${MYSQL_DB_CONFIGURATION:-centreon}"
export MYSQL_DB_STORAGE="${MYSQL_DB_STORAGE:-centreon_storage}"
DATABASE_PARTITIONING="${DATABASE_PARTITIONING:-1}"

# mysql client: TLS when DATABASE_SSL_ENABLED=1 (a server requiring it, e.g.
# AWS RDS MariaDB 11.8), checked against DATABASE_CA_PATH when
# DATABASE_VERIFY_SERVER_CERT=1. Otherwise as the monolithic image's own
# container.d/10-mysql.sh: the CI/dev MariaDB runs with --skip-ssl, but the
# mysql client defaults to requiring TLS and fails with "SSL is required, but
# the server does not support it".
mkdir -p /etc/mysql/conf.d
rm -f /etc/mysql/conf.d/client-no-ssl.cnf /etc/mysql/conf.d/client-tls.cnf
if [ "${DATABASE_SSL_ENABLED:-0}" = "1" ]; then
  {
    printf '[client]\nssl\n'
    [ -n "${DATABASE_CA_PATH:-}" ] && printf 'ssl-ca=%s\n' "$DATABASE_CA_PATH"
    if [ "${DATABASE_VERIFY_SERVER_CERT:-0}" = "1" ]; then
      printf 'ssl-verify-server-cert\n'
    else
      printf 'disable-ssl-verify-server-cert\n'
    fi
  } > /etc/mysql/conf.d/client-tls.cnf
else
  printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/client-no-ssl.cnf
fi

# Same semantics as the former `mysqladmin ping` (not in mariadb-client-core):
# wait until the server answers; an "Access denied" also means it is up, and
# the install then fails on the real query with a clear error.
echo "Waiting for DB server to be ready..."
until out=$(mysql -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" --connect-timeout=5 -e 'SELECT 1' 2>&1) \
  || printf '%s' "$out" | grep -q 'Access denied'; do
  sleep 1
done

sed -i "s/localhost/${MYSQL_HOST}/g" /usr/share/centreon/www/install/tmp/database.json
sed -i "s/\"db_configuration\": \"[^\"]*\"/\"db_configuration\": \"${MYSQL_DB_CONFIGURATION}\"/" /usr/share/centreon/www/install/tmp/database.json
sed -i "s/\"db_storage\": \"[^\"]*\"/\"db_storage\": \"${MYSQL_DB_STORAGE}\"/" /usr/share/centreon/www/install/tmp/database.json
# The install's PHP steps (installConfigurationDb, createDbUser, insertBaseConf
# ...) connect with database.json's credentials, not the environment: the
# root password (mandatory, above) and, when given, the "centreon" DB user's
# password (web config and broker outputs) replace the fixture's. Otherwise a
# non-default password makes createDbUser fail silently (its output is
# discarded) and the install dies later on "Access denied". JSON-encoded, any
# character is safe.
php -r '
  $f = $argv[1];
  $j = json_decode(file_get_contents($f), true);
  $j["root_password"] = getenv("MYSQL_ROOT_PASSWORD");
  if ((string) getenv("MYSQL_PASSWORD") !== "") {
    $j["db_password"] = $j["db_password_confirm"] = getenv("MYSQL_PASSWORD");
  }
  file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
' /usr/share/centreon/www/install/tmp/database.json

cd /usr/share/centreon/www/install/steps/process

echo "Creating Centreon configuration files..."
su www-data -s /bin/bash -c "php configFileSetup.php" > /dev/null

# Same logic as centreon-web-postinstall.sh's updateConfigurationFiles(): the
# .env* glob covers both .env AND .env.local.php in one pass — load-bearing,
# not redundant, since Dotenv::bootEnv() prefers .env.local.php when present
# and never even parses .env in that case (both ship with the placeholder).
# Must run before the engine-context.json write below: FsEngineRepository
# only ever writes that file once, so a stale secret baked in there has no
# code path to fix itself afterwards.
#
# Prefer caller-provided APP_SECRET/SALT (docker-compose.yml passes fixed dev
# values, shared with gorgone-central-poller/centengine — engine needs the
# *same* pair the web side used, not just any fixed pair) over generating
# fresh random ones: neither .env* nor engine-context.json survive a
# container recreate (no volume), so random values would silently change on
# every recreate — invalidating sessions and, worse, orphaning anything
# already encrypted in the (persistent) DB with the previous secret. Random
# generation stays as a fallback for standalone `docker run` usage.
if [ -n "${APP_SECRET:-}" ]; then
  APP_SECRET_VALUE="${APP_SECRET}"
else
  APP_SECRET_VALUE=$(dd if=/dev/urandom bs=32 count=1 status=none | php -r "echo bin2hex(fread(STDIN, 32));")
fi
if [ -n "${SALT:-}" ]; then
  SALT_VALUE="${SALT}"
else
  SALT_VALUE=$(dd if=/dev/urandom bs=32 count=1 status=none | php -r "echo bin2hex(fread(STDIN, 32));")
fi

if grep -lqE "%APP_SECRET%" /usr/share/centreon/.env* 2>/dev/null; then
  echo "Setting APP_SECRET..."
  sed -i "s/%APP_SECRET%/${APP_SECRET_VALUE}/g" /usr/share/centreon/.env*
  chown www-data:www-data /usr/share/centreon/.env*
fi

# Database TLS for the web's PHP (DatabaseTLSResolver: PDO, DBAL, the legacy
# CentreonDB; the broker configuration generator): it reads $_ENV only, which
# Dotenv fills from .env.local.php. Container env does not do it: php-fpm
# clears the workers' environment. Before any PHP step touching the database.
if [ "${DATABASE_SSL_ENABLED:-0}" = "1" ]; then
  echo "Enabling database TLS for the web..."
  php -r '
    $file = "/usr/share/centreon/.env.local.php";
    $env = is_file($file) ? require $file : [];
    foreach (["DATABASE_SSL_ENABLED", "DATABASE_VERIFY_SERVER_CERT", "DATABASE_CA_PATH"] as $key) {
      $value = getenv($key);
      if ($value !== false && $value !== "") {
        $env[$key] = $value;
      }
    }
    file_put_contents($file, "<?php\n\nreturn " . var_export($env, true) . ";\n");
  '
  chown www-data:www-data /usr/share/centreon/.env.local.php
fi

# Same idempotency check as the monolithic image's 15-installation.sh: the DB
# can already hold a schema (a persistent volume/container reused) even
# though this container's own /etc/centreon/centreon.conf.php is fresh —
# re-running the schema install scripts against existing tables would fail.
if [ "$INSTALL_DB_STEPS" != "true" ]; then
  echo "INSTALL_DB_STEPS=false — skipping database install/upgrade steps."
  ALREADY_INSTALLED="skip"
else
  ALREADY_INSTALLED=$(mysql -N -s -h"${MYSQL_HOST}" -uroot -e \
    "SELECT count(*) from information_schema.tables WHERE table_schema='${MYSQL_DB_CONFIGURATION}' and table_name='nagios_server'")
fi

if [ "$ALREADY_INSTALLED" = "skip" ]; then
  :
elif [ "$ALREADY_INSTALLED" = "1" ]; then
  INSTALL_KIND=existing
  echo "Centreon database already installed — creating DB user only."
  su www-data -s /bin/bash -c "php createDbUser.php" > /dev/null
else
  INSTALL_KIND=fresh
  echo "Installing Centreon configuration database..."
  su www-data -s /bin/bash -c "php installConfigurationDb.php" > /dev/null

  echo "Installing Centreon storage database..."
  su www-data -s /bin/bash -c "php installStorageDb.php" > /dev/null

  echo "Creating Centreon database user..."
  su www-data -s /bin/bash -c "php createDbUser.php" > /dev/null

  # Admin password instead of the admin.json fixture's (Centreon!2021):
  # insertBaseConf.php creates the admin from that file as-is, so it is
  # hashed here first. Fresh install only (an existing admin keeps its own).
  # Port of docker-web-split 8b5ee8e39a.
  if [ -n "${ADMIN_PASSWORD:-}" ]; then
    echo "Setting admin password from ADMIN_PASSWORD..."
    php -r '
      $file = "/usr/share/centreon/www/install/tmp/admin.json";
      $data = json_decode(file_get_contents($file), true);
      $hash = password_hash($argv[1], PASSWORD_BCRYPT);
      $data["admin_password"] = $hash;
      $data["confirm_password"] = $hash;
      file_put_contents($file, json_encode($data));
    ' "$ADMIN_PASSWORD"
  fi

  echo "Inserting base configuration..."
  su www-data -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php" > /dev/null

  if [ "${ADMIN_PASSWORD_EXPIRED:-false}" = "true" ]; then
    echo "Marking admin's password as expired (forces a change on next login)..."
    mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "
      UPDATE contact_password SET creation_date = UNIX_TIMESTAMP(NOW()) - 15552000 - 86400
      WHERE contact_id = (SELECT contact_id FROM contact WHERE contact_alias = 'admin')"
  fi

  if [ "$DATABASE_PARTITIONING" = "1" ]; then
    echo "Creating database partition tables..."
    su www-data -s /bin/bash -c "php partitionTables.php" > /dev/null
  fi

  if [ "${CENTREON_DATASET:-0}" = "1" ]; then
    echo "CENTREON_DATASET is set, inserting sample dataset..."
    for file in /usr/local/src/sql/data/*; do
      [ -e "$file" ] || continue
      mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" < "$file"
    done
  fi
fi

# Non-interactive equivalent of the browser upgrade wizard (new
# runPendingUpdates.php, centreon/www/install/steps/process/) — runs every
# Update-<version>.php/.post.php + SQL migration the DB's own recorded
# version hasn't seen yet. Naturally a no-op right after a fresh install
# (informations.version is already seeded to the codebase's own current
# version by insertBaseConf.sql) and on every plain recreate against an
# already-up-to-date DB — only a real version bump (a newer image against
# an older persisted DB) finds anything to do, no separate env var needed.
if [ "$INSTALL_DB_STEPS" = "true" ]; then
echo "Applying any pending schema updates..."
su www-data -s /bin/bash -c "php runPendingUpdates.php"

# Same no-new-env-var reasoning as runPendingUpdates.php above:
# installBakedModules.php checks each module's own installed state first
# (CentreonModuleService::getDetail()->isInstalled()), so this is a no-op
# once all 4 T0 modules (baked into the image at build time, see the
# Dockerfile's modules_fetcher/autodisco_source stages) are registered.
echo "Installing baked-in modules (Autodiscovery, License Manager, IT Edition, PP Manager)..."
su www-data -s /bin/bash -c "php installBakedModules.php"

# createDbUser.php grants the web user from the connecting host's IP only
# (the install pod's), so every other fpm pod is refused ("Access denied for
# user 'centreon'@'%'", seen on a migrated 25.10 database). CREATE first: on
# an external database the user may not exist yet.
DB_JSON=/usr/share/centreon/www/install/tmp/database.json
DB_USER=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["db_user"];' "$DB_JSON")
DB_PASSWORD=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["db_password"];' "$DB_JSON")
echo "Granting the Centreon DB user from any host..."
mysql -h"${MYSQL_HOST}" -uroot -e "
  CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
  GRANT ALL ON *.* TO '${DB_USER}'@'%' WITH GRANT OPTION"
fi

echo "Generating Centreon cache..."
su www-data -s /bin/bash -c "php generationCache.php" > /dev/null

# Write /etc/centreon-engine/engine-context.json directly instead of going
# through createEngineContextConfiguration.php: that script (via
# FileSystemEngineContextWriter) only pulls app_secret from %env(APP_SECRET)%
# and generates its own random salt (bin2hex(random_bytes(32)), hardcoded, no
# env var to pin it) — engine and web have to agree on the *same* app_secret
# AND salt to decrypt the same data, so a self-picked salt here defeats the
# whole point once a real centengine container (sharing this same pair via
# APP_SECRET/SALT env vars, see docker-compose.yml) is in the picture.
echo "Creating engine context configuration..."
printf '{"app_secret":"%s","salt":"%s"}\n' "${APP_SECRET_VALUE}" "${SALT_VALUE}" \
  > /etc/centreon-engine/engine-context.json
chown www-data:www-data /etc/centreon-engine/engine-context.json

if [ "$INSTALL_DB_STEPS" = "true" ]; then
  echo "Disabling statistics collection..."
  mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e \
    "DELETE FROM options WHERE \`key\` = 'send_statistics'; INSERT INTO options (\`key\`, \`value\`) VALUES ('send_statistics', '0')"
fi

cd - > /dev/null

# Same as the wizard's own final step (process_step9.php): back up the
# install directory (audit trail a human running the wizard would get,
# _CENTREON_VARLIB_/installs/, "centreon_varlib" in configuration.json,
# already /var/lib/centreon here) before removing it, redacting the two
# fixture files with credentials in the backup copy rather than just
# deleting outright. Version comes from tmp/version.json when present —
# insertBaseConf.php:220 (Step6::setVersion(), Step6.php:49) writes it as
# a bare-quoted JSON string right after insertBaseConf.sql's own
# INSERT INTO informations (version), confirmed by inspecting a real
# install's tmp/ directly, not assumed — same file AbstractStep::
# getVersion() reads. "1.0.0" is that same method's own fallback when the
# file is absent (e.g. a plain recreate against an already-installed DB,
# where insertBaseConf.php never runs this time either), not a value
# invented for Docker.
INSTALL_VERSION="1.0.0"
if [ -f /usr/share/centreon/www/install/tmp/version.json ]; then
  INSTALL_VERSION=$(sed 's/"//g' /usr/share/centreon/www/install/tmp/version.json)
fi
INSTALL_BACKUP_DIR="/var/lib/centreon/installs/install-${INSTALL_VERSION}-$(date +%Y%m%d_%H%M%S)"
# Config-only replicas still delete www/install (it must never be reachable
# through httpd), but leave the audit-trail backup to the install run.
if [ "$INSTALL_DB_STEPS" != "true" ]; then
  rm -rf /usr/share/centreon/www/install
else
su www-data -s /bin/bash -c "
  mkdir -p '${INSTALL_BACKUP_DIR}' &&
  cp -a /usr/share/centreon/www/install/. '${INSTALL_BACKUP_DIR}/' &&
  rm -f '${INSTALL_BACKUP_DIR}/tmp/admin.json' '${INSTALL_BACKUP_DIR}/tmp/database.json' &&
  rm -rf /usr/share/centreon/www/install
"
fi

case "${INSTALL_KIND:-skip}" in
  fresh)
    if [ -n "${ADMIN_PASSWORD:-}" ]; then
      echo "Install done — login with admin and the password given in ADMIN_PASSWORD."
    else
      echo "Install done — login with admin / Centreon!2021 (fixture default, see .github/docker/centreon-web/trixie/configuration/admin.json)."
    fi ;;
  existing) echo "Install done — existing database, admin password unchanged." ;;
  *) echo "Install done — database steps skipped." ;;
esac
