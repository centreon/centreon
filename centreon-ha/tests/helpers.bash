# Shared setup for the centreon-ha bats tests

CENTREON_HA_DIR="$(cd "$BATS_TEST_DIRNAME/.." && pwd)"

# Load lib/mysql-functions.sh with stubs for the server binary, ps and
# hostname, so that no database is needed.
#   $1: mariadbd or mysqld
#   $2: output of --print-defaults (one string, arguments separated by spaces)
#   $3: arguments of the running server, empty when it is stopped
load_mysql_functions() {
  local binary="$1" defaults="$2" process_args="$3"

  STUB_DIR="$BATS_TEST_TMPDIR/bin"
  DATADIR="$BATS_TEST_TMPDIR/data"
  mkdir -p "$STUB_DIR" "$DATADIR"
  DATADIR="$(realpath "$DATADIR")"

  cat > "$STUB_DIR/$binary" <<EOF
#!/bin/bash
echo "$binary would have been started with the following arguments:"
echo "${defaults//@DATADIR@/$DATADIR}"
EOF
  cat > "$STUB_DIR/ps" <<EOF
#!/bin/bash
[ -n "$process_args" ] && echo "/usr/sbin/$binary ${process_args//@DATADIR@/$DATADIR}"
exit 0
EOF
  printf '#!/bin/bash\necho node1\n' > "$STUB_DIR/hostname"
  chmod +x "$STUB_DIR"/*
  PATH="$STUB_DIR:$PATH"

  # shellcheck source=../lib/mysql-functions.sh
  source "$CENTREON_HA_DIR/lib/mysql-functions.sh"
  # Skip the detection, so that a server installed on the test host is ignored
  SQL_DB_BINARY="$binary"
  SQL_DB_BINARY_PATH="$STUB_DIR/$binary"
  if [ "$binary" = "mariadbd" ]; then
    SQL_IS_MARIADB=1
  else
    SQL_IS_MARIADB=0
  fi
}

# Load the GTID helpers of the resource agent, with stubs for the OCF
# functions that mariadb-centreon-common.sh calls when it is sourced.
#   $1: 1 for MariaDB, 0 for MySQL
load_ocf_common() {
  ocf_is_true() { [ "$1" = "true" ]; }
  ocf_local_nodename() { echo node1; }
  # The scripts are not written for errexit, which bats enables
  set +e
  # shellcheck source=../ocf-scripts/mariadb-centreon-common.sh
  source "$CENTREON_HA_DIR/ocf-scripts/mariadb-centreon-common.sh"
  set -e
  _is_mariadb="$1"
}

# Run sql_fetch_db_config in the current shell, so that its variables can be
# checked, but without errexit
fetch_db_config() {
  local rc
  set +e
  sql_fetch_db_config
  rc=$?
  set -e
  return "$rc"
}
