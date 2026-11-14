#!/bin/sh
set -eu

# This container has no shared volume with fpm for /etc/centreon on purpose:
# fpm's own 12-install.sh short-circuits ("Centreon already installed —
# skipping install") the instant /etc/centreon/centreon.conf.php exists, and
# that guard must stay false on every fpm recreate for the idempotent
# topology-adaptation block (GRANT/broker config/RRD merge) to keep running —
# sharing this path would silently turn that guard on the moment this
# container's own first write landed on a shared volume. Instead this
# container generates its own local copy of the two files the legacy PHP/Perl
# cron scripts actually read (centreon.conf.php, conf.pm), straight from the
# same install-fixture JSON (www/install/tmp/database.json) fpm's own
# 12-install.sh reads — same values, independently derived, zero coupling to
# fpm's own container state.
MYSQL_HOST="${MYSQL_HOST:?MYSQL_HOST is required}"
MYSQL_DB_CONFIGURATION="${MYSQL_DB_CONFIGURATION:-centreon}"
MYSQL_DB_STORAGE="${MYSQL_DB_STORAGE:-centreon_storage}"

# PHP ignores TZ: without date.timezone the jobs run in UTC. centreon-partitioning
# then reads the partition boundaries fpm created in PHP_TIMEZONE one day off
# and tries to re-create the last one ("Duplicate partition name"). Same
# PHP_TIMEZONE as fpm (01-config.sh), written into this PHP's scan dir.
printf 'date.timezone = %s\n' "${PHP_TIMEZONE:-UTC}" \
  > "$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/99-timezone.ini"

# Same as fpm's own 12-install.sh: the CI/dev MariaDB runs with --skip-ssl,
# but PDO's mysql driver (and the plain `mysql` CLI) defaults to requiring
# TLS — without this, every job fails with a misleading "Access denied"
# rather than a clear SSL error (confirmed directly: the raw `mysql` CLI
# gives the real "SSL is required" message, PDO swallows it into a generic
# access-denied exception).
mkdir -p /etc/mysql/conf.d
printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/client-no-ssl.cnf

DB_JSON=/usr/share/centreon/www/install/tmp/database.json
sed -i "s/\"address\": \"[^\"]*\"/\"address\": \"${MYSQL_HOST}\"/" "$DB_JSON"
sed -i "s/\"db_configuration\": \"[^\"]*\"/\"db_configuration\": \"${MYSQL_DB_CONFIGURATION}\"/" "$DB_JSON"
sed -i "s/\"db_storage\": \"[^\"]*\"/\"db_storage\": \"${MYSQL_DB_STORAGE}\"/" "$DB_JSON"

DB_ADDRESS=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["address"];' "$DB_JSON")
DB_PORT=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["port"];' "$DB_JSON")
DB_USER=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["db_user"];' "$DB_JSON")
# MYSQL_PASSWORD when set (same variable fpm's install uses for a custom
# password), the fixture's otherwise.
DB_PASSWORD="${MYSQL_PASSWORD:-$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["db_password"];' "$DB_JSON")}"
DB_CONFDB=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["db_configuration"];' "$DB_JSON")
DB_STORAGEDB=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["db_storage"];' "$DB_JSON")

# Same two templates the real install's configFileSetup.php resolves
# (www/install/var/configFileTemplate / configFilePmTemplate), substituted
# directly instead of invoking the full legacy install bootstrap (Step6 +
# Symfony kernel) just for two template fills — those macros are plain
# placeholders read from the same JSON fixture, no DB query or session state
# involved. Not escaping quotes/backslashes in DB_USER/DB_PASSWORD for this
# substitution (unlike configFileSetup.php's own escaping): fine for this
# stack's static fixture values, would need hardening for arbitrary
# user-supplied credentials.
mkdir -p /etc/centreon
sed \
  -e "s#--ADDRESS--#${DB_ADDRESS}#g" \
  -e "s#--DBUSER--#${DB_USER}#g" \
  -e "s#--DBPASS--#${DB_PASSWORD}#g" \
  -e "s#--CONFDB--#${DB_CONFDB}#g" \
  -e "s#--STORAGEDB--#${DB_STORAGEDB}#g" \
  -e "s#--DBPORT--#${DB_PORT}#g" \
  -e "s#--CENTREONDIR--#/usr/share/centreon#g" \
  /usr/share/centreon/www/install/var/configFileTemplate > /etc/centreon/centreon.conf.php

sed \
  -e "s#--ADDRESS--#${DB_ADDRESS}#g" \
  -e "s#--DBUSER--#${DB_USER}#g" \
  -e "s#--DBPASS--#${DB_PASSWORD}#g" \
  -e "s#--CONFDB--#${DB_CONFDB}#g" \
  -e "s#--STORAGEDB--#${DB_STORAGEDB}#g" \
  -e "s#--DBPORT--#${DB_PORT}#g" \
  -e "s#--CENTREONDIR--#/usr/share/centreon#g" \
  -e "s#--CENTREON_VARLIB--#/var/lib/centreon#g" \
  -e "s#--CENTREON_CACHEDIR--#/var/cache/centreon#g" \
  -e "s#--INSTANCEMODE--#central#g" \
  /usr/share/centreon/www/install/var/configFilePmTemplate > /etc/centreon/conf.pm

