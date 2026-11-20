#!/bin/sh
set -e

# Same convention as httpd's own 10-config.sh: never bake a deployment-
# specific value into the image, substitute it at container start instead.
# Mirrors the real packaging postinstall's setPhpTimezone()
# (packaging/scripts/centreon-web-postinstall.sh), which auto-detects the
# host's timezone (/etc/timezone, date "+%Z") and falls back to UTC when
# detection fails — a container has no meaningful host timezone to detect,
# so PHP_TIMEZONE is the container-native equivalent of that same value,
# defaulting to UTC for the same reason the real script does.
# MON-214095: files (default, nothing changes when unset) or redis
# (Valkey) PHP sessions. PHP_SESSION_SAVE_PATH is the base DSN with no
# credentials in it (e.g. tcp://valkey:6379) — the password is a SEPARATE
# env var, rawurlencode()'d and appended here as ?auth=..., so neither the
# compose file nor the Helm chart's Secret template has to pre-encode a
# password that might contain URL-unsafe characters.
# container.sh turns on `set -x` for the whole entrypoint when DEBUG=true —
# inherited here since container.d/*.sh is sourced, not executed as its own
# process. Without disabling it across this block, the password would be
# traced to stderr by the shell itself (same risk class, same fix, as
# centreon-broker's own engine-context.json block).
was_tracing=0
case "$-" in *x*) was_tracing=1 ;; esac
set +x

PHP_SESSION_HANDLER="${PHP_SESSION_HANDLER:-files}"
if [ "$PHP_SESSION_HANDLER" = "redis" ]; then
  : "${PHP_SESSION_SAVE_PATH:?PHP_SESSION_HANDLER=redis requires PHP_SESSION_SAVE_PATH (e.g. tcp://valkey:6379)}"
  resolved_save_path="$PHP_SESSION_SAVE_PATH"
  if [ -n "${PHP_SESSION_REDIS_PASSWORD:-}" ]; then
    encoded_password=$(php -r 'echo rawurlencode($argv[1]);' "$PHP_SESSION_REDIS_PASSWORD")
    case "$resolved_save_path" in
      *\?*) resolved_save_path="${resolved_save_path}&auth=${encoded_password}" ;;
      *) resolved_save_path="${resolved_save_path}?auth=${encoded_password}" ;;
    esac
  fi
  export PHP_SESSION_SAVE_HANDLER="redis"
  export PHP_SESSION_SAVE_PATH_RESOLVED="$resolved_save_path"
  export PHP_SESSION_REDIS_LOCKING_ENABLED=1
else
  export PHP_SESSION_SAVE_HANDLER="files"
  export PHP_SESSION_SAVE_PATH_RESOLVED="/var/lib/php/sessions"
  export PHP_SESSION_REDIS_LOCKING_ENABLED=0
fi

# Empty = the packaged default.
export PHP_MAX_EXECUTION_TIME="${PHP_MAX_EXECUTION_TIME:-300}"

envsubst '${PHP_TIMEZONE} ${PHP_MEMORY_LIMIT} ${PHP_MAX_EXECUTION_TIME} ${PHP_SESSION_SAVE_HANDLER} ${PHP_SESSION_SAVE_PATH_RESOLVED} ${PHP_SESSION_REDIS_LOCKING_ENABLED}' \
  < /usr/local/etc/php/conf.d/centreon.ini.template \
  > /usr/local/etc/php/conf.d/centreon.ini
# Contains the Valkey auth= password in clear once resolved above — root:
# www-data, 0640: php-fpm's own worker processes run as www-data (owner of
# nothing here, but in the www-data GROUP) and the CLI entrypoints that
# source this same php.ini (console, the autodisco-save-worker) also run as
# www-data (`su -s ... www-data`), so group-read is both necessary and
# sufficient — no need for world-read like every other conf.d/*.ini file.
chown root:www-data /usr/local/etc/php/conf.d/centreon.ini
chmod 0640 /usr/local/etc/php/conf.d/centreon.ini

if [ "$was_tracing" = "1" ]; then
  set -x
fi
