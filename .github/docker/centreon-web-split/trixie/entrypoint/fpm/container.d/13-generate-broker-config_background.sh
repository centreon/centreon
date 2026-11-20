#!/bin/sh

# Keeps retrying "Generate Configuration Files" + "Move Export Files" (via
# the REST generate-and-reload endpoint, see generate-broker-configuration.php)
# so centreon-broker gets delivered a real config instead of booting against
# the package-default central-broker.json — central-gorgone's own broker
# image now refuses to start on that default (fingerprint guard,
# MON-210954), so this is what gets it past that and starting cleanly,
# without a human clicking through the legacy UI.
#
# fpm has no compose-level dependency on gorgone-central/gorgone-central-poller
# being up (adding one would create a cycle: fpm -> gorgone-central-poller ->
# httpd -> fpm, since the poller waits on httpd and httpd waits on fpm) — so
# this retries the actual delivery call on a fixed interval regardless.
# generate-broker-configuration.php itself now polls gorgone's own
# GET /internal/constatus first (2026-10-05, Fabien's suggestion) to wait
# for a real connection before attempting delivery, bounded to 55s with a
# fallback to attempting anyway — a latency optimization layered on top,
# this outer loop is still the actual correctness guarantee if that check
# ever can't reach gorgone's API at all.
#
# Never stops after a first "success" (2026-10-05 correction — it used to):
# the REST endpoint's 204 only confirms the API-level generate+move+reload
# call completed, not that the downstream gorgone REMOTECOPY actually landed
# on a connected node. Confirmed for real after a `docker compose up -d`
# against already-stopped containers (not a fresh `down -v` install): the
# very first call returned 204 while gorgone-central-poller's pullwss module
# was still loading, the file never actually arrived, and centreon-broker
# kept crash-looping on the package-default config until a manual re-run
# delivered it for real. Idempotent and cheap to repeat: each attempt only
# regenerates+redelivers from the current DB state, the same as clicking
# "Generate Configuration Files" twice in the UI — so this keeps running on
# a slow interval for the container's whole lifetime instead of exiting,
# self-healing this exact race without needing to verify gorgone's
# connection state directly.
#
# `|| true` on the su call is load-bearing, not decoration (second real bug
# found the same day, testing the fix above): container.sh runs `set -e`
# before backgrounding `*_background*` scripts via `. "$file" &`, and dash
# (this image's /bin/sh) propagates that `set -e` into the backgrounded
# subshell. A failing `su`/php call inside this while loop's BODY (not its
# controlling condition, so not -e-exempt) killed the whole subshell
# immediately on its very first failed attempt — before ever reaching
# `sleep 60` — so on a real fresh install (gorgone not connected yet on the
# first try, exactly the expected case) this loop silently died after one
# attempt instead of retrying, and nothing re-ran it. Confirmed via `ps aux`
# inside the running container: the backgrounded instance's PID was gone
# entirely (not hung, not a zombie), while a manual `docker compose exec`
# re-run of the same script worked fine — because a fresh `exec` shell
# never inherits container.sh's own `set -e` in the first place, which is
# exactly why this didn't show up under manual testing.
# RUN_CONFIG_DELIVERY defaults to RUN_INSTALL (compose behavior). The helm
# chart turns it off in fpm and delivers once from a dedicated Job instead.
if [ "${RUN_CONFIG_DELIVERY:-${RUN_INSTALL:-false}}" != "true" ]; then
  exit 0
fi

echo "Generating and delivering centreon-broker/centengine configuration..."

while true; do
  su www-data -s /bin/bash -c "php -d allow_url_fopen=On /usr/local/lib/centreon-fpm/generate-broker-configuration.php" || true
  sleep 60
done
