#!/usr/bin/env bash
#
# Two-node Centreon HA database lab on Lima VMs, to test centreon-ha with
# MariaDB or MySQL. See README.md.

set -euo pipefail

LAB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NODES=(ha-node1 ha-node2)
DISK_SIZE="${DISK_SIZE:-10GiB}"
VM_CPUS="${VM_CPUS:-2}"
VM_MEMORY="${VM_MEMORY:-2}"
ENGINE_FILE="${LAB_DIR}/.engine"

usage() {
  cat <<EOF
Usage: $(basename "$0") <command> [args]

Commands:
  up <engine> [os]     create, provision, deploy, sync and cluster (all steps)
  create [os] [engine] create and start the VMs (the engine decides whether
                       MySQL on Debian 13 needs x86_64 VMs)
  provision <engine>   install the engine, Pacemaker and the lab configuration
  deploy               install the centreon-ha files of this checkout
  sync                 initial replication with mysql-sync-bigdb.sh
  cluster              create the Pacemaker cluster and the ms_mysql resource
  test                 run the test scenarios
  status               show the cluster status
  shell <node>         open a root shell on a node (ha-node1 or ha-node2)
  destroy              delete the VMs and their disks
  matrix [--skip-emulated] [dir]
                       run up and test for every engine and distribution; the
                       summary and the logs go to dir (default: ./matrix-logs).
                       --skip-emulated skips the combinations that need a
                       slow x86_64 emulation on this host (MySQL on Debian 13)

Engines: mariadb, mysql. Distributions (os): alma9 (default), debian13.
EOF
}

die() {
  echo "ERROR: $*" >&2
  exit 1
}

node_run() {
  # Run a command as root on a node
  local node="$1"
  shift
  limactl shell --workdir / "${node}" sudo "$@"
}

node_ip() {
  node_run "$1" ip -4 -o route get 1.1.1.1 | awk '{ for (i = 1; i < NF; i++) if ($i == "src") print $(i + 1) }'
}

prefix_lines() {
  # Prefix each line of the input with [$1]
  local line
  while IFS= read -r line || [[ -n "${line}" ]]; do
    printf '[%s] %s\n' "$1" "${line}"
  done
}

stop_node_jobs() {
  # Stop the jobs of run_on_nodes, then die of signal $1 rather than exit, so
  # that a caller such as matrix stops too on an interrupt
  local pid
  for pid in ${pids[@]+"${pids[@]}"}; do
    kill -TERM -- "-${pid}" 2> /dev/null || true
  done
  trap - INT TERM HUP
  kill "-$1" "$$"
}

run_on_nodes() {
  # Run "$1 <node> <index> <other arguments>" on both nodes at once, and fail
  # if it fails on either
  local action="$1" index pids=() failed=0
  shift
  # A process group per job (set -m): an interrupt stops each job with all it
  # started, which background jobs of a script would otherwise survive
  trap 'stop_node_jobs INT' INT
  trap 'stop_node_jobs TERM' TERM
  trap 'stop_node_jobs HUP' HUP
  set -m
  for index in 0 1; do
    ( "${action}" "${NODES[${index}]}" "${index}" "$@" 2>&1 | prefix_lines "${NODES[${index}]}" ) < /dev/null &
    pids+=("$!")
  done
  set +m
  for index in 0 1; do
    if ! wait "${pids[${index}]}"; then
      echo "ERROR: ${action} failed on ${NODES[${index}]}" >&2
      failed=1
    fi
  done
  trap - INT TERM HUP
  return "${failed}"
}

lab_engine() {
  [[ -f "${ENGINE_FILE}" ]] || die "no engine provisioned, run: $0 provision <mariadb|mysql>"
  cat "${ENGINE_FILE}"
}

lima_template() {
  # LIMA_TEMPLATE overrides the template of the distribution
  if [[ -n "${LIMA_TEMPLATE:-}" ]]; then
    echo "${LIMA_TEMPLATE}"
    return
  fi
  case "${1:-alma9}" in
    alma9) echo "template:almalinux-9" ;;
    debian13) echo "template:debian-13" ;;
    *) die "unknown distribution '$1', expected alma9 or debian13" ;;
  esac
}

needs_emulation() {
  # $1: engine, $2: distribution. MySQL publishes its Debian 13 packages for
  # x86_64 only: other hosts emulate x86_64 VMs, which is much slower.
  [[ "$1" == "mysql" && "$2" == "debian13" && "$(uname -m)" != "x86_64" ]]
}

