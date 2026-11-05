#!/bin/sh

# Delivers "Generate Configuration Files" + "Move Export Files" (via the
# REST generate-and-reload endpoint, see generate-broker-configuration.php)
# so centreon-broker gets a real config instead of booting against the
# package-default central-broker.json — central-gorgone's own broker image
# refuses to start on that default (fingerprint guard, MON-210954), so this
# is what gets it past that and starting cleanly, without a human clicking
# through the legacy UI.
#
# fpm has no compose-level dependency on gorgone-central/gorgone-central-poller
# being up (adding one would create a cycle: fpm -> gorgone-central-poller ->
# httpd -> fpm, since the poller waits on httpd and httpd waits on fpm) — so
# this retries the delivery call until it genuinely succeeds, then stops.
#
# CONFIG_DELIVERY_REQUIRE_CONNECTED=1 is what makes "stop on first success"
# safe here: without it, generate-broker-configuration.php's 204 only
# confirms the API-level generate+move+reload call completed, not that the
# downstream gorgone REMOTECOPY actually landed on a connected node
# (confirmed for real: a 204 once came back while gorgone-central-poller's
# pullwss module was still loading, and nothing was actually delivered).
# With this flag, the script itself waits up to 55s for a confirmed gorgone
# connection and fails (exit 1) rather than attempting delivery blind if
# that window elapses — so a success here really means delivered, and this
# loop retrying on failure (instead of looping forever regardless) stays
# correct.
#
# RUN_CONFIG_DELIVERY defaults to RUN_INSTALL (compose behavior). The helm
# chart turns it off in fpm and delivers once from a dedicated Job instead.
if [ "${RUN_CONFIG_DELIVERY:-${RUN_INSTALL:-false}}" != "true" ]; then
  exit 0
fi

echo "Generating and delivering centreon-broker/centengine configuration..."

# Capped exponential backoff, not a fixed interval: most of the time the
# very first or second attempt succeeds (gorgone already connected), so
# retrying fast keeps the dev/CI inner loop short while still giving a slow
# start (e.g. a fresh install still migrating the schema) up to a minute
# between attempts instead of hammering it.
#
# Every retry/backoff step below lives inside an `if`/`while` condition on
# purpose, never as a bare command whose own failure could trip `set -e`:
# container.sh backgrounds `*_background*` scripts via `. "$file" &` under
# `set -e`, and dash (this image's /bin/sh) propagates that into the
# backgrounded subshell — a bare failing command in the loop BODY (not a
# condition, so not -e-exempt) killed this exact script after its very
# first failed attempt before this fix (confirmed via `ps aux`: the
# backgrounded PID was gone entirely, not hung).
attempt=0
delay=5
max_delay=60
while true; do
  attempt=$((attempt + 1))
  if CONFIG_DELIVERY_REQUIRE_CONNECTED=1 su www-data -s /bin/bash -c \
    "php -d allow_url_fopen=On /usr/local/lib/centreon-fpm/generate-broker-configuration.php"; then
    echo "Configuration delivered on attempt ${attempt}."
    break
  fi
  echo "Configuration delivery attempt ${attempt} failed, retrying in ${delay}s." >&2
  sleep "$delay"
  if [ "$delay" -lt "$max_delay" ]; then
    delay=$((delay * 2))
  else
    delay=$max_delay
  fi
done

# TRAP_POLLER_IDS (pollers running centreontrapd): once config delivery
# succeeded, send them their first trap database, retrying on its own
# backoff until it works once. centreontrapd exits on a missing one
# (read-only definitions directory). Same script as the helm chart's
# config-delivery Job.
if [ -n "${TRAP_POLLER_IDS:-}" ]; then
  attempt=0
  delay=5
  while true; do
    attempt=$((attempt + 1))
    if su www-data -s /bin/bash -c \
      "php -d allow_url_fopen=On /usr/local/lib/centreon-fpm/deliver-traps.php"; then
      echo "Trap database delivered on attempt ${attempt}."
      break
    fi
    echo "Trap database delivery attempt ${attempt} failed, retrying in ${delay}s." >&2
    sleep "$delay"
    if [ "$delay" -lt "$max_delay" ]; then
      delay=$((delay * 2))
    else
      delay=$max_delay
    fi
  done
fi
