#!/bin/bash

install() {
  echo "Installing centreon-web selinux rules ..."
  semodule -i /usr/share/selinux/packages/centreon/centreon-web.pp || :
  setsebool -P httpd_unified on || :
  setsebool -P httpd_can_network_connect on || :
  setsebool -P httpd_can_network_relay on || :
  setsebool -P httpd_mod_auth_pam on || :
  restorecon -R -v /var/log/centreon/login.log > /dev/null 2>&1 || :
  restorecon -R -v /var/cache/centreon/symfony/App_KernelProdContainer.php > /dev/null 2>&1 || :
}

upgrade() {
  echo "Updating centreon-web selinux rules ..."
  semodule -i /usr/share/selinux/packages/centreon/centreon-web.pp || :
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
    install
    ;;
  "2" | "upgrade")
    upgrade
    ;;
esac