create_node() {
  # $1: node, $2: index, $3: Lima template, then the architecture options
  local node="$1" template="$3"
  shift 3
  if grep -qx "${node}" <<< "$(limactl list --quiet 2>/dev/null)"; then
    echo "${node} already exists"
    return
  fi
  limactl disk create "${node}-data" --size "${DISK_SIZE}"
  limactl create --tty=false --name "${node}" \
    --cpus "${VM_CPUS}" --memory "${VM_MEMORY}" ${1+"$@"} \
    --network lima:user-v2 \
    --set ".additionalDisks=[{\"name\": \"${node}-data\", \"format\": false}]" \
    "${template}"
  limactl start "${node}"
}

cmd_create() {
  local os="${1:-alma9}" engine="${2:-}" template arch_options=()
  template="$(lima_template "${os}")"
  if needs_emulation "${engine}" "${os}"; then
    echo "WARNING: MySQL has no ${os} packages for $(uname -m): emulating x86_64 VMs, which is much slower" >&2
    arch_options=(--arch x86_64 --vm-type qemu)
  fi
  run_on_nodes create_node "${template}" ${arch_options[@]+"${arch_options[@]}"}
}

provision_node() {
  # $1: node, $2: index, $3: engine, $4 and $5: IP addresses of the nodes
  echo "### Provision $1 ($3)"
  node_run "$1" env \
    ENGINE="$3" NODE_ID="$(($2 + 1))" NODE_NAME="$1" \
    NODE1_NAME="${NODES[0]}" NODE1_IP="$4" \
    NODE2_NAME="${NODES[1]}" NODE2_IP="$5" \
    bash "${LAB_DIR}/node-provision.sh"
}

cmd_provision() {
  local engine="${1:-}" node1_ip node2_ip index node
  [[ "${engine}" == "mariadb" || "${engine}" == "mysql" ]] || die "engine must be mariadb or mysql"
  if [[ "${engine}" == "mysql" ]] \
    && [[ "$(limactl list --format '{{.Arch}}' "${NODES[0]}")" != "x86_64" ]] \
    && node_run "${NODES[0]}" grep -qx 'ID=debian' /etc/os-release; then
    die "MySQL has no Debian packages for this architecture, recreate x86_64 VMs: $0 destroy; $0 create debian13 mysql"
  fi
  node1_ip="$(node_ip "${NODES[0]}")"
  node2_ip="$(node_ip "${NODES[1]}")"
  # The nodes only need each other's address: provision them at once
  run_on_nodes provision_node "${engine}" "${node1_ip}" "${node2_ip}"
  # Let the mysql user of each node ssh to the other, for mysql-sync-bigdb.sh
  for index in 0 1; do
    node_run "${NODES[${index}]}" cat /home/mysql/.ssh/id_ed25519.pub \
      | node_run "${NODES[$((1 - index))]}" bash -c \
        'cat >> /home/mysql/.ssh/authorized_keys && chown mysql:mysql /home/mysql/.ssh/authorized_keys && chmod 600 /home/mysql/.ssh/authorized_keys'
  done
  for node in "${NODES[@]}"; do
    node_run "${node}" bash -c "ssh-keyscan -q ${NODES[*]} > /home/mysql/.ssh/known_hosts && chown mysql:mysql /home/mysql/.ssh/known_hosts"
  done
  echo "${engine}" > "${ENGINE_FILE}"
}

cmd_deploy() {
  local node
  for node in "${NODES[@]}"; do
    echo "### Deploy centreon-ha on ${node}"
    node_run "${node}" env \
      NODE1_NAME="${NODES[0]}" NODE2_NAME="${NODES[1]}" \
      bash "${LAB_DIR}/node-deploy.sh"
  done
}

cmd_sync() {
  echo "### Initial replication from ${NODES[0]} to ${NODES[1]}"
  # mysql-sync-bigdb.sh copies the datadir of the running primary to the
  # stopped replica
  node_run "${NODES[1]}" bash -c '. /etc/centreon-ha-lab.env && systemctl stop "${DB_SERVICE}"'
  node_run "${NODES[0]}" bash -c '. /etc/centreon-ha-lab.env && systemctl start "${DB_SERVICE}"'
  node_run "${NODES[0]}" /usr/share/centreon-ha/bin/mysql-sync-bigdb.sh
}

cmd_cluster() {
  local node
  echo "### Create the Pacemaker cluster"
  # Pacemaker starts the databases
  for node in "${NODES[@]}"; do
    node_run "${node}" bash -c '. /etc/centreon-ha-lab.env && systemctl stop "${DB_SERVICE}"'
  done
  node_run "${NODES[0]}" env NODE1_NAME="${NODES[0]}" NODE2_NAME="${NODES[1]}" \
    bash "${LAB_DIR}/cluster-setup.sh"
}

