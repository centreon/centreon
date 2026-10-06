#!/bin/bash

##################################################################
# bash common functions for mysql replication management scripts #
##################################################################

SQL_DB_BINARY=
SQL_IS_MARIADB=0
SQL_SYSTEMD_SERVICE=
SQL_IS_RUNNING=0

SQL_CONF_DATADIR=
SQL_CONF_DEFAULTS_FILE=
SQL_CONF_LOG_BIN_ARG=
SQL_CONF_LOG_BIN=
SQL_CONF_PID_FILE=
SQL_CONF_RELAY_LOG=

SQL_OPTIONS=

sql_fetch_db_binary()
{
    if [ -n "$SQL_DB_BINARY" ] ; then
        return 0
    fi

    # order is important as mariadb installs a mysqld symlink
    local _test_binary
    for _test_binary in mariadbd mysqld; do
        command -v "$_test_binary" &>/dev/null
        if [ $? -eq 0 ]; then
            SQL_DB_BINARY="$_test_binary"
            if [ "$_test_binary" = "mariadbd" ] ; then
                SQL_IS_MARIADB=1
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

    # order is important as mariadb installs symlinks
    local _test_service
    for _test_service in mariadb mysql; do
        if systemctl list-unit-files "$_test_service.service" 2>/dev/null | grep -q "$_test_service.service"; then
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

sql_fetch_db_config()
{
    sql_fetch_db_binary

    if [ -z "$SQL_DB_BINARY" ] ; then
        return 1
    fi

    local _process _options
    _process=$(ps -o args --no-headers -C "${SQL_DB_BINARY}" | head -1)

    if [ -n "$_process" ] ; then
        SQL_CONF_DATADIR=$(echo "$_process" | awk '{ for (i = 1; i <= NF; i++) { if (match($i, "^--datadir=")) { print $i } } }' | awk -F\= '{ print $2 }')
        SQL_CONF_DEFAULTS_FILE=$(echo "$_process" | awk '{ for (i = 1; i <= NF; i++) { if (match($i, "^--defaults-file=")) { print $i } } }' | awk -F\= '{ print $2 }')
        SQL_CONF_LOG_BIN_ARG=$(echo "$_process" | awk '{ for (i = 1; i <= NF; i++) { if (match($i, "^--log-bin=")) { print $i } } }' | awk -F\= '{ print $1 }')
        SQL_CONF_LOG_BIN=$(echo "$_process" | awk '{ for (i = 1; i <= NF; i++) { if (match($i, "^--log-bin=")) { print $i } } }' | awk -F\= '{ print $2 }')
        SQL_CONF_PID_FILE=$(echo "$_process" | awk '{ for (i = 1; i <= NF; i++) { if (match($i, "^--pid-file=")) { print $i } } }' | awk -F\= '{ print $2 }')
        SQL_CONF_RELAY_LOG=$(echo "$_process" | awk '{ for (i = 1; i <= NF; i++) { if (match($i, "^--relay-log=")) { print $i } } }' | awk -F\= '{ print $2 }')
        SQL_IS_RUNNING=1
    else
        SQL_IS_RUNNING=0
    fi

    if [ -n "$SQL_CONF_DEFAULTS_FILE" ] ; then
        _options=$($SQL_DB_BINARY "--defaults-file=$SQL_CONF_DEFAULTS_FILE" --print-defaults | grep -v "would have been started with the following argument" | awk '{ for (i = 1; i <= NF; i++) { print $i } }')
    else
        _options=$($SQL_DB_BINARY --print-defaults | grep -v "would have been started with the following argument" | awk '{ for (i = 1; i <= NF; i++) { print $i } }')
    fi

    if [ -z "$SQL_CONF_DATADIR" ] ; then
        SQL_CONF_DATADIR=$(echo "$_options" | grep -E '^--datadir=' | tail -1 | awk -F\= '{ print $2 }')
    fi
    if [ -z "$SQL_CONF_DATADIR" ] ; then
        SQL_CONF_DATADIR="/var/lib/mysql"
    fi

    ### Avoid datadir is a symlink (get the absolute path)
    SQL_CONF_DATADIR=$(realpath "$SQL_CONF_DATADIR")

    if [ -z "$SQL_CONF_PID_FILE" ] ; then
        SQL_CONF_PID_FILE=$(echo "$_options" | grep -E '^--pid-file=' | tail -1 | awk -F\= '{ print $2 }')
    fi
    if [ -z "$SQL_CONF_PID_FILE" ] ; then
        SQL_CONF_PID_FILE=$(hostname -s)
    else
        SQL_CONF_PID_FILE=$(basename "$SQL_CONF_PID_FILE" | cut -d '.' -f 1)
    fi

    if [ -z "$SQL_CONF_LOG_BIN_ARG" ] ; then
        SQL_CONF_LOG_BIN_ARG=$(echo "$_options" | grep -E '^--log-bin=' | tail -1 | awk -F\= '{ print $1 }')
        SQL_CONF_LOG_BIN=$(echo "$_options" | grep -E '^--log-bin=' | tail -1 | awk -F\= '{ print $2 }')
    fi

    if [ -z "$SQL_CONF_LOG_BIN" ] ; then
        SQL_CONF_LOG_BIN="$SQL_CONF_DATADIR/$SQL_CONF_PID_FILE-bin"
    fi

    if [ -z "$SQL_CONF_RELAY_LOG" ] ; then
        SQL_CONF_RELAY_LOG=$(echo "$_options" | grep -E '^--relay-log=' | tail -1 | awk -F\= '{ print $2 }')
    fi

    if [ -z "$SQL_CONF_RELAY_LOG" ] ; then
        SQL_CONF_RELAY_LOG="$SQL_CONF_DATADIR/$SQL_CONF_PID_FILE-relay-bin"
    fi
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
        mysql -f -u "$DBROOTUSER" -h "$1" "-p$DBROOTPASSWORD" << EOF
quit
EOF
	status=$?
	if [ $status -ne 0 ] ; then
		echo "Impossible de se connecter au serveur '$1'." >&2
		if [ $status -eq 1 ] ; then
			return 2
		fi
		exit 2
	fi
	return 0
}

