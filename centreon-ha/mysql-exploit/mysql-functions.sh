#!/bin/bash

##################################################################
# bash common functions for mysql replication management scripts #
##################################################################

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

