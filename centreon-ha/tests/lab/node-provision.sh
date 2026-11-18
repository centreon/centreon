#!/usr/bin/env bash
#
# Provision a lab node, as root: database engine on an LVM datadir,
# Pacemaker, and the accounts used by centreon-ha.
# Environment: ENGINE (mariadb or mysql), NODE_ID, NODE_NAME, NODE1_NAME,
# NODE1_IP, NODE2_NAME, NODE2_IP.

set -euo pipefail

: "${ENGINE:?}" "${NODE_ID:?}" "${NODE_NAME:?}"
: "${NODE1_NAME:?}" "${NODE1_IP:?}" "${NODE2_NAME:?}" "${NODE2_IP:?}"

# Lab credentials: the VMs are local and disposable
REPL_USER="centreon-repl"
REPL_PASSWORD="Lab-Repl-2026"
HACLUSTER_PASSWORD="Lab-Hacluster-2026"
MARIADB_VERSION="11.8"
DATADIR="/var/lib/mysql"
MYSQL_HOME="/home/mysql"
VG_NAME="vg_data"
LV_SIZE="4G"

# shellcheck source=/dev/null
. /etc/os-release
if [[ "${ID}" == "debian" ]]; then
  OS_FAMILY="debian"
else
  OS_FAMILY="el"
fi

# The paths of the Centreon HA installation guide for each distribution
if [[ "${ENGINE}" == "mysql" ]]; then
  DB_BINARY="/usr/sbin/mysqld"
  if [[ "${OS_FAMILY}" == "debian" ]]; then
    DB_SERVICE="mysql"
    DB_CONFIG="/etc/mysql/mysql.conf.d/centreon-ha.cnf"
  else
    DB_SERVICE="mysqld"
    DB_CONFIG="/etc/my.cnf.d/server.cnf"
  fi
else
  DB_BINARY="/usr/sbin/mariadbd"
  DB_SERVICE="mariadb"
  if [[ "${OS_FAMILY}" == "debian" ]]; then
    # After 50-server.cnf, to override its bind-address
    DB_CONFIG="/etc/mysql/mariadb.conf.d/60-centreon-ha.cnf"
  else
    DB_CONFIG="/etc/my.cnf.d/server.cnf"
  fi
fi
if [[ "${OS_FAMILY}" == "debian" ]]; then
  PID_FILE="/run/mysqld/mysqld.pid"
  SOCKET="/run/mysqld/mysqld.sock"
else
  PID_FILE="${DATADIR}/mysql.pid"
  SOCKET="${DATADIR}/mysql.sock"
fi

setup_system() {
  hostnamectl set-hostname "${NODE_NAME}"
  sed -i '/# centreon-ha lab$/d' /etc/hosts
  printf '%s %s # centreon-ha lab\n' "${NODE1_IP}" "${NODE1_NAME}" "${NODE2_IP}" "${NODE2_NAME}" >> /etc/hosts
  if [[ "${OS_FAMILY}" == "debian" ]]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y pacemaker pcs corosync lvm2 rsync bc perl tar sudo openssh-server xfsprogs curl gnupg ca-certificates
    # The packages start a one-node cluster: remove it for the lab cluster
    systemctl stop pacemaker corosync || true
    pcs cluster destroy --force > /dev/null 2>&1 || true
    if [[ -e /etc/corosync/corosync.conf ]]; then
      echo "ERROR: cannot remove the default cluster of the packages" >&2
      exit 1
    fi
  else
    # The lab does not set up SELinux policies for the datadir and the cluster
    setenforce 0 || true
    sed -i 's/^SELINUX=enforcing/SELINUX=permissive/' /etc/selinux/config
    systemctl disable --now firewalld 2>/dev/null || true
    dnf install -y dnf-plugins-core
    dnf config-manager --set-enabled highavailability
    dnf install -y pacemaker pcs lvm2 rsync bc perl tar sudo openssh-clients openssh-server xfsprogs
  fi
}

