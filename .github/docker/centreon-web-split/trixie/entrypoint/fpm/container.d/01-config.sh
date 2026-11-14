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
envsubst '${PHP_TIMEZONE}' \
  < /usr/local/etc/php/conf.d/centreon.ini.template \
  > /usr/local/etc/php/conf.d/centreon.ini
