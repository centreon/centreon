#!/usr/bin/env bash
#
# Test scenarios for the centreon-ha lab, run from the host by lab.sh test.
# Environment: ENGINE (mariadb or mysql), NODE1_NAME, NODE2_NAME, and
# optionally CLUSTER_TIMEOUT and REPLICATION_TIMEOUT (seconds).

set -euo pipefail

: "${ENGINE:?}" "${NODE1_NAME:?}" "${NODE2_NAME:?}"

NODES=("${NODE1_NAME}" "${NODE2_NAME}")
CLUSTER_TIMEOUT="${CLUSTER_TIMEOUT:-240}"
REPLICATION_TIMEOUT="${REPLICATION_TIMEOUT:-60}"
failures=0

node_run() {
  local node="$1"
  shift
  limactl shell --workdir / "${node}" sudo "$@"
}

sql() {
  # The mariadb client when it exists: MariaDB 11 warns about the mysql name
  local node="$1"
  shift
  node_run "${node}" bash -c 'client=$(command -v mariadb || command -v mysql) && "${client}" -N -B -u root -e "$1"' _ "$*"
}

pass() {
  echo "  PASS: $*"
}

fail() {
  echo "  FAIL: $*"
  failures=$((failures + 1))
}

scenario() {
  echo
  echo "=== $*"
}

other_node() {
  if [[ "$1" == "${NODES[0]}" ]]; then
    echo "${NODES[1]}"
  else
    echo "${NODES[0]}"
  fi
}

# Capture the outputs before matching them: under pipefail, grep -q closing
# its input early can fail the pipeline
locate_clone() {
  node_run "${NODES[0]}" crm_resource --resource ms_mysql-clone --locate 2>/dev/null || true
}

promoted_node() {
  local output
  output="$(locate_clone)"
  awk '/Promoted|Master/ { print (($NF == "Promoted" || $NF == "Master") ? $(NF - 1) : $NF); exit }' <<< "${output}"
}

running_count() {
  local output
  output="$(locate_clone)"
  grep -c 'is running on' <<< "${output}" || true
}

wait_for_cluster() {
  # Wait for one promoted instance and two running ones
  local elapsed=0 primary
  while ((elapsed < CLUSTER_TIMEOUT)); do
    primary="$(promoted_node)"
    if [[ -n "${primary}" ]] && [[ "$(running_count)" -eq 2 ]]; then
      echo "${primary}"
      return 0
    fi
    sleep 5
    elapsed=$((elapsed + 5))
  done
  node_run "${NODES[0]}" pcs status >&2 || true
  return 1
}

wait_for_running_count() {
  # Wait for $1 running instances
  local count="$1" elapsed=0
  while ((elapsed < CLUSTER_TIMEOUT)); do
    if [[ "$(running_count)" -eq "${count}" ]]; then
      return 0
    fi
    sleep 5
    elapsed=$((elapsed + 5))
  done
  return 1
}

next_host_id() {
  # The next free host_id on the primary $1
  sql "$1" "SELECT COALESCE(MAX(host_id), 0) + 1 FROM centreon.host"
}

write_and_check_replication() {
  # Insert a row on the primary $1 and wait for it on the replica $2
  local primary="$1" replica="$2" host_id elapsed=0 count
  if ! host_id="$(next_host_id "${primary}")" \
    || ! sql "${primary}" "USE centreon; INSERT INTO host VALUES (${host_id}, 'lab-${host_id}')"; then
    fail "write on the primary ${primary}"
    return
  fi
  while ((elapsed < REPLICATION_TIMEOUT)); do
    count="$(sql "${replica}" "SELECT COUNT(*) FROM centreon.host WHERE host_id = ${host_id}" 2>/dev/null || true)"
    if [[ "${count}" == "1" ]]; then
      pass "row ${host_id} written on ${primary} reached ${replica}"
      return
    fi
    sleep 2
    elapsed=$((elapsed + 2))
  done
  fail "row ${host_id} written on ${primary} did not reach ${replica}"
}

