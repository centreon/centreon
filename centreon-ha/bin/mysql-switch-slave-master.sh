#!/bin/bash

###################################################
# Centreon                                Juin 2017
#
# Permet de switcher le sens de replication
# Les deux bases doivent etre allumees
#
###################################################

. /usr/share/centreon-ha/lib/mysql-functions.sh
. /etc/centreon-ha/mysql-resources.sh

usage()
{
echo
echo "Use : $0"
echo
}

cmd_line()
{
	:
}

switch_slave_master()
{
	# Trouver le slave
	mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_IO_Running: Yes'
	ret_value1=$?
	mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_SQL_Running: Yes'
	ret_value2=$?

	mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_IO_Running: Yes'
	ret_value3=$?
	mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_SQL_Running: Yes'
	ret_value4=$?
	total1=$(($ret_value1 + $ret_value2))
	total2=$(($ret_value3 + $ret_value4))
	
	# Verifier si y'a deux slaves (anormal)
	if [ "$total1" -lt 2 ] && [ "$total2" -lt 2 ] ; then
		echo "Slave process launch on 2. Need to clean manually."
		exit 2
	fi
	if [ "$total1" -lt 2 ] ; then
		NEW_MASTER="$DBHOSTNAMEMASTER"
	fi
	if [ "$total2" -lt 2 ] ; then
		NEW_MASTER="$DBHOSTNAMESLAVE"
	fi
	
	OLD_MASTER=$(get_other_db_hostname "$NEW_MASTER")
        mysql_connection_test "$NEW_MASTER"
        mysql_connection_test "$OLD_MASTER"

	# Locker le maitre
        mysql -f -u "$DBROOTUSER" -h "$OLD_MASTER" "-p$DBROOTPASSWORD" << EOF
FLUSH TABLES WITH READ LOCK;
SET GLOBAL read_only = ON;
quit
EOF
	
	# Stopper l'i/o esclave
	echo "Stop I/O Thread - Connection stopped with the master"
        mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" << EOF
SET GLOBAL read_only = ON;
RESET MASTER;
STOP SLAVE IO_THREAD;
quit
EOF
	# On attend que le thread SQL finisse de traiter le relay-log
	# Has read all relay log; waiting for the slave I/O thread to update it
	# http://dev.mysql.com/doc/refman/5.0/en/slave-sql-thread-states.html
	TIMEOUT=60
	echo "Waiting Relay log bin to finish proceed (TIMEOUT = ${TIMEOUT}sec)"
	i=0
	while : ; do
		if [ "$i" -gt "$TIMEOUT" ] ; then
			echo "Not finished smoothly.!!!"
			break
		fi
		mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" -e 'SHOW PROCESSLIST\G' | grep -qi 'Has read all relay log; waiting for the slave I/O thread to update it'
		if [ "$?" -eq "0" ] ; then
			break
		else
			echo -n "."
		fi
		i=$(($i + 1))
		sleep 1
	done

        mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" << EOF
STOP SLAVE SQL_THREAD;
RESET SLAVE;
RESET MASTER;
CHANGE MASTER TO MASTER_HOST='';
quit
EOF

	# On recupere le fichier et pos
	FILE_BINLOG=$(mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" -e 'SHOW MASTER STATUS\G' | grep -i 'File' | awk '{ print $2 }')
	POSITION_BINLOG=$(mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" -e 'SHOW MASTER STATUS\G' | grep -i 'Position' | awk '{ print $2 }')
	
	# On enleve le verrou
        mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" << EOF
SET GLOBAL read_only = OFF;
quit
EOF
	# On passe l'ancien maitre en esclave
        mysql -f -u "$DBROOTUSER" -h "$OLD_MASTER" "-p$DBROOTPASSWORD" << EOF
STOP SLAVE;
RESET SLAVE;
RESET MASTER;
CHANGE MASTER TO MASTER_HOST='$NEW_MASTER', MASTER_USER='$DBREPLUSER', MASTER_PASSWORD='$DBREPLPASSWORD', MASTER_LOG_FILE='$FILE_BINLOG', MASTER_LOG_POS=$POSITION_BINLOG;
START SLAVE;
UNLOCK TABLES;
show processlist;
quit
EOF
	
}

#
# Main
#
cmd_line $*

# Initialisation du slave
switch_slave_master
