#!/bin/bash

##################################################################
# bash common functions for mysql replication management scripts #
##################################################################

# Server process name (mariadbd or mysqld), as matched by "ps -C"
SQL_DB_BINARY=
# Full path of the server binary
SQL_DB_BINARY_PATH=
SQL_IS_MARIADB=0
SQL_SYSTEMD_SERVICE=
SQL_IS_RUNNING=0

SQL_CONF_DATADIR=
SQL_CONF_DEFAULTS_FILE=
# "--log-bin" when binary logging is enabled, empty otherwise
SQL_CONF_LOG_BIN_ARG=
# Absolute base path of the binary logs
SQL_CONF_LOG_BIN=
# Base name of the pid file, without directory or extension
SQL_CONF_PID_FILE=
# Absolute base path of the relay logs
SQL_CONF_RELAY_LOG=

sql_fetch_clients()
{
    # MariaDB warns when its clients are called by their mysql* names, which
    # MySQL alone ships: call them by the names of the installed engine
    if command -v mariadb > /dev/null 2>&1 ; then
        SQL_CLIENT="mariadb"
        SQL_ADMIN="mariadb-admin"
        SQL_SHOW="mariadb-show"
    else
        SQL_CLIENT="mysql"
        SQL_ADMIN="mysqladmin"
        SQL_SHOW="mysqlshow"
    fi
}

sql_fetch_db_binary()
{
    if [ -n "$SQL_DB_BINARY" ] ; then
        return 0
    fi

    # order is important as mariadb installs a mysqld symlink
    local _test_binary _test_path _test_dir
    for _test_binary in mariadbd mysqld; do
        _test_path=$(command -v "$_test_binary" 2>/dev/null)
        if [ -z "$_test_path" ] ; then
            # /usr/sbin can be out of PATH (Debian non-root users, some
            # non-login environments), and the EL distribution packages
            # install the server in /usr/libexec
            for _test_dir in /usr/sbin /usr/libexec; do
                if [ -x "$_test_dir/$_test_binary" ] ; then
                    _test_path="$_test_dir/$_test_binary"
                    break
                fi
            done
        fi
        if [ -n "$_test_path" ] ; then
            SQL_DB_BINARY="$_test_binary"
            SQL_DB_BINARY_PATH="$_test_path"
            if [ "$_test_binary" = "mariadbd" ] ; then
                SQL_IS_MARIADB=1
            else
                SQL_IS_MARIADB=0
            fi
            return 0
        fi
    done

    return 1
}

sql_fetch_systemd_service()
{
    if [ -n "$SQL_SYSTEMD_SERVICE" ] ; then
        return 0
    fi

    # order is important as mariadb installs mysql and mysqld aliases;
    # MySQL is mysqld on EL and mysql on Debian
    local _test_service
    for _test_service in mariadb mysqld mysql; do
        if systemctl list-unit-files --no-legend "$_test_service.service" 2>/dev/null | awk -v unit="$_test_service.service" '$1 == unit && $2 !~ /^masked/ { found = 1 } END { exit !found }'; then
            SQL_SYSTEMD_SERVICE="$_test_service"
            return 0
        fi
    done

    return 1
}

sql_fetch_db_running()
{
    sql_fetch_db_binary

    if [ -z "$SQL_DB_BINARY" ] ; then
        return 1
    fi

    local _process
    _process=$(ps -o args --no-headers -C "${SQL_DB_BINARY}" | head -1)

    if [ -n "$_process" ] ; then
        SQL_IS_RUNNING=1
    else
        SQL_IS_RUNNING=0
    fi
}

sql_fetch_server_engine()
{
    # Set SQL_IS_MARIADB from the version of the server on host $1. Return 1
    # when the server cannot be queried.
    local _version
    _version=$("$SQL_CLIENT" -N -B --connect-timeout=5 -u "$DBROOTUSER" -h "$1" "-p$DBROOTPASSWORD" -e 'SELECT VERSION()' 2>/dev/null)
    if [ -z "$_version" ] ; then
        return 1
    fi
    case "$_version" in
        *MariaDB*) SQL_IS_MARIADB=1 ;;
        *) SQL_IS_MARIADB=0 ;;
    esac
    return 0
}

sql_get_option()
{
    # Print the value of the last occurrence of option $1 (without the
    # leading dashes) in $2, which holds one argument per line. "-" and "_"
    # are interchangeable in option names, and the "loose-" prefix is
    # accepted. Return 1 when the option is absent; a valueless option
    # (--log-bin) prints an empty value.
    local _pattern _line
    _pattern="${1//[-_]/[-_]}"
    _line=$(echo "$2" | grep -E "^--(loose[-_])?${_pattern}(=|$)" | tail -1)
    if [ -z "$_line" ] ; then
        return 1
    fi
    case "$_line" in
        *=*) echo "${_line#*=}" ;;
    esac
    return 0
}

