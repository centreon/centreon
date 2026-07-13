#!/bin/bash

rebuildSymfonyCache() {
  # centreon-central depends on every add-on module (license-manager, pp-manager,
  # auto-discovery-server, it-edition-extensions) and centreon-web, so it is always the
  # last of these to install, regardless of what order dpkg/rpm picks among them.
  # centreon-web only clears the Symfony route cache on upgrade, not on a fresh install,
  # so if its own package installed before one of these modules, the compiled route cache
  # can be missing that module's routes (e.g. autodiscovery's API returning 404) with
  # nothing to ever invalidate it. Force a rebuild here, once everything is in place.
  if [ ! -d /usr/share/centreon ]; then
    return
  fi

  # rpm re-lays-down every package-declared file on every transaction, including
  # www/install, even on a host where the web install wizard already finished and
  # removed it (dpkg does not restore a file removed after install, so this only
  # bites rpm). ModuleRouteLoader treats that directory's mere presence as
  # "installation in progress" and deliberately compiles an empty route collection
  # for every module while it exists, so a copy re-created by this same rpm
  # transaction would silently disable autodiscovery/license-manager/etc. API
  # routes as soon as we rebuild the cache below. Clear it first whenever the
  # system is already installed.
  if [ -f /etc/centreon/centreon.conf.php ] && [ -d /usr/share/centreon/www/install ]; then
    rm -rf /usr/share/centreon/www/install
  fi

  rm -rf /var/cache/centreon/symfony
  if [ "$1" = "rpm" ]; then
    su - apache -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear -q" 2> /dev/null || :
  else
    su - www-data -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear -q" 2> /dev/null || :
  fi
}

package_type="rpm"
if  [ "$1" = "configure" ]; then
  package_type="deb"
fi

rebuildSymfonyCache "$package_type"
