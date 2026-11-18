#!/usr/bin/env bash
#
# Create the two-node Pacemaker cluster and the ms_mysql resource, as root on
# the first node, as the Centreon HA documentation does.
# Environment: NODE1_NAME, NODE2_NAME.

set -euo pipefail

: "${NODE1_NAME:?}" "${NODE2_NAME:?}"

# shellcheck source=/dev/null
. /etc/centreon-ha-lab.env

if ! pcs status > /dev/null 2>&1; then
  pcs host auth "${NODE1_NAME}" "${NODE2_NAME}" -u hacluster -p "${HACLUSTER_PASSWORD}"
  pcs cluster setup centreon_lab "${NODE1_NAME}" "${NODE2_NAME}" --force
  pcs cluster start --all --wait
  pcs cluster enable --all
fi
# No fencing device in the lab
pcs property set stonith-enabled=false

# A rerun completes what an interrupted one left
if pcs resource config ms_mysql-clone > /dev/null 2>&1; then
  pcs resource enable ms_mysql
  echo "ms_mysql already exists"
  exit 0
fi

# MariaDB ships mysqld_safe, as in the documentation. MySQL does not: leave
# binary unset to let the agent find the server.
binary_option=()
if [[ "${ENGINE}" == "mariadb" ]]; then
  binary_option=(binary="/usr/bin/mysqld_safe")
fi

# The guide creates the resource and then makes it promotable: created
# enabled, Pacemaker starts it in between, which the agent refuses. Create
# it disabled, and enable it once promotable.
# MariaDB uses the agent name of the guide, which is now a symlink
if [[ "${ENGINE}" == "mariadb" ]]; then
  agent="mariadb-centreon"
else
  agent="db-centreon"
fi

if ! pcs resource config ms_mysql > /dev/null 2>&1; then
  pcs resource create ms_mysql "ocf:heartbeat:${agent}" --disabled \
    config="${DB_CONFIG}" \
    pid="${PID_FILE}" \
    datadir="${DATADIR}" \
    socket="${SOCKET}" \
    "${binary_option[@]}" \
    node_list="${NODE1_NAME} ${NODE2_NAME}" \
    replication_user="${REPL_USER}" \
    replication_passwd="${REPL_PASSWORD}" \
    test_user="${REPL_USER}" \
    test_passwd="${REPL_PASSWORD}" \
    test_table="centreon.host"
fi

pcs resource promotable ms_mysql \
  promoted-max="1" \
  clone-max="2" \
  globally-unique="false" \
  clone-node-max="1" \
  notify="true"
pcs resource enable ms_mysql

echo "ms_mysql created"
