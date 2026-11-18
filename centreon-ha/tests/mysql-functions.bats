#!/usr/bin/env bats

load helpers

# sql_get_option

@test "sql_get_option: the last occurrence wins" {
  load_mysql_functions mysqld "" ""
  run sql_get_option datadir $'--datadir=/a\n--datadir=/b'
  [ "$status" -eq 0 ]
  [ "$output" = "/b" ]
}

@test "sql_get_option: - and _ are interchangeable" {
  load_mysql_functions mysqld "" ""
  run sql_get_option log-bin $'--log_bin=mysql-bin'
  [ "$status" -eq 0 ]
  [ "$output" = "mysql-bin" ]
}

@test "sql_get_option: accepts the loose- prefix" {
  load_mysql_functions mysqld "" ""
  run sql_get_option relay-log $'--loose-relay-log=relay'
  [ "$status" -eq 0 ]
  [ "$output" = "relay" ]
}

@test "sql_get_option: a valueless option prints an empty value" {
  load_mysql_functions mysqld "" ""
  run sql_get_option log-bin $'--log-bin'
  [ "$status" -eq 0 ]
  [ "$output" = "" ]
}

@test "sql_get_option: keeps the = in values" {
  load_mysql_functions mysqld "" ""
  run sql_get_option init-connect $'--init-connect=SET a=1'
  [ "$status" -eq 0 ]
  [ "$output" = "SET a=1" ]
}

@test "sql_get_option: does not match a longer option name" {
  load_mysql_functions mysqld "" ""
  run sql_get_option log-bin $'--log-bin-index=/x/index'
  [ "$status" -eq 1 ]
}

# sql_fetch_db_config

@test "sql_fetch_db_config: MariaDB without log-bin has no binary logs" {
  load_mysql_functions mariadbd "--datadir=@DATADIR@" ""
  fetch_db_config
  [ "$SQL_CONF_DATADIR" = "$DATADIR" ]
  [ "$SQL_CONF_LOG_BIN_ARG" = "" ]
  [ "$SQL_IS_RUNNING" -eq 0 ]
}

@test "sql_fetch_db_config: MariaDB valueless log-bin is named after the pid file" {
  load_mysql_functions mariadbd "--datadir=@DATADIR@ --log-bin --pid-file=/run/mariadb/db1.pid" ""
  fetch_db_config
  [ "$SQL_CONF_PID_FILE" = "db1" ]
  [ "$SQL_CONF_LOG_BIN_ARG" = "--log-bin" ]
  [ "$SQL_CONF_LOG_BIN" = "$DATADIR/db1-bin" ]
  [ "$SQL_CONF_RELAY_LOG" = "$DATADIR/db1-relay-bin" ]
}

@test "sql_fetch_db_config: MySQL logs to binlog by default" {
  load_mysql_functions mysqld "--datadir=@DATADIR@" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN_ARG" = "--log-bin" ]
  [ "$SQL_CONF_LOG_BIN" = "$DATADIR/binlog" ]
  [ "$SQL_CONF_RELAY_LOG" = "$DATADIR/node1-relay-bin" ]
}

@test "sql_fetch_db_config: MySQL valueless log-bin is named after the host, not the pid file" {
  load_mysql_functions mysqld "--datadir=@DATADIR@ --log-bin --pid-file=/run/mysqld/db1.pid" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN_ARG" = "--log-bin" ]
  [ "$SQL_CONF_LOG_BIN" = "$DATADIR/node1-bin" ]
  [ "$SQL_CONF_RELAY_LOG" = "$DATADIR/node1-relay-bin" ]
}

@test "sql_fetch_db_config: MySQL empty log-bin is named after the host" {
  load_mysql_functions mysqld "--datadir=@DATADIR@ --log-bin=" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN_ARG" = "--log-bin" ]
  [ "$SQL_CONF_LOG_BIN" = "$DATADIR/node1-bin" ]
}

