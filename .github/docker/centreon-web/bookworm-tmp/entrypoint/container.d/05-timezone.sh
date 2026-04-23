#!/bin/sh

: "${TZ:=UTC}"

# update system timezone files if zoneinfo present (optional)
ZONE="/usr/share/zoneinfo/${TZ}"
if [ -f "$ZONE" ]; then
  ln -sf "$ZONE" /etc/localtime 2>/dev/null || true
  printf '%s\n' "$TZ" > /etc/timezone 2>/dev/null || true
fi

# write PHP timezone ini from TZ
PHP_INI="/etc/php/8.2/mods-available/timezone.ini"
printf 'date.timezone = %s\n' "$TZ" > "$PHP_INI" || true
chmod 644 "$PHP_INI" || true

# write PHP timezone into PHP's conf.d so php-fpm and php-cli see it
PHP_CONF_DIR="/usr/local/etc/php/conf.d"
mkdir -p "$PHP_CONF_DIR"
printf 'date.timezone = %s\n' "$TZ" > "$PHP_CONF_DIR/99-timezone.ini"
chmod 644 "$PHP_CONF_DIR/99-timezone.ini" || true
