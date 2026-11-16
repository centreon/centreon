#!/bin/sh

# VICTORIA_METRICS_URL set: the performance and status graphs of the API v2 and of the dashboards
# are read from VictoriaMetrics (written by the victoria_metrics output of Centreon Broker) instead
# of the RRD files, which this image does not share with Broker. VICTORIA_METRICS_USER and
# VICTORIA_METRICS_PASSWORD set the optional HTTP basic authentication.
if [ -n "${VICTORIA_METRICS_URL:-}" ]; then
  sql_quote() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
  }

  VICTORIA_METRICS_VALUES="('metrics_data_source', 'victoria_metrics'), ('victoria_metrics_url', '$(sql_quote "${VICTORIA_METRICS_URL}")'), ('victoria_metrics_user', '$(sql_quote "${VICTORIA_METRICS_USER:-}")'), ('victoria_metrics_password', '$(sql_quote "${VICTORIA_METRICS_PASSWORD:-}")')"

  echo "Reading the graphs from VictoriaMetrics at ${VICTORIA_METRICS_URL}."
  MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION:-centreon}" -e "
    DELETE FROM options WHERE \`key\` IN ('metrics_data_source', 'victoria_metrics_url', 'victoria_metrics_user', 'victoria_metrics_password');
    INSERT INTO options (\`key\`, \`value\`) VALUES ${VICTORIA_METRICS_VALUES};
  " || {
    echo "Failed to set the metrics_data_source and victoria_metrics_* options"
    return 1
  }
fi