@test "sql_fetch_db_config: MariaDB log-basename names the logs" {
  load_mysql_functions mariadbd "--datadir=@DATADIR@ --log-basename=centreon --log-bin --pid-file=/run/mariadb/db1.pid" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN" = "$DATADIR/centreon-bin" ]
  [ "$SQL_CONF_RELAY_LOG" = "$DATADIR/centreon-relay-bin" ]
}

@test "sql_fetch_db_config: a later skip-log-bin disables binary logging" {
  load_mysql_functions mysqld "--datadir=@DATADIR@ --log-bin=/logs/mysql-bin" "--skip-log-bin"
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN_ARG" = "" ]
  [ "$SQL_CONF_LOG_BIN" = "" ]
}

@test "sql_fetch_db_config: a later loose-disable-log-bin disables binary logging" {
  load_mysql_functions mysqld "--datadir=@DATADIR@ --log-bin=/logs/mysql-bin --loose-disable-log-bin" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN_ARG" = "" ]
}

@test "sql_fetch_db_config: a later log-bin enables binary logging again" {
  load_mysql_functions mysqld "--datadir=@DATADIR@ --skip-log-bin --log-bin=/logs/mysql-bin" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN_ARG" = "--log-bin" ]
  [ "$SQL_CONF_LOG_BIN" = "/logs/mysql-bin" ]
}

@test "sql_fetch_db_config: resolves relative log paths against the datadir" {
  load_mysql_functions mysqld "--datadir=@DATADIR@ --log-bin=logs/mysql-bin --relay-log=logs/relay" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN" = "$DATADIR/logs/mysql-bin" ]
  [ "$SQL_CONF_RELAY_LOG" = "$DATADIR/logs/relay" ]
}

@test "sql_fetch_db_config: puts log names without a directory in the datadir" {
  load_mysql_functions mariadbd "--datadir=@DATADIR@ --log-bin=mysql-bin --relay-log=relay" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN" = "$DATADIR/mysql-bin" ]
  [ "$SQL_CONF_RELAY_LOG" = "$DATADIR/relay" ]
}

@test "sql_fetch_db_config: keeps absolute log paths" {
  load_mysql_functions mysqld "--datadir=@DATADIR@ --log-bin=/logs/mysql-bin --relay-log=/logs/relay" ""
  fetch_db_config
  [ "$SQL_CONF_LOG_BIN" = "/logs/mysql-bin" ]
  [ "$SQL_CONF_RELAY_LOG" = "/logs/relay" ]
}

@test "sql_fetch_db_config: resolves a symlinked datadir" {
  load_mysql_functions mariadbd "--datadir=$BATS_TEST_TMPDIR/datadir-link" ""
  ln -s "$DATADIR" "$BATS_TEST_TMPDIR/datadir-link"
  fetch_db_config
  [ "$SQL_CONF_DATADIR" = "$DATADIR" ]
}

@test "sql_fetch_db_config: the arguments of the running server win over the option files" {
  load_mysql_functions mariadbd "--datadir=/nonexistent --log-bin=mysql-bin --relay-log=relay" "--datadir=@DATADIR@ --log-bin=/logs/other-bin"
  fetch_db_config
  [ "$SQL_IS_RUNNING" -eq 1 ]
  [ "$SQL_CONF_DATADIR" = "$DATADIR" ]
  [ "$SQL_CONF_LOG_BIN" = "/logs/other-bin" ]
  [ "$SQL_CONF_RELAY_LOG" = "$DATADIR/relay" ]
}

@test "sql_fetch_db_config: passes --defaults-file of the running server first" {
  load_mysql_functions mariadbd "" "--defaults-file=/etc/custom.cnf"
  cat > "$STUB_DIR/mariadbd" <<EOF
#!/bin/bash
[ "\$1" = "--defaults-file=/etc/custom.cnf" ] && [ "\$2" = "--print-defaults" ] || exit 1
echo "--datadir=$DATADIR"
EOF
  fetch_db_config
  [ "$SQL_CONF_DEFAULTS_FILE" = "/etc/custom.cnf" ]
  [ "$SQL_CONF_DATADIR" = "$DATADIR" ]
}

