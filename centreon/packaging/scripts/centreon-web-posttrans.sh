#!/bin/bash

rebuildSymfonyCache() {
  if [ "$1" = "rpm" ]; then
    echo "Rebuilding Centreon application cache ..."
    rm -rf /var/cache/centreon/symfony
    su - apache -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear"
  fi
}

package_type="rpm"
if  [ "$1" = "configure" ]; then
  package_type="deb"
fi

action="$1"
if  [ "$1" = "configure" ] && [ -z "$2" ]; then
  # Alpine linux does not pass args, and deb passes $1=configure
  action="install"
elif [ "$1" = "configure" ] && [ -n "$2" ]; then
  # deb passes $1=configure $2=<current version>
  action="upgrade"
fi

case "$action" in
  "2" | "upgrade")
    rebuildSymfonyCache $package_type
    ;;
esac