setup_datadir() {
  local disk="" name
  if mountpoint -q "${DATADIR}"; then
    return
  fi
  # The lab disk is the only unmounted disk without partitions
  for name in $(lsblk -dpno NAME,TYPE | awk '$2 == "disk" { print $1 }'); do
    if [[ -z "$(lsblk -no MOUNTPOINT "${name}" | tr -d '[:space:]')" ]] \
      && [[ "$(lsblk -no NAME "${name}" | wc -l)" -eq 1 ]]; then
      disk="${name}"
      break
    fi
  done
  if [[ -z "${disk}" ]]; then
    echo "ERROR: no free disk for the datadir" >&2
    exit 1
  fi
  pvcreate -y "${disk}"
  vgcreate "${VG_NAME}" "${disk}"
  lvcreate -y -L "${LV_SIZE}" -n lv_mysql "${VG_NAME}"
  mkfs.xfs -q "/dev/${VG_NAME}/lv_mysql"
  install -d -m 755 "${DATADIR}"
  echo "/dev/${VG_NAME}/lv_mysql ${DATADIR} xfs defaults 0 0" >> /etc/fstab
  systemctl daemon-reload
  mount "${DATADIR}"
}

centreon_tuning() {
  cat <<'EOF'

# Tuning standard Centreon
innodb_file_per_table=1
open_files_limit=32000
key_buffer_size=256M
sort_buffer_size=32M
join_buffer_size=4M
thread_cache_size=64
read_buffer_size=512K
read_rnd_buffer_size=256K
max_allowed_packet=64M
EOF
}

install_mariadb() {
  # As the Centreon installation documentation does
  if [[ "${OS_FAMILY}" == "debian" ]]; then
    curl -fLsS https://r.mariadb.com/downloads/mariadb_repo_setup \
      | bash -s -- --os-type=debian --os-version="${VERSION_ID}" --mariadb-server-version="mariadb-${MARIADB_VERSION}" --skip-maxscale
    apt-get install -y mariadb-server mariadb-client
  else
    curl -fLsS https://r.mariadb.com/downloads/mariadb_repo_setup \
      | bash -s -- --os-type=rhel --os-version="${VERSION_ID%%.*}" --mariadb-server-version="mariadb-${MARIADB_VERSION}" --skip-maxscale
    dnf install -y MariaDB-server MariaDB-client
  fi
  # The configuration of the Centreon HA installation guide; read_only is
  # enabled once the accounts exist
  cat > "${DB_CONFIG}" <<EOF
[server]
server-id=${NODE_ID}
#read_only
bind-address=0.0.0.0
log-bin=mysql-bin
binlog-do-db=centreon
binlog-do-db=centreon_storage
innodb_flush_log_at_trx_commit=1
sync_binlog=1
binlog_format=MIXED
slave_compressed_protocol=1
slave_parallel_mode=conservative
datadir=${DATADIR}
pid-file=${PID_FILE}
socket=${SOCKET}
skip-slave-start
log-slave-updates
gtid_strict_mode=ON
expire_logs_days=7
ignore-db-dir=lost+found
$(centreon_tuning)

[client]
socket=${SOCKET}
EOF
  if [[ ! -d "${DATADIR}/mysql" ]]; then
    mariadb-install-db --user=mysql --datadir="${DATADIR}" > /dev/null
  fi
}