@test "sql_fetch_db_config: fails when the option files cannot be read" {
  load_mysql_functions mysqld "" ""
  printf '#!/bin/bash\nexit 1\n' > "$STUB_DIR/mysqld"
  run sql_fetch_db_config
  [ "$status" -eq 1 ]
  [[ "$output" == *"Cannot read the option files"* ]]
}

# sql_fetch_db_binary

@test "sql_fetch_db_binary: prefers mariadbd, as MariaDB installs a mysqld symlink" {
  load_mysql_functions mysqld "" ""
  printf '#!/bin/bash\n' > "$STUB_DIR/mariadbd"
  chmod +x "$STUB_DIR/mariadbd"
  SQL_DB_BINARY=
  SQL_IS_MARIADB=0
  run_without_errexit sql_fetch_db_binary
  [ "$SQL_DB_BINARY" = "mariadbd" ]
  [ "$SQL_DB_BINARY_PATH" = "$STUB_DIR/mariadbd" ]
  [ "$SQL_IS_MARIADB" -eq 1 ]
}

@test "sql_fetch_db_binary: falls back to mysqld" {
  if command -v mariadbd >/dev/null || [ -x /usr/sbin/mariadbd ] || [ -x /usr/libexec/mariadbd ]; then
    skip "MariaDB is installed on this host"
  fi
  load_mysql_functions mysqld "" ""
  SQL_DB_BINARY=
  SQL_IS_MARIADB=1
  run_without_errexit sql_fetch_db_binary
  [ "$SQL_DB_BINARY" = "mysqld" ]
  [ "$SQL_DB_BINARY_PATH" = "$STUB_DIR/mysqld" ]
  [ "$SQL_IS_MARIADB" -eq 0 ]
}

# sql_fetch_server_engine

@test "sql_fetch_server_engine: detects MariaDB and MySQL from the server version" {
  load_mysql_functions mysqld "" ""
  cat > "$STUB_DIR/mysql" <<'EOF'
#!/bin/bash
case "$*" in
  *db1*) echo "10.11.6-MariaDB-log" ;;
  *db2*) echo "8.4.3" ;;
  *) exit 1 ;;
esac
EOF
  chmod +x "$STUB_DIR/mysql"
  sql_fetch_server_engine db1
  [ "$SQL_IS_MARIADB" -eq 1 ]
  sql_fetch_server_engine db2
  [ "$SQL_IS_MARIADB" -eq 0 ]
  run sql_fetch_server_engine db3
  [ "$status" -eq 1 ]
}

# sql_fetch_systemd_service

stub_systemctl() {
  # list-unit-files output for the units given as "name state" arguments
  local unit
  {
    echo '#!/bin/bash'
    echo 'case "$*" in'
    for unit in "$@"; do
      echo "  *\" ${unit%% *}.service\") echo \"${unit%% *}.service ${unit#* } disabled\" ;;"
    done
    echo 'esac'
  } > "$STUB_DIR/systemctl"
  chmod +x "$STUB_DIR/systemctl"
}

@test "sql_fetch_systemd_service: prefers mariadb, then mysqld, then mysql" {
  load_mysql_functions mysqld "" ""
  stub_systemctl "mysql enabled" "mysqld disabled"
  run_without_errexit sql_fetch_systemd_service
  [ "$SQL_SYSTEMD_SERVICE" = "mysqld" ]
}

@test "sql_fetch_systemd_service: skips masked units" {
  load_mysql_functions mysqld "" ""
  stub_systemctl "mariadb masked" "mysqld masked-runtime" "mysql disabled"
  run_without_errexit sql_fetch_systemd_service
  [ "$SQL_SYSTEMD_SERVICE" = "mysql" ]
}

@test "sql_fetch_systemd_service: fails without a database unit" {
  load_mysql_functions mysqld "" ""
  stub_systemctl "httpd enabled"
  run sql_fetch_systemd_service
  [ "$status" -eq 1 ]
}
