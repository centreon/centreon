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

# Container-topology adaptations of the Centreon database. Run on every
# install/upgrade run (fresh install AND an already-installed database, e.g.
# one migrated from a VM), so they must stay idempotent. They only touch
# Central's own rows: its nagios_server row and the broker configs attached
# to it, never remote pollers'.
#
# Broker configs are found by role, never by config_id (1/2/3 only hold on a
# fresh insertBaseConf, a VM database with any history can differ):
#   master = Central's daemon config with a unified_sql output
#   module = Central's non-daemon config (cbmod, loaded by centengine)
#   rrd    = Central's other daemon config with an rrd output
#            (central-rrd-master, merged into the master below)
CENTRAL_ID="${CENTRAL_POLLER_ID:-1}"

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

# Password of the centreon-gorgone service account (the user gorgone logs in
# to the Centreon API with). The install derives it from gorgone's own
# 31-centreon-api.yaml (getGorgoneApiCredentialMacros()), which fpm cannot
# read in a split topology: it stays EMPTY and gorgone ends up using admin.
# Set on every run when provided (the caller's secret is the reference,
# rotation works, a migrated database gets it too). bcrypt hashes contain
# `$`: passed through PDO parameters, never interpolated in shell.
# Port of docker-web-split 8b5ee8e39a.
if [ -n "${GORGONE_API_PASSWORD:-}" ]; then
  echo "Setting the centreon-gorgone service account password..."
  GORGONE_API_PASSWORD="$GORGONE_API_PASSWORD" php -r '
    // Database TLS as DatabaseTLSResolver applies it (this block runs
    // outside the web, without Dotenv).
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    if (getenv("DATABASE_SSL_ENABLED") === "1") {
      $options[PDO::MYSQL_ATTR_SSL_CA] = getenv("DATABASE_CA_PATH") ?: "";
      $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = getenv("DATABASE_VERIFY_SERVER_CERT") === "1";
    }
    $pdo = new PDO(
      "mysql:host=" . getenv("MYSQL_HOST") . ";dbname=" . getenv("MYSQL_DB_CONFIGURATION"),
      "root", getenv("MYSQL_ROOT_PASSWORD"),
      $options
    );
    $stmt = $pdo->prepare(
      "UPDATE contact_password SET password = :hash WHERE contact_id = " .
      "(SELECT contact_id FROM contact WHERE contact_alias = \"centreon-gorgone\")"
    );
    $stmt->execute([":hash" => password_hash(getenv("GORGONE_API_PASSWORD"), PASSWORD_BCRYPT)]);
    echo "  rows updated: " . $stmt->rowCount() . PHP_EOL;
    // The same service account authenticates the web against gorgone-central
    // HTTP API when GORGONE_API_USERNAME is given (Kubernetes: gorgone runs
    // with httpserver auth enabled). Read by ConfigurationLoader (basic auth).
    $user = getenv("GORGONE_API_USERNAME");
    if ($user !== false && $user !== "") {
      $pdo->exec("DELETE FROM options WHERE `key` IN (\"gorgone_api_username\", \"gorgone_api_password\")");
      $ins = $pdo->prepare("INSERT INTO options (`key`, `value`) VALUES (:k, :v)");
      $ins->execute([":k" => "gorgone_api_username", ":v" => $user]);
      $ins->execute([":k" => "gorgone_api_password", ":v" => getenv("GORGONE_API_PASSWORD")]);
      echo "  web -> gorgone API credentials set (" . $user . ")" . PHP_EOL;
    } else {
      // Say what was NOT done: with only the password, gorgone accepts the
      // account but the web never learns it, and every web -> gorgone call
      // answers 401 with nothing pointing back here.
      fwrite(STDERR, "  WARNING: GORGONE_API_USERNAME is not set: web -> gorgone API credentials (options gorgone_api_username/password) NOT set." . PHP_EOL);
      fwrite(STDERR, "  WARNING: if gorgone-central requires API authentication, every web -> gorgone call will get HTTP 401." . PHP_EOL);
    }
  '
fi

# Central's nagios_server row stays localhost=1 (it IS the Central: the web
# and the modules find it that way) and is marked poller_type='docker': its
# engine and gorgone run in other containers, so gorgone-central registers it
# as a pullwss node (type 4, port 8087) instead of treating it as itself, and
# the web delivers its configuration through gorgone. localhost is forced
# back to 1 for databases set up by earlier images (localhost=0). Must run
# after insertBaseConf.php on a fresh install: it does, this block runs after
# the install branch.
echo "Marking Central as a containerized poller (poller_type=docker, pullwss)..."
mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "
  UPDATE nagios_server
     SET localhost = '1', poller_type = 'docker',
         gorgone_communication_type = '4', gorgone_port = '8087'
   WHERE id = ${CENTRAL_ID}"

