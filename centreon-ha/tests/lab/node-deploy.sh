#!/usr/bin/env bash
#
# Install the centreon-ha files of this checkout on a lab node, as root, at
# the paths of the centreon-ha-common package (packaging/centreon-ha-common.yaml).
# Environment: NODE1_NAME, NODE2_NAME.

set -euo pipefail

: "${NODE1_NAME:?}" "${NODE2_NAME:?}"

HA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=/dev/null
. /etc/centreon-ha-lab.env

install -d -m 755 /usr/share/centreon-ha/bin /usr/share/centreon-ha/lib \
  /etc/centreon-ha /var/log/centreon-ha \
  /usr/lib/ocf/resource.d/heartbeat /usr/lib/ocf/lib/heartbeat
install -m 755 "${HA_DIR}"/bin/*.sh /usr/share/centreon-ha/bin/
install -m 644 "${HA_DIR}"/lib/*.sh /usr/share/centreon-ha/lib/
install -m 755 "${HA_DIR}/ocf-scripts/db-centreon" /usr/lib/ocf/resource.d/heartbeat/
# The previous name of the agent, kept for existing clusters
ln -sfn db-centreon /usr/lib/ocf/resource.d/heartbeat/mariadb-centreon
install -m 644 "${HA_DIR}/ocf-scripts/db-centreon-common.sh" /usr/lib/ocf/lib/heartbeat/
ln -sfn db-centreon-common.sh /usr/lib/ocf/lib/heartbeat/mariadb-centreon-common.sh
install -m 440 "${HA_DIR}/etc/sudoers.d/centreon-cluster-db" /etc/sudoers.d/
visudo -cq

sed -e "s/@CENTRAL_MASTER_NAME@/${NODE1_NAME}/" \
  -e "s/@CENTRAL_SLAVE_NAME@/${NODE2_NAME}/" \
  -e "s/@MARIADB_REPL_USER@/${REPL_USER}/g" \
  -e "s/@MARIADB_REPL_PASSWD@/${REPL_PASSWORD}/g" \
  "${HA_DIR}/etc/centreon-ha/mysql-resources.sh" > /etc/centreon-ha/mysql-resources.sh
chmod 600 /etc/centreon-ha/mysql-resources.sh

echo "centreon-ha deployed from ${HA_DIR}"