install_mysql() {
  # As the Centreon installation documentation does: the MySQL repository,
  # and my.cnf.d included from my.cnf, which this repository does not do
  if [[ "${OS_FAMILY}" == "debian" ]]; then
    local mysql_apt_config="mysql-apt-config_0.8.36-1_all.deb" mysql_keyring
    (cd /tmp && curl -fJLO "https://dev.mysql.com/get/${mysql_apt_config}")
    echo "mysql-apt-config mysql-apt-config/select-server select mysql-8.4-lts" | debconf-set-selections
    apt-get install -y "/tmp/${mysql_apt_config}"
    mysql_keyring=$(grep -ohm1 'signed-by=[^] ]*' /etc/apt/sources.list.d/mysql.list 2>/dev/null | cut -d= -f2 || true)
    [[ -n "${mysql_keyring}" ]] || mysql_keyring=/etc/apt/trusted.gpg.d/mysql.gpg
    curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | gpg --dearmor --yes -o "${mysql_keyring}"
    apt-get update
    apt-get install -y mysql-server
  else
    dnf install -y "https://dev.mysql.com/get/mysql84-community-release-el${VERSION_ID%%.*}-1.noarch.rpm"
    # The repository still points to the 2023 key file: replace the expired key
    curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 -o /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2023
    rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2023
    dnf install -y mysql-server mysql
    grep -q '^!includedir /etc/my.cnf.d' /etc/my.cnf || echo '!includedir /etc/my.cnf.d' >> /etc/my.cnf
    sed -Ei 's/LimitNOFILE\s*=\s*[0-9]+/LimitNOFILE = 32000/' /usr/lib/systemd/system/mysqld.service
    systemctl daemon-reload
  fi
  # The installation guide adapted to MySQL: its MariaDB-only options
  # (gtid_strict_mode, slave_parallel_mode) replaced by the MySQL settings
  # that centreon-ha requires
  cat > "${DB_CONFIG}" <<EOF
[mysqld]
server-id=${NODE_ID}
#read_only
log-bin=mysql-bin
binlog-do-db=centreon
binlog-do-db=centreon_storage
innodb_flush_log_at_trx_commit=1
sync_binlog=1
datadir=${DATADIR}
pid-file=${PID_FILE}
socket=${SOCKET}
skip_replica_start=ON
log_replica_updates=ON
gtid_mode=ON
enforce_gtid_consistency=ON
binlog_expire_logs_seconds=604800
plugin-load-add=semisync_source.so
plugin-load-add=semisync_replica.so
$(centreon_tuning)

[client]
socket=${SOCKET}
EOF
  if [[ ! -d "${DATADIR}/mysql" ]]; then
    mysqld --initialize-insecure --user=mysql --datadir="${DATADIR}"
  fi
}

