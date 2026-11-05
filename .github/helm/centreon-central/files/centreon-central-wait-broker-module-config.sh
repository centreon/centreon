#!/bin/sh
# MON-214281: waits for the cbmod config gorgone delivers, instead of
# centengine crash-looping before it exists. Parsed from centengine.cfg
# (path depends on the poller, <name>-module.json; engine.class.php writes
# broker_module_cfg_file= or "broker_module=...cbmod.so <path>"). Shared by
# the chart (this file) and docker-web-split's compose (bind-mounted).
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