chown centreon:centreon /etc/centreon/centreon.conf.php /etc/centreon/conf.pm
chmod 640 /etc/centreon/centreon.conf.php /etc/centreon/conf.pm

# Symfony cache of the scripts that boot the kernel (e.g.
# centreon-send-stats.php): without it, "Unable to create the cache directory
# (/var/cache/centreon/symfony/...)". Writable by centreon and www-data (in the
# centreon group), the two users the cron.d lines run as.
mkdir -p /var/cache/centreon
chown centreon:centreon /var/cache/centreon
chmod 775 /var/cache/centreon

# Real content, real macros already resolved by app_assembler's own
# macro-replacement pass (tmpl/install/*.cron ship with the same @WEB_USER@/
# @CENTREON_USER@/@PHP_BIN@/... placeholders as the rest of the tree, swept
# by that same sed pass since they land inside the wholesale COPY'd source —
# nothing cron-specific to resolve here).
install -d /etc/cron.d
cp /usr/share/centreon/tmpl/install/centreon.cron /etc/cron.d/centreon
cp /usr/share/centreon/tmpl/install/centstorage.cron /etc/cron.d/centstorage

# Daemon mode only (cron.d is not read when a single command is given below;
# on Kubernetes each job is its own CronJob, toggled in the chart values).
# centreon-send-stats.php sends a base telemetry payload (UUID, versions,
# platform info, auth options) to statistics.centreon.com every night for any
# non-remote central — `options.send_statistics` only gates the extra
# per-module data (cron/centreon-send-stats.php:111-158), so the job itself is
# dropped. centreon-backup.pl is dropped too (Fabien's call): this image has no
# MariaDB client (no mysqldump), and the stack's persistence is volumes, not a
# backup written inside a container torn down with `down -v`.
sed -i -e '/centreon-send-stats\.php/d' -e '/centreon-backup\.pl/d' /etc/cron.d/centreon
chmod 644 /etc/cron.d/centreon /etc/cron.d/centstorage

# Real files, not stdout-symlinked, for the same reason fpm's own
# /var/log/centreon/*.log aren't: each job's own `>> file 2>&1` redirect is
# set up fresh by cron's per-job /bin/sh wrapper, and PHP/Perl inside each
# script just inherit that fd — but cron itself (this container's PID 1)
# needs something to background-tail to the container's real stdout, same
# pattern as 02-log-tail_background.sh on fpm.
mkdir -p /var/log/centreon
for f in centAcl downtimeManager eventReportBuilder dashboardBuilder \
         knowledgebase statistics centreon-tokens \
         centreon-purge centreon-partitioning; do
  touch "/var/log/centreon/${f}.log"
done
# Real bug found only by waiting for a scheduled run and tracing cron's own
# syslog output (confirmed via a temporary socat /dev/log receiver, no
# syslog daemon in this image otherwise): these files were root:root with
# plain 664, so the www-data/centreon cron.d lines (everything except the
# single root one) failed to *open* their own `>> file` redirect —
# "Permission denied" on that open leaks back through cron's own
# stdout/stderr capture pipe instead of landing in the file at all,
# surfacing only as cron's generic "No MTA installed, discarding output",
# with zero indication of the real cause. www-data is already in the
# `centreon` group (fpm's own cross-container volume setup, reused here).
chown centreon:centreon /var/log/centreon/*.log
chmod 664 /var/log/centreon/*.log

# One job instead of the daemon: with arguments (e.g. a Kubernetes CronJob,
# one per cron.d line), set up the same config files above, then run that
# single command in the foreground with its output on the container's own
# stdout. No arguments: the cron daemon, as in the compose stack.
if [ "$#" -gt 0 ]; then
  # Release this job's lock left by its previous run. The Perl jobs' lock
  # (centreon::common::lock::sql) never resets cron_operation.running and
  # treats the job as still running when `ps` finds the recorded PID; in a
  # container every run gets the same PID, so the new run finds itself and
  # fails with "Failed to set lock". Only one run per job at a time here (the
  # Kubernetes CronJob has concurrencyPolicy: Forbid), so a set flag is stale.
  # The lock name is the script's name (eventReportBuilder, dashboardBuilder).
  job_script=$(printf '%s\n' "$*" | grep -o '/usr/share/centreon/cron/[^ ]*' | head -1 || true)
  if [ -n "$job_script" ]; then
    job_name=$(basename "$job_script")
    DB_ADDRESS="$DB_ADDRESS" DB_PORT="$DB_PORT" DB_USER="$DB_USER" DB_PASSWORD="$DB_PASSWORD" DB_CONFDB="$DB_CONFDB" \
    php -r '
      $pdo = new PDO("mysql:host=" . getenv("DB_ADDRESS") . ";port=" . (getenv("DB_PORT") ?: 3306) . ";dbname=" . getenv("DB_CONFDB"),
        getenv("DB_USER"), getenv("DB_PASSWORD"));
      $pdo->prepare("UPDATE cron_operation SET running = \"0\" WHERE name = ? AND running = \"1\"")->execute([$argv[1]]);
    ' "${job_name%.*}" || echo "warning: could not release the cron_operation lock of ${job_name%.*}" >&2
  fi
  exec "$@"
fi

tail -F /var/log/centreon/*.log > /proc/1/fd/1 2>/proc/1/fd/2 &

exec cron -f