setup_database() {
  chown mysql:mysql "${DATADIR}"
  systemctl enable --now "${DB_SERVICE}"
  # The anonymous accounts of mariadb-install-db, which the guide removes
  # with mysql_secure_installation, would take over the replication
  # account for local connections
  mysql -u root -N -e "SELECT CONCAT(QUOTE(User), '@', QUOTE(Host)) FROM mysql.user WHERE User = ''" \
    | while read -r account; do
      mysql -u root -e "SET sql_log_bin = 0; DROP USER ${account}"
    done
  # The replication account of the guide, for both nodes. MySQL has no
  # READ_ONLY ADMIN, and its replication statements need dynamic privileges.
  local privileges host
  if [[ "${ENGINE}" == "mysql" ]]; then
    privileges="SUPER, SHUTDOWN, PROCESS, RELOAD, SELECT, REPLICATION CLIENT, REPLICATION SLAVE, SYSTEM_VARIABLES_ADMIN, REPLICATION_SLAVE_ADMIN, CONNECTION_ADMIN"
  else
    # The guide's list, plus the replication administration privileges that
    # SUPER no longer includes since MariaDB 11.0
    privileges="READ_ONLY ADMIN, SHUTDOWN, PROCESS, RELOAD, SUPER, SELECT, REPLICATION CLIENT, REPLICATION SLAVE, REPLICATION SLAVE ADMIN, REPLICATION MASTER ADMIN, BINLOG ADMIN, SLAVE MONITOR"
  fi
  # Without binary logging: the accounts exist on both nodes, the
  # databases come from node 1 through mysql-sync-bigdb.sh
  for host in localhost "${NODE1_IP}" "${NODE2_IP}"; do
    mysql -u root <<EOF
SET sql_log_bin = 0;
CREATE USER IF NOT EXISTS '${REPL_USER}'@'${host}' IDENTIFIED BY '${REPL_PASSWORD}';
GRANT ${privileges} ON *.* TO '${REPL_USER}'@'${host}';
EOF
  done
  if [[ "${NODE_ID}" -eq 1 ]]; then
    # Centreon writes with its database as default database, which
    # binlog-do-db requires to log the statements
    mysql -u root <<'EOF'
CREATE DATABASE IF NOT EXISTS centreon;
USE centreon;
CREATE TABLE IF NOT EXISTS host (host_id INT PRIMARY KEY, host_name VARCHAR(255));
INSERT IGNORE INTO host VALUES (1, 'lab-host');
CREATE DATABASE IF NOT EXISTS centreon_storage;
USE centreon_storage;
CREATE TABLE IF NOT EXISTS data_bin (id_metric INT, ctime INT, value FLOAT)
  PARTITION BY RANGE (ctime) (PARTITION p1 VALUES LESS THAN (1000), PARTITION pmax VALUES LESS THAN MAXVALUE);
CREATE TABLE IF NOT EXISTS logs (log_id INT, ctime INT, output VARCHAR(255))
  PARTITION BY RANGE (ctime) (PARTITION p1 VALUES LESS THAN (1000), PARTITION pmax VALUES LESS THAN MAXVALUE);
EOF
  fi
  # As in the guide, both nodes start read-only: mysql-sync-bigdb.sh makes
  # the primary writable
  sed -i 's/^#read_only$/read_only/' "${DB_CONFIG}"
  # Pacemaker starts the database
  systemctl stop "${DB_SERVICE}"
  systemctl disable "${DB_SERVICE}"
}

setup_mysql_user() {
  # mysql-sync-bigdb.sh runs ssh and rsync as mysql between the nodes: as in
  # the guide, give mysql a home directory and a shell. usermod refuses to
  # change a user with running processes.
  systemctl stop "${DB_SERVICE}" 2>/dev/null || true
  install -d -o mysql -g mysql -m 700 "${MYSQL_HOME}"
  usermod -d "${MYSQL_HOME}" -s /bin/bash mysql
}

setup_accounts() {
  install -d -o mysql -g mysql -m 700 "${MYSQL_HOME}/.ssh"
  if [[ ! -f "${MYSQL_HOME}/.ssh/id_ed25519" ]]; then
    sudo -u mysql ssh-keygen -q -t ed25519 -a 100 -N '' -f "${MYSQL_HOME}/.ssh/id_ed25519"
  fi
  if [[ "${OS_FAMILY}" == "debian" ]]; then
    systemctl enable --now ssh
  else
    systemctl enable --now sshd
  fi
  echo "hacluster:${HACLUSTER_PASSWORD}" | chpasswd
  systemctl enable --now pcsd
}

write_lab_env() {
  cat > /etc/centreon-ha-lab.env <<EOF
ENGINE=${ENGINE}
DB_SERVICE=${DB_SERVICE}
DB_BINARY=${DB_BINARY}
DB_CONFIG=${DB_CONFIG}
PID_FILE=${PID_FILE}
SOCKET=${SOCKET}
DATADIR=${DATADIR}
MYSQL_HOME=${MYSQL_HOME}
REPL_USER=${REPL_USER}
REPL_PASSWORD=${REPL_PASSWORD}
HACLUSTER_PASSWORD=${HACLUSTER_PASSWORD}
EOF
}

setup_system
setup_datadir
if [[ "${ENGINE}" == "mysql" ]]; then
  install_mysql
else
  install_mariadb
fi
setup_mysql_user
setup_database
setup_accounts
write_lab_env
echo "${NODE_NAME} provisioned with ${ENGINE}"
