#!/bin/bash

###################################################
# Centreon                                Sept 2022
#
# Checks the replication state
#
###################################################

. /usr/share/centreon-ha/lib/mysql-functions.sh
. /etc/centreon-ha/mysql-resources.sh


append_error_msg()
{
	eval $1=\"\$$1\$$3\$2\"
	eval $3=\"\\n\ \ \ \ \"
}

display_result_one()
{
	echo -n "$2 ["
	if [ "$1" -eq 0 ] ; then
		echo -en "\033[1;32mOK\033[m"
	elif [ "$1" -eq -1 ] ; then
		echo -en "\033[33mWARNING\033[m"
	elif [ "$1" -eq -2 ] ; then
		echo -en "SKIP"
	else
		echo -en "\033[31mKO\033[m"
	fi
	echo "]"
	
	if [ -n "$3" ] ; then
		echo "Error reports:"
		echo -e "    $3"
	fi
}

display_result()
{
	if [[ "$slave_address" == "$DBHOSTNAMEMASTER" ]] ; then
		display_result_one "$connexion_status_server2" "Connection MASTER Status '$DBHOSTNAMESLAVE'" "$report_connexion2"
		display_result_one "$connexion_status_server1" "Connection SLAVE Status '$DBHOSTNAMEMASTER'" "$report_connexion1"
	elif [[ "$slave_address" == "$DBHOSTNAMESLAVE" ]] ; then
		display_result_one "$connexion_status_server1" "Connection MASTER Status '$DBHOSTNAMEMASTER'" "$report_connexion1"
		display_result_one "$connexion_status_server2" "Connection SLAVE Status '$DBHOSTNAMESLAVE'" "$report_connexion2"
	else
		display_result_one "$connexion_status_server1" "Connection SLAVE Status '$DBHOSTNAMEMASTER'" "$report_connexion1"
		display_result_one "$connexion_status_server2" "Connection SLAVE Status '$DBHOSTNAMESLAVE'" "$report_connexion2"
	fi
	display_result_one $slave_status "Slave Thread Status" "$slave_status_error"
	display_result_one $position_status "Position Status" "$position_status_error"
}

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

