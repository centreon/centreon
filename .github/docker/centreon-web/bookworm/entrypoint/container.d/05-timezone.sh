#!bin/sh

if [ -n "${TZ}" ] && [ -f "/usr/share/zoneinfo/${TZ}" ]; then
  ln -sf "/usr/share/zoneinfo/${TZ}" /etc/localtime
  printf '%s\n' "${TZ}" > /etc/timezone
fi
