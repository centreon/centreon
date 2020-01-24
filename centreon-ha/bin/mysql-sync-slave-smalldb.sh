#!/bin/bash

###################################################
# Centreon                                Juin 2017
#
# Permet de synchroniser et init un slave
#
###################################################

. /usr/share/centreon-ha/lib/mysql-functions.sh
. /etc/centreon-ha/mysql-resources.sh

usage()
{
echo
echo "Use : $0 <master_log_file> <master_log_pos> <dump file> [<db hostname target>]"
echo
}

cmd_line()
{
if [ $# -ne 3 ] && [ $# -ne 4 ] 
then
        usage
        exit 1
fi

MASTER_LOG_FILE=$1
MASTER_LOG_POS=$2
DUMP_FILE=$3
PARAM_DBHOSTNAME="$4"
}


insert_dump()
{
	if [ -z "$PARAM_DBHOSTNAME" ] ; then
		PARAM_DBHOSTNAME=$(get_other_db_hostname)
	fi
	get_ip "$PARAM_DBHOSTNAME" > /dev/null
	mysql -f -u "$DBROOTUSER" -h "$PARAM_DBHOSTNAME" "-p$DBROOTPASSWORD" << EOF
SET GLOBAL read_only = ON;
quit
EOF
	mysql -u "$DBROOTUSER" -h "$PARAM_DBHOSTNAME" "-p$DBROOTPASSWORD" < "$DUMP_FILE"
}

slave_init()
{
        MASTER_DBHOSTNAME=$(get_other_db_hostname "$PARAM_DBHOSTNAME")
        get_ip "$MASTER_DBHOSTNAME" > /dev/null
	mysql -f -u "$DBROOTUSER" -h "$PARAM_DBHOSTNAME" "-p$DBROOTPASSWORD" << EOF
RESET MASTER;
STOP SLAVE;
RESET SLAVE;
CHANGE MASTER TO MASTER_HOST='$MASTER_DBHOSTNAME', MASTER_USER='$DBREPLUSER', MASTER_PASSWORD='$DBREPLPASSWORD', MASTER_LOG_FILE='$MASTER_LOG_FILE', MASTER_LOG_POS=$MASTER_LOG_POS;
START SLAVE;
show processlist;
quit
EOF
}

#
# Main
#
cmd_line $*

# Insertion du dump
insert_dump
# Initialisation du slave
slave_init
