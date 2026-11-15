#!/bin/bash

. /usr/share/selinux/packages/centreon/centreon-selinux-functions.sh

PHP_FPM_PORT=9042

load_policy() {
  # paths: keep in sync with centreon-web.fc
  if ! centreon_selinux_load centreon-web /usr/share/centreon /var/lib/centreon /var/run/centreon \
    /usr/bin/centFillTrapDB /usr/bin/centreon /usr/bin/centreonSyncArchives /usr/bin/centreonSyncPlugins \
    /usr/bin/centreon_trap_send /usr/bin/generateSqlLite; then
    echo "ERROR: php-fpm will be denied in enforcing mode" >&2
    return
  fi
  # -a only for a new local mapping, -m when one exists (admin change); nothing when already ours
  semanage_action="-a"
  current_type=$(semanage port -l -C -n | awk -v p="$PHP_FPM_PORT" '$2 == "tcp" { for (i = 3; i <= NF; i++) { f = $i; sub(/,$/, "", f); if (f == p) { print $1; exit } } }')
  if [ "$current_type" = "centreon_php_fpm_port_t" ]; then
    semanage_action=""
  elif [ -n "$current_type" ]; then
    semanage_action="-m"
    echo "WARNING: tcp/$PHP_FPM_PORT was labelled $current_type, it is now labelled centreon_php_fpm_port_t" >&2
  fi
  if [ -n "$semanage_action" ] && ! semanage port "$semanage_action" -t centreon_php_fpm_port_t -p tcp "$PHP_FPM_PORT"; then
    echo "ERROR: could not label tcp/$PHP_FPM_PORT for php-fpm, run: semanage port $semanage_action -t centreon_php_fpm_port_t -p tcp $PHP_FPM_PORT" >&2
  fi
  setsebool -P httpd_unified on || :
  setsebool -P httpd_can_network_connect on || :
  setsebool -P httpd_can_network_relay on || :
  setsebool -P httpd_mod_auth_pam on || :
  restorecon -R /var/log/centreon/login.log /var/cache/centreon/symfony > /dev/null 2>&1 || :
}

action="$1"
if  [ "$1" = "configure" ] && [ -z "$2" ]; then
  action="install"
elif [ "$1" = "configure" ] && [ -n "$2" ]; then
  action="upgrade"
fi

case "$action" in
  "1" | "install")
    echo "Installing centreon-web selinux rules ..."
    load_policy
    ;;
  "2" | "upgrade")
    echo "Updating centreon-web selinux rules ..."
    load_policy
    ;;
esac