check_status_script() {
  # mysql-check-status.sh always exits 0: check its report
  local node="$1" output
  # Strip the colors (portable: BSD sed has no \x1b)
  output="$(node_run "${node}" /usr/share/centreon-ha/bin/mysql-check-status.sh 2>&1 | sed "s/$(printf '\033')\[[0-9;]*m//g")"
  if grep -q 'Connection MASTER Status' <<< "${output}" \
    && grep -q 'Connection SLAVE Status' <<< "${output}" \
    && ! grep -qE '\[KO\]|Error reports' <<< "${output}"; then
    pass "mysql-check-status.sh on ${node} reports a healthy replication"
  else
    fail "mysql-check-status.sh on ${node}:"
    sed 's/^/    /' <<< "${output}"
  fi
}

failover() {
  # Move the primary role away from $1 and bring $1 back as replica
  local old_primary="$1" new_primary="" elapsed=0
  if [[ "$(promoted_node)" != "${old_primary}" ]]; then
    fail "${old_primary} is not the primary before the failover"
    return 1
  fi
  node_run "${NODES[0]}" pcs node standby "${old_primary}"
  while ((elapsed < CLUSTER_TIMEOUT)); do
    new_primary="$(promoted_node)"
    [[ -n "${new_primary}" && "${new_primary}" != "${old_primary}" ]] && break
    sleep 5
    elapsed=$((elapsed + 5))
  done
  if [[ -z "${new_primary}" || "${new_primary}" == "${old_primary}" ]]; then
    fail "no new primary after putting ${old_primary} in standby"
    node_run "${NODES[0]}" pcs node unstandby "${old_primary}"
    return 1
  fi
  pass "${new_primary} promoted after putting ${old_primary} in standby"
  # Bring the old primary back once it really stopped
  if ! wait_for_running_count 1; then
    fail "${old_primary} did not stop in standby"
    node_run "${NODES[0]}" pcs node unstandby "${old_primary}"
    return 1
  fi
  node_run "${NODES[0]}" pcs node unstandby "${old_primary}"
  if ! wait_for_running_count 2; then
    fail "${old_primary} did not come back as replica"
    return 1
  fi
  pass "${old_primary} came back as replica"
  primary="${new_primary}"
  replica="${old_primary}"
}

set_skip_replica_start() {
  # Set skip_replica_start to $2 in the configuration of $1
  node_run "$1" bash -c ". /etc/centreon-ha-lab.env && sed -i 's/^skip_replica_start=.*/skip_replica_start=$2/' \"\${DB_CONFIG}\" && grep -qx 'skip_replica_start=$2' \"\${DB_CONFIG}\""
}

restore_lab() {
  # Leave the lab usable for the next run, whatever stopped this one
  local node restored=0
  for node in "${NODES[@]}"; do
    if ! node_run "${NODES[0]}" pcs node unstandby "${node}" > /dev/null 2>&1; then
      echo "WARNING: cannot take ${node} out of standby" >&2
      restored=1
    fi
    if [[ "${ENGINE}" == "mysql" ]] && ! set_skip_replica_start "${node}" ON > /dev/null 2>&1; then
      echo "WARNING: cannot set skip_replica_start=ON on ${node}" >&2
      restored=1
    fi
  done
  if ! node_run "${NODES[0]}" pcs resource manage ms_mysql > /dev/null 2>&1; then
    echo "WARNING: cannot make Pacemaker manage ms_mysql again" >&2
    restored=1
  fi
  if ! node_run "${NODES[0]}" pcs resource cleanup ms_mysql > /dev/null 2>&1; then
    echo "WARNING: cannot clean up the failed actions of ms_mysql" >&2
    restored=1
  fi
  return "${restored}"
}

on_exit() {
  local exit_status=$?
  if ! restore_lab && ((exit_status == 0)); then
    echo "The lab is not restored: the next run may fail"
    exit_status=1
  fi
  exit "${exit_status}"
}
trap on_exit EXIT

backup_checkpoint() {
  # The incremental backup checkpoint of $1, empty without one
  node_run "$1" cat /var/lib/centreon-backup/centreondb-smooth-backup.save 2>/dev/null || true
}

echo "Testing centreon-ha with ${ENGINE} on ${NODES[*]}"
# Start from a clean state: no failed action left by an earlier run
if ! restore_lab; then
  echo "Giving up: cannot reset the lab"
  exit 1
fi

scenario "The cluster starts the databases and promotes one node"
if primary="$(wait_for_cluster)"; then
  pass "${primary} is promoted, both instances run"
else
  fail "the cluster did not promote a node within ${CLUSTER_TIMEOUT}s"
  echo "Giving up: the other scenarios need a running cluster"
  exit 1
fi
replica="$(other_node "${primary}")"

scenario "The replication works"
write_and_check_replication "${primary}" "${replica}"
check_status_script "${NODES[0]}"

scenario "Failover to the replica, and back"
for _ in 1 2; do
  if ! failover "${primary}"; then
    break
  fi
  write_and_check_replication "${primary}" "${replica}"
  check_status_script "${primary}"
done

if [[ "${ENGINE}" == "mysql" ]]; then
  scenario "MySQL: gtid_executed holds the UUIDs of both nodes after the failovers"
  uuids="$(sql "${primary}" "SELECT @@gtid_executed" | tr ',' '\n' | grep -c ':' || true)"
  if ((uuids == 2)); then
    pass "gtid_executed holds 2 server UUIDs"
  else
    fail "gtid_executed holds ${uuids} server UUIDs, expected 2"
  fi
fi

scenario "Backup with centreondb-smooth-backup.sh on the replica"
if output="$(node_run "${replica}" /usr/share/centreon-ha/bin/centreondb-smooth-backup.sh 2>&1)"; then
  pass "centreondb-smooth-backup.sh succeeded on ${replica}"
else
  fail "centreondb-smooth-backup.sh failed on ${replica}:"
  tail -20 <<< "${output}" | sed 's/^/    /'
fi
if new_primary="$(wait_for_cluster)"; then
  pass "the cluster is healthy after the backup"
  primary="${new_primary}"
  replica="$(other_node "${primary}")"
  write_and_check_replication "${primary}" "${replica}"
else
  fail "the cluster is not healthy after the backup"
  echo "Giving up: the other scenarios need a running cluster"
  exit 1
fi

scenario "Incremental backup with centreondb-smooth-backup.sh -i on the replica"
checkpoint="$(backup_checkpoint "${replica}")"
if [[ ! "${checkpoint}" =~ ^[0-9]+$ ]]; then
  fail "the total backup left no checkpoint on ${replica} ('${checkpoint}')"
elif ! output="$(node_run "${replica}" /usr/share/centreon-ha/bin/centreondb-smooth-backup.sh -i 2>&1)"; then
  fail "centreondb-smooth-backup.sh -i failed on ${replica}:"
  tail -20 <<< "${output}" | sed 's/^/    /'
else
  new_checkpoint="$(backup_checkpoint "${replica}")"
  # The archive of the day, named by the date of the node
  archived="$(node_run "${replica}" bash -c 'tar tzf "/var/backup/mysql-$(date +%Y-%m-%d).tar.gz"' 2>&1 || true)"
  if [[ ! "${new_checkpoint}" =~ ^[0-9]+$ ]] || ((new_checkpoint <= checkpoint)); then
    fail "the checkpoint did not advance from ${checkpoint} ('${new_checkpoint}')"
  elif ! grep -q "\.$(printf '%06d' "$((checkpoint + 1))")\$" <<< "${archived}"; then
    fail "the archive misses the binary log after the checkpoint ${checkpoint}:"
    sed 's/^/    /' <<< "${archived}"
  else
    pass "the binary logs after ${checkpoint} are saved, the checkpoint is now ${new_checkpoint}"
  fi
fi

scenario "A gap in the binary logs makes the next backup a total one"
checkpoint="$(backup_checkpoint "${replica}")"
log_base="$(sql "${replica}" "SELECT @@log_bin_basename" || true)"
# Close two binary logs and purge the first one
if [[ ! "${checkpoint}" =~ ^[0-9]+$ ]] || [[ -z "${log_base}" ]] \
  || ! sql "${replica}" "FLUSH BINARY LOGS; FLUSH BINARY LOGS; PURGE BINARY LOGS TO '${log_base##*/}.$(printf '%06d' "$((checkpoint + 2))")'"; then
  fail "cannot remove the binary log after the checkpoint '${checkpoint}' on ${replica}"
elif output="$(node_run "${replica}" /usr/share/centreon-ha/bin/centreondb-smooth-backup.sh -i 2>&1)"; then
  fail "centreondb-smooth-backup.sh -i succeeded despite the gap"
elif [[ -n "$(backup_checkpoint "${replica}")" ]]; then
  fail "the checkpoint stayed despite the gap"
else
  pass "the backup failed on the gap and dropped the checkpoint"
fi
write_and_check_replication "${primary}" "${replica}"

if [[ "${ENGINE}" == "mysql" ]]; then
  scenario "MySQL: a missing prerequisite fails the start on that node only"
  if ! set_skip_replica_start "${replica}" OFF; then
    fail "cannot disable skip_replica_start on ${replica}"
  else
    # Restart the replica through Pacemaker, once it is really stopped
    node_run "${NODES[0]}" pcs node standby "${replica}"
    if ! wait_for_running_count 1; then
      fail "${replica} did not stop in standby"
    fi
    node_run "${NODES[0]}" pcs node unstandby "${replica}"
    # The failed action of the replica names the missing setting only
    found=0
    elapsed=0
    while ((elapsed < CLUSTER_TIMEOUT)); do
      status="$(node_run "${NODES[0]}" pcs status 2>&1 || true)"
      replica_status="$(grep "${replica}" <<< "${status}" || true)"
      if grep -qF "(MySQL needs skip_replica_start=ON)" <<< "${replica_status}"; then
        found=1
        break
      fi
      sleep 5
      elapsed=$((elapsed + 5))
    done
    if ((found)); then
      pass "the start on ${replica} failed on skip_replica_start"
    else
      fail "no failed start of ${replica} on skip_replica_start"
      sed 's/^/    /' <<< "${status}"
    fi
    if [[ -n "${primary}" && "$(promoted_node)" == "${primary}" ]]; then
      pass "${primary} stayed promoted"
    else
      fail "${primary} is no longer promoted"
    fi
    set_skip_replica_start "${replica}" ON
    node_run "${replica}" bash -c '. /etc/centreon-ha-lab.env && systemctl stop "${DB_SERVICE}"' || true
    node_run "${NODES[0]}" pcs resource cleanup ms_mysql > /dev/null
    if new_primary="$(wait_for_cluster)"; then
      pass "the cluster recovers once the prerequisite is restored"
      primary="${new_primary}"
      replica="$(other_node "${primary}")"
      write_and_check_replication "${primary}" "${replica}"
    else
      fail "the cluster did not recover"
    fi
  fi
fi

echo
if ((failures == 0)); then
  echo "All scenarios passed with ${ENGINE}"
else
  echo "${failures} check(s) failed with ${ENGINE}"
  exit 1
fi