replication_status()
{
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
	slave_status_error_append=""
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
		slave_status_error="Two slave. Need to have only one."
	else
		# voir si un thread est down
		if [ "$total1" -eq 1 ] ; then
			slave_status=-1
			slave_status_error="A Replication thread is down on '$DBHOSTNAMEMASTER'."

			# Cas ou le thread SQL est arrete a cause d'une erreur
			if [ "$status_sql_thread1" -ne 0 ] ; then
				last_error=$(mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -i 'Last_Error' | awk '{ for (i = 2; i < NF; i++) { myerror = myerror " " $i } } END { print myerror } ')
				if [ -n "$last_error" ] ; then
					slave_status=1
					append_error_msg "slave_status_error" "SQL Thread is stopped because of an error (error='$last_error')." "slave_status_error_append"
				fi
			fi
		fi
		if [ "$total2" -eq 1 ] ; then
			slave_status=-1
			slave_status_error="A Replication thread is down on '$DBHOSTNAMESLAVE'."

			# Cas ou le thread SQL est arrete a cause d'une erreur
			if [ "$status_sql_thread2" -ne 0 ] ; then
				last_error=$(mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -i 'Last_Error' | awk '{ for (i = 2; i < NF; i++) { myerror = myerror " " $i } } END { print myerror }')
				if [ -n "$last_error" ] ; then
					slave_status=1
					append_error_msg "slave_status_error" "SQL Thread is stopped because of an error (error='$last_error')." "slave_status_error_append"
				fi
			fi
		fi

		# voir si on a skip
		if [ "$status_io_thread1" -eq 100 ] ; then
			slave_status=-1
			append_error_msg "slave_status_error" "Skip check on '$DBHOSTNAMEMASTER'." "slave_status_error_append"
		fi
		if [ "$status_io_thread2" -eq 100 ] ; then
			slave_status=-1
			append_error_msg "slave_status_error" "Skip check on '$DBHOSTNAMESLAVE'." "slave_status_error_append"
		fi

		# Get slave
		if [ "$total1" -lt 2 ] ; then
			slave_address="$DBHOSTNAMEMASTER"
		fi
		if [ "$total2" -lt 2 ] ; then
			slave_address="$DBHOSTNAMESLAVE"
		fi

		# No slave
		if [ "$total2" -gt 1 ] && [ "$total1" -gt 1 ] ; then
			slave_status=1
			append_error_msg "slave_status_error" "No slave (maybe because we cannot check a server)." "slave_status_error_append"
		fi
	fi

	# verifier la position de l'esclave
	position_status=0
	position_status_error=""
	position_status_error_append=""
	if [ -z "$slave_address" ] ; then
		position_status=-2
		append_error_msg "position_status_error" "Skip because we can't identify a unique slave." "position_status_error_append"	
	else
		master_address=$(get_other_db_hostname "$slave_address")
		if [ "$master_address"  = "$connexion_status_servername1" ] && [ "$connexion_status_server1" -ne 0 ] ; then
			position_status=-1
			append_error_msg "position_status_error" "Can't get master position on '$master_address'." "position_status_error_append"	
		elif [ "$master_address"  = "$connexion_status_servername2" ] && [ "$connexion_status_server2" -ne 0 ] ; then
			position_status=-1
			append_error_msg "position_status_error" "Can't get master position on '$master_address'." "position_status_error_append"	
		else
			# get master position
			temp_read=$(mysql -f -u "$DBROOTUSER" -h "$master_address" "-p$DBROOTPASSWORD" -e 'SHOW MASTER STATUS\G' | grep -iE 'File|Position')
			master_file=$(echo "$temp_read" | grep -i 'File' | awk '{ print $2 }')
			master_position=$(echo "$temp_read" | grep -i 'Position' | awk '{ print $2 }')

			# get slave position (pas besoin de verifier)
			temp_read=$(mysql -f -u "$DBROOTUSER" -h "$slave_address" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -iE '[^a-zA-Z_]Master_Log_File|Read_Master_Log_Pos')
			slave_file=$(echo "$temp_read" | grep -i 'Master_Log_File' | awk '{ print $2 }')
			slave_position=$(echo "$temp_read" | grep -i 'Read_Master_Log_Pos' | awk '{ print $2 }')

			# Slave I/O thread wrong

			temp_read=$(mysql -f -u "$DBROOTUSER" -h "$slave_address" "-p$DBROOTPASSWORD" -e 'SHOW PROCESSLIST\G' | grep -qiE 'Waiting to reconnect after a failed binlog dump request|Connecting to master|Reconnecting after a failed binlog dump request|Waiting to reconnect after a failed master event read|Waiting for the slave SQL thread to free enough relay log space|Waiting for the next event in relay log|Reading event from the relay log|Has read all relay log; waiting for the slave I/O thread to update it')

			
			echo "$temp_read" | grep -qiE 'Waiting to reconnect after a failed binlog dump request|Connecting to master|Reconnecting after a failed binlog dump request|Waiting to reconnect after a failed master event read|Waiting for the slave SQL thread to free enough relay log space'
			slave_sql_thread_ko=$?
			# Slave Sql thread in progress
			echo "$temp_read" | grep -qiE 'Waiting for the next event in relay log|Reading event from the relay log'
			slave_sql_thread_warning=$?
			# Slave Sql thread ok
			echo "$temp_read" | grep -qiE 'Has read all relay log; waiting for the slave I/O thread to update it'
			slave_sql_thread_ok=$?
			
			if [ "$slave_sql_thread_ko" -eq 0 ] ; then
				position_status=1
				append_error_msg "position_status_error" "Slave replication has connection issue with the master." "position_status_error_append"
			elif ( [ "$master_file" != "$slave_file" ] || [ "$master_position" -ne "$slave_position" ] ) && [ $slave_sql_thread_warning -eq "0" ] ; then
				position_status=-1
				append_error_msg "position_status_error" "Slave replication is late but it's progressing." "position_status_error_append"
			elif ( [ "$master_file" != "$slave_file" ] || [ "$master_position" -ne "$slave_position" ] ) && [ $slave_sql_thread_ok -eq "0" ] ; then
				#We can't check that. There is too many calls
				#position_status=1
				#append_error_msg "position_status_error" "Slave replication is late but it says 'i have finished' (anormal)." "position_status_error_append"
				position_status=-1
				append_error_msg "position_status_error" "Slave replication is late but it's progressing." "position_status_error_append"
			fi
		fi
	fi

	display_result
}

#
# Main
#
cmd_line $*

# Initialisation du slave
replication_status
