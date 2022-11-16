#!/bin/bash

###################################################
# Centreon                                Juin 2017
#
# Permet de purger les logs MySQL
#
###################################################

. /usr/share/centreon-ha/lib/mysql-functions.sh
. /etc/centreon-ha/mysql-resources.sh

usage()
{
echo
echo "Use : $0 [<max-size>]"
echo " max-size is in Go (it used when you have only the master server and binlogs size is greater)"
echo " We prefer to loose sync than loose master server."
echo
}

cmd_line()
{
	if [ $# -gt 0 ] ; then
		echo $1 | grep -qE '^[0-9]*$'
		if [ "$?" -ne 0 ] ; then
			echo "max-size must be a positive number."
			exit
		fi
	fi
	MAX_SIZE=${1:-5}
}

log_status()
{
	echo $(date -R) ": Beginning script mysql-purge-log script"
	slave_address=""

	#######
	# On teste la connexion
	#######
	report_connexion1=$(mysql_connection_test "$DBHOSTNAMEMASTER" 1 2>&1)
	connexion_status_server1=$?
	connexion_status_servername1="$DBHOSTNAMEMASTER"
	report_connexion2=$(mysql_connection_test "$DBHOSTNAMESLAVE" 1 2>&1)
	connexion_status_server2=$?
	connexion_status_servername2="$DBHOSTNAMESLAVE"

	#######
	# Find slave
	#######
	slave_status=0
	slave_status_error=""
	slave_address=""
	if [ "$connexion_status_server1" -eq 0 ] ; then
		mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_IO_Running: Yes'
		status_io_thread1=$?
		mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_SQL_Running: Yes'
		status_sql_thread1=$?
	else
		status_io_thread1=100
		status_sql_thread1=100
	fi
	
	if [ "$connexion_status_server2" -eq 0 ] ; then
		mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_IO_Running: Yes'
		status_io_thread2=$?
		mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_SQL_Running: Yes'
		status_sql_thread2=$?
	else
		status_io_thread2=100
		status_sql_thread2=100
	fi
	total1=$(($status_io_thread1 + $status_sql_thread1))
	total2=$(($status_io_thread2 + $status_sql_thread2))
	# Verifier si y'a deux slaves (anormal)
	if [ "$total1" -lt 2 ] && [ "$total2" -lt 2 ] ; then
		slave_status=1
		echo $(date -R) ": Two slave. Need to have only one. Resolve the issue."
		exit 1
	else
		# Get slave
		if [ "$total1" -lt 2 ] ; then
			slave_address="$DBHOSTNAMEMASTER"
		fi
		if [ "$total2" -lt 2 ] ; then
			slave_address="$DBHOSTNAMESLAVE"
		fi
		
		# Known if a replication thread is down
		THREAD_DOWN=0
		if [ "$total1" -eq 1 ] || [ "$total2" -eq 1 ] ; then
			THREAD_DOWN=1
		fi
	fi

	# Get Slave position if possible
	if [ -n "$slave_address" ] ; then
		master_address=$(get_other_db_hostname "$slave_address")
		temp_read=$(mysql -f -u "$DBROOTUSER" -h "$slave_address" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -iE '[^a-zA-Z_]Master_Log_File|Read_Master_Log_Pos')
		slave_file=$(echo "$temp_read" | grep -i 'Master_Log_File' | awk '{ print $2 }')
		slave_position=$(echo "$temp_read" | grep -i 'Read_Master_Log_Pos' | awk '{ print $2 }')
	else
		if [ "$connexion_status_server1" -eq 0 ] ; then
			master_address=$connexion_status_servername1
		elif [ "$connexion_status_server2" -eq 0 ] ; then
			master_address=$connexion_status_servername2
		fi
	fi

	#Get Master Info
	temp_read=$(mysql -f -u "$DBROOTUSER" -h "$master_address" "-p$DBROOTPASSWORD" -e 'SHOW MASTER STATUS\G' | grep -iE 'File|Position')
	master_file=$(echo "$temp_read" | grep -i 'File' | awk '{ print $2 }')
	master_position=$(echo "$temp_read" | grep -i 'Position' | awk '{ print $2 }')
	#Get total size binlogs
	temp_read=$(mysql -f -u "$DBROOTUSER" -h "$master_address" "-p$DBROOTPASSWORD" -e 'SHOW binary logs\G' | grep -i 'File_size')
	total_binlogs_size_go=$(echo "$temp_read" | awk '{ total += $2 } END { print total / 1024 / 1024 / 1024 }' | sed 's/,/./g')


	echo $(date -R) ": Master Server is: [" $master_address "]"
	echo $(date -R) ": Slave Server is: [" $slave_address "]"

	echo $(date -R) ": Master current file: " $master_file
	echo $(date -R) ": Master current position file: " $master_position

	echo $(date -R) ": Slave current file: " $slave_file
	echo $(date -R) ": Slave current position file: " $slave_position

	echo "Total binary logs size (Go): $total_binlogs_size_go"

	# Manage case replication is 'on' in slave. But there is a problem with a thread
	# We prefer to purge binary logs than use all disk space
	if [ "$THREAD_DOWN" -eq 1 ] ; then
		echo "$total_binlogs_size_go $MAX_SIZE" | awk '{ if ($1 > $2) { exit(1) } else { exit(0) } }'
		if [ "$?" -eq 1 ] ; then
			echo $(date -R) ": We'll break replication. We prefer to purge binary logs than use all disk space."
			echo $(date -R) ": Execute in progress: PURGE BINARY LOGS TO '$master_file'"
			mysql -f -u "$DBROOTUSER" -h "$master_address" "-p$DBROOTPASSWORD" -e "PURGE BINARY LOGS TO '$master_file'"
		else
			echo $(date -R) ": Nothing done."
		fi
		exit 0
	fi
	
	if [ -n "$slave_file" ] ; then
		slave_num=$(echo "$slave_file" | awk -F. '{ file_num = $2 + 0 } END { print file_num }')
		master_num=$(echo "$master_file" | awk -F. '{ file_num = $2 + 0 } END { print file_num }')
		if [ "$slave_num" -gt "$master_num" ] ; then
			echo $(date -R) ": Anormal that slave file is higher that master bin logs."
			exit 1
		fi
		echo $(date -R) ": Execute in progress: PURGE BINARY LOGS TO '$slave_file'"
		mysql -f -u "$DBROOTUSER" -h "$master_address" "-p$DBROOTPASSWORD" -e "PURGE BINARY LOGS TO '$slave_file'"
	else
		echo "$total_binlogs_size_go $MAX_SIZE" | awk '{ if ($1 > $2) { exit(1) } else { exit(0) } }'
		if [ "$?" -eq 1 ] ; then
			echo $(date -R) ": We'll break replication. We prefer to purge binary logs than use all disk space."
			echo $(date -R) ": Execute in progress: PURGE BINARY LOGS TO '$master_file'"
			mysql -f -u "$DBROOTUSER" -h "$master_address" "-p$DBROOTPASSWORD" -e "PURGE BINARY LOGS TO '$master_file'"
		else
			echo $(date -R) ": Nothing done."
		fi
	fi
	echo $(date -R) ": Finishing mysql-purge-log script"
}

#
# Main
#
cmd_line $*

# Log Status
log_status

exit 0
