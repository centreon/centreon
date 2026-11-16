#!/bin/sh

# The slim image ships no rrdtool and shares no RRD file with Broker: the graphs are read from
# VictoriaMetrics only (74-victoria-metrics.sh).
if [ -z "${VICTORIA_METRICS_URL:-}" ]; then
  echo "VICTORIA_METRICS_URL is required by the slim centreon-web image (no rrdtool, no RRD file)."
  exit 1
fi
