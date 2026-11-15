#!/bin/bash

PHP_FPM_PORT=9042

load_policy() {
  if ! semodule -i /usr/share/selinux/packages/centreon/centreon-web.pp; then
    echo "ERROR: could not load the centreon-web SELinux module, php-fpm will be denied in enforcing mode" >&2
    return
  fi
  # -a fails when the port already has a local mapping (upgrade): fall back to -m
  semanage port -a -t centreon_php_fpm_port_t -p tcp "$PHP_FPM_PORT" > /dev/null 2>&1 \
    || semanage port -m -t centreon_php_fpm_port_t -p tcp "$PHP_FPM_PORT" \
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
