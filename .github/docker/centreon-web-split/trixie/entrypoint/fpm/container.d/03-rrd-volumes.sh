#!/bin/sh
set -e

# RRD_VOLUMES_CHOWN=false when another component owns these directories (the
# helm chart chowns them from the broker pod, and mounts them read-only here).
if [ "${RRD_VOLUMES_CHOWN:-true}" != "true" ]; then
  return 0
fi

# centreon-status/centreon-metrics are fresh named volumes with no
# image-provided ownership (fpm's own Dockerfile doesn't bake these
# docker-web-split-specific RRD paths in, unlike /var/lib/centreon etc.) —
# centreon-broker needs them writable as its own centreon-broker user, but
# its image ships USER centreon-broker (non-root) by default, so it can't
# chown them itself without an override of its own. fpm already runs this
# early container.d phase as root AND already bakes in centreon-broker's
# exact UID/GID (see the Dockerfile's own useradd block, added specifically
# for cross-container volume consistency) — reused here instead of adding a
# dedicated init container just to pull the (large) broker image for one
# mkdir+chown. Runs early (03-), well before 12-install.sh's lengthy
# RUN_INSTALL work, so centreon-broker isn't kept waiting on fpm's own
# healthcheck.
mkdir -p /var/lib/centreon/status /var/lib/centreon/metrics
chown -R centreon-broker:centreon-broker /var/lib/centreon/status /var/lib/centreon/metrics