cmd_test() {
  # Emulated VMs are much slower: give the cluster more time
  if [[ "$(limactl list --format '{{.Arch}}' "${NODES[0]}")" != "$(uname -m | sed 's/arm64/aarch64/')" ]]; then
    export CLUSTER_TIMEOUT="${CLUSTER_TIMEOUT:-900}" REPLICATION_TIMEOUT="${REPLICATION_TIMEOUT:-300}"
  fi
  ENGINE="$(lab_engine)" NODE1_NAME="${NODES[0]}" NODE2_NAME="${NODES[1]}" \
    bash "${LAB_DIR}/run-tests.sh"
}

cmd_destroy() {
  local node remaining
  for node in "${NODES[@]}"; do
    limactl delete --force "${node}" 2>/dev/null || true
    limactl disk delete --force "${node}-data" 2>/dev/null || true
  done
  rm -f "${ENGINE_FILE}"
  if ! remaining="$(limactl list --quiet)"; then
    echo "ERROR: cannot list the VMs" >&2
    return 1
  fi
  for node in "${NODES[@]}"; do
    if grep -qx "${node}" <<< "${remaining}"; then
      echo "ERROR: cannot delete ${node}" >&2
      return 1
    fi
  done
}

cmd_matrix() {
  # Each combination rebuilds the lab from scratch. The summary goes to
  # summary.log, next to the logs of each combination.
  local skip_emulated=0 log_dir status=0
  if [[ "${1:-}" == "--skip-emulated" ]]; then
    skip_emulated=1
    shift
  fi
  log_dir="${1:-${LAB_DIR}/matrix-logs}"
  mkdir -p "${log_dir}"
  # Do not leave VMs behind when interrupted
  trap 'cmd_destroy > /dev/null 2>&1' EXIT
  if ! matrix_run "${log_dir}" "${skip_emulated}" | tee "${log_dir}/summary.log"; then
    status=1
  fi
  if ! cmd_destroy > /dev/null 2>&1; then
    echo "ERROR: cannot delete the VMs of the last combination" | tee -a "${log_dir}/summary.log" >&2
    status=1
  fi
  trap - EXIT
  return "${status}"
}

matrix_run() {
  local log_dir="$1" skip_emulated="$2" os engine name status=0
  for os in alma9 debian13; do
    for engine in mariadb mysql; do
      name="${engine}-${os}"
      echo "=== ${engine} on ${os}"
      if ((skip_emulated)) && needs_emulation "${engine}" "${os}"; then
        echo "  SKIP: needs x86_64 emulation on this $(uname -m) host"
        continue
      fi
      if ! cmd_destroy > /dev/null 2>&1; then
        echo "  FAIL: cannot delete the VMs of the previous combination"
        return 1
      fi
      if ! "${LAB_DIR}/lab.sh" up "${engine}" "${os}" > "${log_dir}/up-${name}.log" 2>&1; then
        echo "  FAIL: the lab did not build, see up-${name}.log"
        status=1
        continue
      fi
      if ! "${LAB_DIR}/lab.sh" test > "${log_dir}/test-${name}.log" 2>&1; then
        status=1
      fi
      grep -E '  (PASS|FAIL):|All scenarios|check\(s\) failed' "${log_dir}/test-${name}.log" || true
      if ! grep -qE 'All scenarios|check\(s\) failed' "${log_dir}/test-${name}.log"; then
        echo "  FAIL: the tests aborted, see test-${name}.log"
      fi
    done
  done
  return "${status}"
}

main() {
  local command="${1:-}"
  shift || true
  case "${command}" in
    up)
      cmd_create "${2:-}" "${1:-}"
      cmd_provision "${1:-}"
      cmd_deploy
      cmd_sync
      cmd_cluster
      ;;
    create) cmd_create "$@" ;;
    provision) cmd_provision "$@" ;;
    deploy) cmd_deploy ;;
    sync) cmd_sync ;;
    cluster) cmd_cluster ;;
    test) cmd_test ;;
    status) node_run "${NODES[0]}" pcs status ;;
    shell) limactl shell --workdir / "${1:?node}" sudo -i ;;
    destroy) cmd_destroy ;;
    matrix) cmd_matrix "$@" ;;
    -h|--help|"") usage ;;
    *)
      usage >&2
      exit 1
      ;;
  esac
}

main "$@"
