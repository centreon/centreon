#!/bin/sh
set -e

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
