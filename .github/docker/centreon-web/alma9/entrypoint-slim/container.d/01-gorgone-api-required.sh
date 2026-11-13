#!/bin/sh

# The slim image ships no local gorgoned, engine nor broker: without an external Gorgone API,
# commands would be written to centcore files that nothing reads.
if [ -z "${GORGONE_API_ADDRESS:-}" ]; then
  echo "GORGONE_API_ADDRESS is required by the slim centreon-web image (no local gorgoned)."
  exit 1
fi
