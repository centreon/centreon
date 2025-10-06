#!/bin/sh

APP_ENV="${APP_ENV:=-prod}"
: "${APP_SECRET:=}"

# if a .env.local file was mounted (dev), keep it. Otherwise render compiled PHP env file.
if [ -f /usr/share/centreon/.env.local ]; then
  echo "Using mounted .env.local"
else
  echo "Rendering env.local.php from environment"
  envsubst < /usr/share/centreon/www/install/tmp/env.local.php.tpl > /usr/share/centreon/.env.local.php
  chown www-data:www-data /usr/share/centreon/.env.local.php || true
fi
