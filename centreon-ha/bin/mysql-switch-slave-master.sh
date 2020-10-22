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

usage() {
    echo
    echo "Use : $0"
    echo
}

cmd_line()
{
    local arg
    while (( $# > 0 )) ; do
        arg="$1"
        case $arg in
            --debug)
                DEBUG=1
                shift
                ;;
            --verbose)
                VERBOSE=1
                shift
                ;;
            *)
                usage
                exit 1
                ;;
        esac
    done
}

switch_slave_master()
{
    # Trouver le slave
#    mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_IO_Running: Yes'
#    ret_value1=$?
#    mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_SQL_Running: Yes'
#    ret_value2=$?
#    mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_IO_Running: Yes'
#    ret_value3=$?
#    mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Slave_SQL_Running: Yes'
#    ret_value4=$?
#    total1=$(($ret_value1 + $ret_value2))
#    total2=$(($ret_value3 + $ret_value4))

    [[ "$DEBUG" ]] && set -x
    total1=$(mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -cE 'Slave_(IO|SQL)_Running: Yes')
    total2=$(mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -cE 'Slave_(IO|SQL)_Running: Yes')
    [[ "$DEBUG" ]] && set +x

    [[ "$VERBOSE" ]] && echo -e "There are:\n\t- $total1 slave running on $DBHOSTNAMEMASTER\n\t- $total2 slave running on $DBHOSTNAMESLAVE"
    # There should be 2 on one side (the slave and master-to-be) and 0 on the other (future ex-master)
    [[ "$DEBUG" ]] && set -x
    if (( total1 < 2 )) && (( total2 == 2 )) ; then
        NEW_MASTER="$DBHOSTNAMESLAVE"
    elif (( total2 < 2 )) && (( total1 == 2 )) ; then
        NEW_MASTER="$DBHOSTNAMEMASTER"
    elif (( total1 < 2 )) && (( total2 < 2 )) ; then
        echo "No slave thread seems to be properly working. Need to clean manually."
        exit 2
    elif (( total1 == 2 )) && (( total2 == 2 )) ; then
        echo "Slave process launch on both servers. Need to clean manually."
        exit 2
    else
        echo "*** What happened??? There are:\n\t- $total1 slave running on $DBHOSTNAMEMASTER\n\t- $total2 slave running on $DBHOSTNAMESLAVE\nThis is not normal..."
        exit 2
    fi
    [[ "$DEBUG" ]] && set +x
    
    [[ "$DEBUG" ]] && declare -p NEW_MASTER
    [[ "$DEBUG" ]] && set -x
    OLD_MASTER=$(get_other_db_hostname "$NEW_MASTER")
    [[ "$DEBUG" ]] && set +x
    [[ "$VERBOSE" ]] && echo -e "Roles switching from\n\t\tBefore\t\t\tAfter\n\tMaster:\t$OLD_MASTER\t-->\t$NEW_MASTER\n\tSlave:\t$NEW_MASTER\t-->\t$OLD_MASTER"

    mysql_connection_test "$NEW_MASTER"
    mysql_connection_test "$OLD_MASTER"

    # Locker le maitre
    echo "Locking master database on $OLD_MASTER"
    mysql -f -u "$DBROOTUSER" -h "$OLD_MASTER" "-p$DBROOTPASSWORD" << EOF
FLUSH TABLES WITH READ LOCK;
SET GLOBAL read_only = ON;
EOF
    
#    # Stopper l'i/o esclave
#    echo "Stop I/O Thread - Connection stopped with the master"
#    mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" << EOF
#RESET MASTER;
#STOP SLAVE IO_THREAD;
#quit
#EOF
    # On attend que le thread SQL finisse de traiter le relay-log
    # Has read all relay log; waiting for the slave I/O thread to update it
    # http://dev.mysql.com/doc/refman/5.0/en/slave-sql-thread-states.html
    TIMEOUT=60
    echo "Waiting Relay log bin to finish proceed (TIMEOUT = ${TIMEOUT}sec)"
    i=0
    while : ; do
        if (( i > TIMEOUT )) ; then
            echo "Not finished smoothly.!!!"
            break
        fi
        mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" -e 'SHOW SLAVE STATUS\G' | grep -qi 'Has read all relay log; waiting for the slave I/O thread to update it'
        if (( $?  == 0 )) ; then
            break
        else
            echo -n "."
        fi
        i=$(($i + 1))
        sleep 1
    done

    echo "Removing slave thread on $NEW_MASTER"
    mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" << EOF
STOP ALL SLAVES;
RESET SLAVE ALL;
RESET MASTER;
EOF

    # On recupere le fichier et pos
    echo "Recording binlog file and position from $NEW_MASTER"
    FILE_BINLOG=$(mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" -e 'SHOW MASTER STATUS\G' | grep -i 'File' | awk '{ print $2 }')
    POSITION_BINLOG=$(mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" -e 'SHOW MASTER STATUS\G' | grep -i 'Position' | awk '{ print $2 }')
    
    # On enleve le verrou
    echo "Unlocking databases on $NEW_MASTER"
    mysql -f -u "$DBROOTUSER" -h "$NEW_MASTER" "-p$DBROOTPASSWORD" << EOF
SET GLOBAL read_only = OFF;
EOF
    # On passe l'ancien maitre en esclave
    echo "Setting and starting slave thread on $OLD_MASTER"
    mysql -f -u "$DBROOTUSER" -h "$OLD_MASTER" "-p$DBROOTPASSWORD" << EOF
STOP ALL SLAVES;
RESET SLAVE;
RESET MASTER;
CHANGE MASTER TO MASTER_HOST='$NEW_MASTER', MASTER_USER='$DBREPLUSER', MASTER_PASSWORD='$DBREPLPASSWORD', MASTER_LOG_FILE='$FILE_BINLOG', MASTER_LOG_POS=$POSITION_BINLOG;
START SLAVE;
UNLOCK TABLES;
EOF

}

#
# Main
#
#set -x
cmd_line $* || exit

# Initialisation du slave
switch_slave_master
/usr/share/centreon-ha/bin/move-mysql-vip-to-mysql-master.sh


