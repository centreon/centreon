#!/bin/bash

PHP_FPM_PORT=9042

load_policy() {
  if ! semodule -i /usr/share/selinux/packages/centreon/centreon-web.pp; then
    echo "ERROR: could not load the centreon-web SELinux module, php-fpm will be denied in enforcing mode" >&2
    return
  fi
  # -a only for a new local mapping, -m when one exists (upgrade or admin change)
  semanage_action="-a"
  if semanage port -l -C -n | awk -v p="$PHP_FPM_PORT" '$2 == "tcp" { for (i = 3; i <= NF; i++) { f = $i; sub(/,$/, "", f); if (f == p) found = 1 } } END { exit !found }'; then
    semanage_action="-m"
  fi
  semanage port "$semanage_action" -t centreon_php_fpm_port_t -p tcp "$PHP_FPM_PORT" \
    || echo "ERROR: could not label tcp/$PHP_FPM_PORT for php-fpm" >&2
  setsebool -P httpd_unified on || :
  setsebool -P httpd_can_network_connect on || :
  setsebool -P httpd_can_network_relay on || :
  setsebool -P httpd_mod_auth_pam on || :
  restorecon -R -v /var/log/centreon/login.log > /dev/null 2>&1 || :
  restorecon -R -v /var/cache/centreon/symfony/App_KernelProdContainer.php > /dev/null 2>&1 || :
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
