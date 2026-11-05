#!/bin/sh
# Shared by both delivery mechanisms wrapping the Central engine/poller
# container (centreon-engine-trixie, an external image built from
# centreon-collect, not from this repo): docker-web-split's
# docker-compose.yml (bind-mounted, entrypoint wrapper around centengine) and
# the Helm chart's centengine container (embedded via .Files.Get, hence its
# place in the chart: Helm only reads files inside the chart). MON-214281:
# keep both call sites pointing at this one file instead of each carrying its
# own copy of the logic. Not an initContainer in the chart: gorgone, which
# delivers the file, runs in the same pod.
#
# centengine crash-loops before gorgone's first "Generate Configuration
# Files" export: centengine.cfg itself is already present from first volume
# mount (the image's own package-default, same as central-broker.json on the
# broker), but the broker module config it points cbmod at
# (/etc/centreon-broker/central-module.json by default, though the real path
# is whatever that package-default or later-delivered centengine.cfg's
# broker_module/broker_module_cfg_file directive actually says) does not
# exist until delivered. Waits for that file instead, then returns: the
# caller execs the image's entrypoint.
set -eu

# Stop at once when the pod/container is stopped while waiting.
trap 'exit 0' TERM INT

cfg="${CENTENGINE_CFG:-/etc/centreon-engine/centengine.cfg}"
waited=0
while :; do
  if [ -s "$cfg" ]; then
    module_cfg=$(sed -n "s/^broker_module_cfg_file[[:space:]]*=[[:space:]]*//p" "$cfg" | tail -1)
    if [ -z "$module_cfg" ]; then
      # Older-style single line: "broker_module=<path-to-cbmod.so> <path-to-json-config>".
      module_cfg=$(sed -n "s#^broker_module[[:space:]]*=[[:space:]]*[^ ]*cbmod\.so[[:space:]]*##p" "$cfg" | tail -1)
    fi
    if [ -z "$module_cfg" ]; then
      echo "No broker_module line in $cfg -- nothing to wait for."
      break
    fi
    if [ -s "$module_cfg" ]; then
      echo "Broker module config delivered ($module_cfg)."
      break
    fi
  fi
  [ $((waited % 60)) -eq 0 ] && echo "Waiting for ${module_cfg:-$cfg} (delivered by gorgone on export)..."
  sleep 2
  waited=$((waited + 2))
done