# options.gorgone_api_address ships '127.0.0.1' (co-located gorgone on a VM):
# triggerGorgoneNodesSyncIfPullwss() (configServers/DB-Func.php) uses it to
# tell gorgone-central to resync its nodes whenever a poller is saved.
echo "Pointing gorgone_api_address at gorgone-central..."
mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e \
  "UPDATE options SET value = 'gorgone-central' WHERE \`key\` = 'gorgone_api_address'"

# Broker configs of Central:
# - master's unified_sql db_host: a migrated database keeps the old DB host.
# - cbmod's output host ships localhost (co-located broker on a VM).
# - RRD: merge central-rrd-master into the master (a type=rrd output writing
#   /var/lib/centreon/{metrics,status} directly) instead of the default
#   BBDO forward to localhost:5670 + a separate rrd process. Insert only if
#   the master has no rrd output yet; delete the 5670 forward and the
#   rrd-master config only if present. Field list taken from a working,
#   UI-created RRD output (blockId is UI bookkeeping, excluded from the
#   generated JSON).
echo "Adapting Central's broker configuration (db_host, cbmod output, RRD merge)..."
mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "
  SET @master = (
    SELECT b.config_id FROM cfg_centreonbroker b
      JOIN cfg_centreonbroker_info i ON i.config_id = b.config_id
     WHERE b.ns_nagios_server = ${CENTRAL_ID} AND b.daemon = '1'
       AND i.config_group = 'output' AND i.config_key = 'type' AND i.config_value = 'unified_sql'
     LIMIT 1);
  SET @module = (
    SELECT config_id FROM cfg_centreonbroker
     WHERE ns_nagios_server = ${CENTRAL_ID} AND daemon = '0'
     LIMIT 1);
  SET @rrdcfg = (
    SELECT b.config_id FROM cfg_centreonbroker b
      JOIN cfg_centreonbroker_info i ON i.config_id = b.config_id
     WHERE b.ns_nagios_server = ${CENTRAL_ID} AND b.daemon = '1' AND b.config_id <> @master
       AND i.config_group = 'output' AND i.config_key = 'type' AND i.config_value = 'rrd'
     LIMIT 1);

  UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}'
   WHERE config_id = @master AND config_key = 'db_host';
  UPDATE cfg_centreonbroker_info SET config_value = 'centreon-broker'
   WHERE config_id = @module AND config_group = 'output' AND config_key = 'host';
  -- cbmod's cache_directory (where it saves its in-memory queue on a clean
  -- stop, and overflows it to disk) only when the caller provides a
  -- persistent path: the default /var/lib/centreon-engine holds image files,
  -- no volume can be mounted there.
  UPDATE cfg_centreonbroker SET cache_directory = '${CBMOD_CACHE_DIRECTORY:-}'
   WHERE config_id = @module AND '${CBMOD_CACHE_DIRECTORY:-}' <> '';
  -- Same for the master broker: its queue while the database is down
  -- (default /var/lib/centreon-broker holds the image's entrypoint).
  UPDATE cfg_centreonbroker SET cache_directory = '${BROKER_CACHE_DIRECTORY:-}'
   WHERE config_id = @master AND '${BROKER_CACHE_DIRECTORY:-}' <> '';

  SET @fwd_gid = (
    SELECT config_group_id FROM cfg_centreonbroker_info
     WHERE config_id = @master AND config_group = 'output'
       AND config_key = 'port' AND config_value = '5670'
     LIMIT 1);
  DELETE FROM cfg_centreonbroker_info
   WHERE config_id = @master AND config_group = 'output' AND config_group_id = @fwd_gid;
  DELETE FROM cfg_centreonbroker_info WHERE config_id = @rrdcfg;
  DELETE FROM cfg_centreonbroker WHERE config_id = @rrdcfg;

  SET @has_rrd = (
    SELECT COUNT(*) FROM cfg_centreonbroker_info
     WHERE config_id = @master AND config_group = 'output'
       AND config_key = 'type' AND config_value = 'rrd');
  SET @new_gid = (
    SELECT COALESCE(MAX(config_group_id), 0) + 1 FROM cfg_centreonbroker_info
     WHERE config_id = @master AND config_group = 'output');
  INSERT INTO cfg_centreonbroker_info (config_id, config_key, config_value, config_group, config_group_id)
  SELECT @master, k, v, 'output', @new_gid FROM (
              SELECT 'name' AS k, 'central-broker-master-rrd' AS v
    UNION ALL SELECT 'type', 'rrd'
    UNION ALL SELECT 'metrics_path', '/var/lib/centreon/metrics/'
    UNION ALL SELECT 'status_path', '/var/lib/centreon/status/'
    UNION ALL SELECT 'write_metrics', 'yes'
    UNION ALL SELECT 'write_status', 'yes'
    UNION ALL SELECT 'store_in_data_bin', 'yes'
    UNION ALL SELECT 'insert_in_index_data', '1'
    UNION ALL SELECT 'rrd_cached', ''
    UNION ALL SELECT 'rrd_cached_option', 'disable'
    UNION ALL SELECT 'blockId', '1_13'
  ) AS rrd_fields
  WHERE @master IS NOT NULL AND @has_rrd = 0;
"

# rrdcached (MON-213837): write-cache TCP sidecar for Central's merged RRD
# output, its own independent service/hostname (not sharing centreon-broker's
# network namespace — DESIGN CHANGE, see trixie/README.md: G9/MON-213839 adds
# a 'host' key to the rrd output, find_param(cfg, "host", false, "localhost")
# in broker.class.php, letting cbd dial out to a separate container instead
# of requiring netns sharing). Disabled by default stack-wide (RRDCACHED_PORT
# unset) until a BROKER_TAG carrying that fix is in use (C12). Idempotent
# BOTH ways, on every start (UPDATE/DELETE+INSERT, not INSERT-if-missing): an
# unset/empty RRDCACHED_PORT forces 'disable' and drops the 'host' row
# explicitly rather than leaving whatever was there — otherwise toggling
# rrdcached off on an already-running release would leave cbd configured for
# tcp with nothing listening on the other end.
if [ -n "${RRDCACHED_PORT:-}" ]; then
  echo "Enabling rrdcached for Central's RRD output (${RRDCACHED_HOST:-rrdcached}:${RRDCACHED_PORT})..."
  RRD_CACHED_OPTION_VALUE=tcp
  RRD_CACHED_VALUE="${RRDCACHED_PORT}"
  RRD_HOST_VALUE="${RRDCACHED_HOST:-rrdcached}"
else
  echo "Disabling rrdcached for Central's RRD output (RRDCACHED_PORT unset)..."
  RRD_CACHED_OPTION_VALUE=disable
  RRD_CACHED_VALUE=""
  RRD_HOST_VALUE=""
fi
mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "
  SET @master = (
    SELECT b.config_id FROM cfg_centreonbroker b
      JOIN cfg_centreonbroker_info i ON i.config_id = b.config_id
     WHERE b.ns_nagios_server = ${CENTRAL_ID} AND b.daemon = '1'
       AND i.config_group = 'output' AND i.config_key = 'type' AND i.config_value = 'unified_sql'
     LIMIT 1);
  SET @rrd_gid = (
    SELECT config_group_id FROM cfg_centreonbroker_info
     WHERE config_id = @master AND config_group = 'output'
       AND config_key = 'type' AND config_value = 'rrd'
     LIMIT 1);
  UPDATE cfg_centreonbroker_info SET config_value = '${RRD_CACHED_OPTION_VALUE}'
   WHERE config_id = @master AND config_group_id = @rrd_gid AND config_key = 'rrd_cached_option';
  UPDATE cfg_centreonbroker_info SET config_value = '${RRD_CACHED_VALUE}'
   WHERE config_id = @master AND config_group_id = @rrd_gid AND config_key = 'rrd_cached';
  DELETE FROM cfg_centreonbroker_info
   WHERE config_id = @master AND config_group_id = @rrd_gid AND config_key = 'host';
  INSERT INTO cfg_centreonbroker_info (config_id, config_key, config_value, config_group, config_group_id)
  SELECT @master, 'host', '${RRD_HOST_VALUE}', 'output', @rrd_gid
   WHERE @master IS NOT NULL AND @rrd_gid IS NOT NULL AND '${RRD_HOST_VALUE}' != '';
"

# Central's engine logs straight to the container's stdout instead of a file
# on its volume that the engine image tails (and that grows for the pod's
# whole life). The image skips its tail for /dev/std*. An empty log_file
# would not do: the generator drops empty fields and engine falls back to its
# compiled default file. Only the package default is replaced, a path chosen
# in the UI is kept. Engine never rotates it: the generator does not write
# max_log_file_size, which stays 0 (a rotation would rename /dev/stdout).
echo "Sending Central's engine log to stdout..."
mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "
  UPDATE cfg_nagios SET log_file = '/dev/stdout'
   WHERE nagios_server_id = ${CENTRAL_ID}
     AND log_file = '/var/log/centreon-engine/centengine.log'"
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
