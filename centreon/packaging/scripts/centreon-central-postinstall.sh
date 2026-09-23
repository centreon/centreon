#!/bin/bash

rebuildSymfonyCache() {
  # DEB only. On RPM, centreon-web.yaml's %posttrans script (centreon-web-posttrans.sh)
  # already rebuilds the cache unconditionally, and RPM guarantees %posttrans runs after
  # every package's %post in the whole transaction, not just centreon-web's own
  # dependents - so doing it again here would just be a redundant extra cache:clear.
  #
  # dpkg has no equivalent to %posttrans. centreon-central depends on every add-on module
  # (license-manager, pp-manager, auto-discovery-server, it-edition-extensions) and
  # centreon-web, and dpkg does configure dependencies before dependents for a plain
  # Depends (confirmed empirically: dummy .deb packages reproducing this exact dependency
  # graph, installed together in one transaction, configure in full topological order) -
  # making centreon-central the only package guaranteed to configure after the whole set
  # on DEB. Neither centreon-web nor the 4 modules rebuild the cache on a fresh DEB
  # install (only on upgrade, each gated on their own $1=configure check), so without
  # this, the compiled route cache can be missing routes from whichever of these
  # installed last (e.g. autodiscovery's API returning 404) with nothing to invalidate it.
  if [ "$1" = "rpm" ]; then
    return
  fi

  if [ ! -d /usr/share/centreon ]; then
    return
  fi

  rm -rf /var/cache/centreon/symfony
  su - www-data -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear -q" 2> /dev/null || :
}

package_type="rpm"
if  [ "$1" = "configure" ]; then
  package_type="deb"
fi

rebuildSymfonyCache "$package_type"
