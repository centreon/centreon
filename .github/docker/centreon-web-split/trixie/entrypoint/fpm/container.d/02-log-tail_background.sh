#!/bin/sh
set -e

# The build-time "symlink to /dev/stdout" trick used elsewhere in this repo
# does NOT work for these files, confirmed empirically, not assumed: PHP's
# own fopen()/proc_open() against that symlink fails with "No such file or
# directory" — a pipe opened by path in append mode from a userland process
# behaves differently from a shell `>` redirect, which is why an earlier,
# shell-only test of this looked fine. /proc/1/fd/1 directly isn't a fix
# either — that fails with "Permission denied" for www-data (php-fpm's
# workers), since it belongs to PID 1's owner, not the worker's.
#
# So these 8 files stay real on-disk files (same reasoning login.log
# already has, for a different underlying cause) and get tailed to the
# container's actual stdout here instead — this script itself runs as
# root, sourced by container.sh before any privilege drop, with its own
# stdout already redirected to /proc/1/fd/1 by container.sh's own
# `*_background*` convention, so no fopen() from PHP is involved for the
# tail process itself. `-F` retries files that don't exist yet, so this is
# safe to start before the app has written anything.
tail -F -q \
  /var/log/centreon/prod.web.log \
  /var/log/centreon/prod.access.log \
  /var/log/centreon/prod.token.log \
  /var/log/centreon/prod.password.log \
  /var/log/centreon/prod.upgrade.log \
  /var/log/centreon/prod.plugin-pack-manager.log \
  /var/log/centreon/prod.poller-install.log \
  /var/log/centreon/rrdtool.log
