#!/bin/sh

# GORGONE_API_ADDRESS set: Centreon uses an external Gorgone API (for instance gorgone-server
# running in another container) instead of the local gorgoned. The gorgone_api_* options point
# at it and the feature flag gorgone_api_commands makes centreon-web send its commands through
# that API instead of the centcore files (no shared volume needed).
if [ -n "${GORGONE_API_ADDRESS:-}" ]; then
  sql_quote() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
  }

  GORGONE_API_VALUES="('gorgone_api_address', '$(sql_quote "${GORGONE_API_ADDRESS}")'), ('gorgone_api_port', '$(sql_quote "${GORGONE_API_PORT:-8085}")')"
  if [ -n "${GORGONE_API_USERNAME:-}" ]; then
    GORGONE_API_VALUES="${GORGONE_API_VALUES}, ('gorgone_api_username', '$(sql_quote "${GORGONE_API_USERNAME}")'), ('gorgone_api_password', '$(sql_quote "${GORGONE_API_PASSWORD:-}")')"
  fi

  echo "Using the Gorgone API at ${GORGONE_API_ADDRESS}:${GORGONE_API_PORT:-8085} (local gorgoned is not started)."
  MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION:-centreon}" -e "
    DELETE FROM options WHERE \`key\` IN ('gorgone_api_address', 'gorgone_api_port', 'gorgone_api_username', 'gorgone_api_password');
    INSERT INTO options (\`key\`, \`value\`) VALUES ${GORGONE_API_VALUES};
  " || {
    echo "Failed to set the gorgone_api_* options"
    return 1
  }

  sed -i 's@"gorgone_api_commands": [0-3]@"gorgone_api_commands": 3@' /usr/share/centreon/config/features.json
  if ! grep -q '"gorgone_api_commands": 3' /usr/share/centreon/config/features.json; then
    echo "Feature flag gorgone_api_commands is unknown to this image: commands still go to the centcore files."
  fi
fi