sql_fetch_db_config()
{
    sql_fetch_db_binary

    if [ -z "$SQL_DB_BINARY" ] ; then
        return 1
    fi

    local _process _process_args _options _value _default_basename _defaults_args
    _process=$(ps -o args --no-headers -C "${SQL_DB_BINARY}" | head -1)

    if [ -n "$_process" ] ; then
        SQL_IS_RUNNING=1
    else
        SQL_IS_RUNNING=0
    fi
    _process_args=$(echo "$_process" | awk '{ for (i = 2; i <= NF; i++) { print $i } }')

    # --defaults-file must come first
    _defaults_args=()
    SQL_CONF_DEFAULTS_FILE=$(sql_get_option defaults-file "$_process_args")
    if [ -n "$SQL_CONF_DEFAULTS_FILE" ] ; then
        _defaults_args=("--defaults-file=$SQL_CONF_DEFAULTS_FILE")
    fi
    if ! _options=$("$SQL_DB_BINARY_PATH" "${_defaults_args[@]}" --print-defaults) ; then
        echo "ERROR: Cannot read the option files with '$SQL_DB_BINARY_PATH --print-defaults'." >&2
        return 1
    fi
    _options=$(echo "$_options" | grep -v "would have been started with the following argument" | awk '{ for (i = 1; i <= NF; i++) { print $i } }')
    # The arguments of the running server come last, as they take precedence
    # over the option files
    _options=$(printf '%s\n%s\n' "$_options" "$_process_args")

    SQL_CONF_DATADIR=$(sql_get_option datadir "$_options")
    if [ -z "$SQL_CONF_DATADIR" ] ; then
        # default of the MariaDB and MySQL packages
        SQL_CONF_DATADIR="/var/lib/mysql"
    fi

    # Resolve symlinks: callers match the datadir against its mount point
    if ! SQL_CONF_DATADIR=$(realpath "$SQL_CONF_DATADIR") ; then
        echo "ERROR: Cannot resolve the datadir." >&2
        return 1
    fi

    SQL_CONF_PID_FILE=$(sql_get_option pid-file "$_options")
    if [ -z "$SQL_CONF_PID_FILE" ] ; then
        SQL_CONF_PID_FILE=$(hostname -s)
    else
        SQL_CONF_PID_FILE=$(basename "$SQL_CONF_PID_FILE" | cut -d '.' -f 1)
    fi

    # Default base name of the binary and relay logs when log-bin or
    # relay-log has no value: MariaDB derives it from log-basename or the pid
    # file name, MySQL from the host name
    if [ "$SQL_IS_MARIADB" -eq 1 ] ; then
        _default_basename=$(sql_get_option log-basename "$_options")
        if [ -z "$_default_basename" ] ; then
            _default_basename="$SQL_CONF_PID_FILE"
        fi
    else
        _default_basename=$(hostname -s)
    fi

    # The last of log-bin, skip-log-bin and disable-log-bin wins
    SQL_CONF_LOG_BIN_ARG=
    SQL_CONF_LOG_BIN=
    _value=$(echo "$_options" | grep -E '^--(loose[-_])?((skip|disable)[-_])?log[-_]bin(=|$)' | tail -1)
    case "$_value" in
        --skip[-_]*|--disable[-_]*|--loose[-_]skip[-_]*|--loose[-_]disable[-_]*)
            ;;
        "")
            if [ "$SQL_IS_MARIADB" -eq 0 ] ; then
                # MySQL enables binary logging by default, as "binlog"
                SQL_CONF_LOG_BIN_ARG="--log-bin"
                SQL_CONF_LOG_BIN="$SQL_CONF_DATADIR/binlog"
            fi
            ;;
        *=?*)
            SQL_CONF_LOG_BIN_ARG="--log-bin"
            SQL_CONF_LOG_BIN="${_value#*=}"
            ;;
        *)
            SQL_CONF_LOG_BIN_ARG="--log-bin"
            SQL_CONF_LOG_BIN="$SQL_CONF_DATADIR/$_default_basename-bin"
            ;;
    esac

    SQL_CONF_RELAY_LOG=$(sql_get_option relay-log "$_options")
    if [ -z "$SQL_CONF_RELAY_LOG" ] ; then
        SQL_CONF_RELAY_LOG="$SQL_CONF_DATADIR/$_default_basename-relay-bin"
    fi

    # The server writes relative log paths under the datadir
    case "$SQL_CONF_LOG_BIN" in
        ""|/*) ;;
        *) SQL_CONF_LOG_BIN="$SQL_CONF_DATADIR/$SQL_CONF_LOG_BIN" ;;
    esac
    case "$SQL_CONF_RELAY_LOG" in
        /*) ;;
        *) SQL_CONF_RELAY_LOG="$SQL_CONF_DATADIR/$SQL_CONF_RELAY_LOG" ;;
    esac
}

get_other_db_hostname()
{
	if [ -z "$1" ] ; then
		name_current=$(hostname | awk -F\. '{ print $1 }')
	else
		name_current=$(echo "$1" | awk -F\. '{ print $1 }')
	fi
	if [ "$name_current" != "$DBHOSTNAMEMASTER" ] && [ "$name_current" != "$DBHOSTNAMESLAVE" ] ; then
		echo "Can't find other db hostname. (name='$name_current')" >&2
		exit 2
	fi
	if [ "$name_current" != "$DBHOSTNAMEMASTER" ] ; then
		echo "$DBHOSTNAMEMASTER"
		return 0
	fi
	echo "$DBHOSTNAMESLAVE"
	return 0
}

get_ip()
{
	ip=$(getent hosts "$1")
	if [ "$?" -ne 0 ] ; then
		echo "Can't resolve db hostname. (name='$1')" >&2
		exit 2
	fi
	echo $(echo "$ip" | awk '{ print $1 }')
	return 0
}

mysql_connection_test()
{
	local _output
	_output=$("$SQL_CLIENT" -f -u "$DBROOTUSER" -h "$1" "-p$DBROOTPASSWORD" 2>&1 << EOF
quit
EOF
)
	status=$?
	# The MySQL client warns about any password given on its command line:
	# not an error of the connection
	if [ -n "$_output" ] ; then
		echo "$_output" | grep -v 'Using a password on the command line interface can be insecure' >&2
	fi
	if [ $status -ne 0 ] ; then
		echo "Impossible de se connecter au serveur '$1'." >&2
		if [ $status -eq 1 ] ; then
			return 2
		fi
		exit 2
	fi
	return 0
}


# The client commands of the installed engine, for the scripts
sql_fetch_clients
