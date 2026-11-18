#!/bin/bash

###################################################
# Centreon                                Juin 2017
#
# Permet de synchroniser en passant par 
#   une sauvegarde binaire
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

#
# Main
#
cmd_line $*

###########################################
# SANITY CHECK
###########################################

# minimum Go
VG_FREESIZE_NEEDED=1
STOP_TIMEOUT=60
# Longer than the default connect retry (60s) of the replica threads
REPLICATION_TIMEOUT=120
SNAPSHOT_MOUNT_PATH="/mnt/"
USER="mysql"
USER_SUDO="sudo -u $USER"
MYSQLADMIN="$SQL_ADMIN"
SUDO_MYSQL_START_SLAVE="sudo"
SSH_PORT="22"
RSYNC_EXTRA_OPTIONS="--checksum"

if [[ "$USER" == "root" ]] ; then
    USER_SUDO=
    SUDO_MYSQL_START_SLAVE=
fi

###
# Check rsync
###

which rsync > /dev/null
if [ "$?" -ne "0" ] ; then
    echo "ERROR: Need rsync command." >&2
    exit 1
fi

###
# Get DB parameters
###
if ! sql_fetch_db_config ; then
    echo "ERROR: Cannot find the MariaDB/MySQL configuration." >&2
    exit 1
fi
sql_fetch_systemd_service

if [ -z "$SQL_SYSTEMD_SERVICE" ] ; then
    echo "Cannot find MariaDB/MySQL systemd service." >&2
    exit 1
fi

if [ -z "$SQL_CONF_DATADIR" ] ; then
    echo "ERROR: Can't find MySQL datadir." >&2
    exit 1
fi

if [ -z "$SQL_CONF_LOG_BIN_ARG" ] ; then
    echo "'log-bin' option not found. Can't sync." >&2
    exit 1
fi

logbin_files=$(basename "$SQL_CONF_LOG_BIN")
logbin_loc=$(dirname "$SQL_CONF_LOG_BIN")
if [ -z "$logbin_loc" ] || [ "$logbin_loc" = "." ] ; then
    logbin_loc="$SQL_CONF_DATADIR"
fi

relaylog_files=$(basename "$SQL_CONF_RELAY_LOG")
relaylog_loc=$(dirname "$SQL_CONF_RELAY_LOG")
if [ -z "$relaylog_loc" ] || [ "$relaylog_loc" = "." ] ; then
    relaylog_loc="$SQL_CONF_DATADIR"
fi

echo "MySQL datadir found: $SQL_CONF_DATADIR"
echo "MySQL logbin files: $logbin_files"
echo "MySQL logbin localisation: $logbin_loc"
echo "MySQL relaylog files: $relaylog_files"
echo "MySQL relaylog localisation: $relaylog_loc"

###
# Get mount datadir
###
mount_device=$(df -P "$SQL_CONF_DATADIR" | tail -1 | awk '{ print $1 }')
mount_point=$(df -P "$SQL_CONF_DATADIR" | tail -1 | awk '{ print $6 }')
if [ -z "$mount_device" ] ; then
    echo "ERROR: Can't get mount device for datadir." >&2
    exit 1
fi
if [ -z "$mount_point" ] ; then
    echo "ERROR: Can't get mount point for datadir." >&2
    exit 1
fi
echo "Mount device 'datadir' found: $mount_device"
echo "Mount point 'datadir' found: $mount_point"

###
# Get Volume group Name
###
vg_name=$(lvdisplay -c "$mount_device" | cut -d : -f 2)
lv_name=$(lvdisplay -c "$mount_device" | cut -d : -f 1)
if [ -z "$vg_name" ] ; then
    echo "ERROR: Can't get VolumeGroup name for datadir." >&2
    exit 1
fi
if [ -z "$lv_name" ] ; then
    echo "ERROR: Can't get LogicalVolume name for datadir." >&2
    exit 1
fi

echo "VolumeGroup found: $vg_name"
echo "LogicalVolume 'datadir' found: $lv_name"

###
# Get free Space
###

free_pe=$(vgdisplay -c "$vg_name" | cut -d : -f 16)
size_pe=$(vgdisplay -c "$vg_name" | cut -d : -f 13)
if [ -z "$free_pe" ] ; then
    echo "ERROR: Can't get free PE value for the VolumeGroup." >&2
    exit 1
fi
if [ -z "$size_pe" ] ; then
    echo "ERROR: Can't get size PE value for the VolumeGroup." >&2
    exit 1
fi

free_total_pe=$(echo $free_pe " " $size_pe | awk '{ print ($1 * $2) / 1024 / 1024 }')
echo "Free total size in VolumeGroup (Go): $free_total_pe"

echo "$free_total_pe $VG_FREESIZE_NEEDED" | awk '{ if ($2 > $1) { exit(1) } else { exit(0) } }'
if [ "$?" -eq 1 ] ; then
    echo "ERROR: Not enough free space in the VolumeGroup." >&2
    exit 1
fi

###
# Check slave server stopped
###

slave_hostname=$(get_other_db_hostname)
master_hostname=$(get_other_db_hostname $slave_hostname)
echo "Connection to slave Server (verify mysql stopped): $slave_hostname"
result=$($USER_SUDO ssh -p $SSH_PORT $slave_hostname 'if ps --no-headers -C mariadbd,mysqld >/dev/null; then echo "yes" ; else echo "no"; fi')
if [ "$result" != "no" ] ; then
    echo "ERROR: MySQL is launched or problem to connect to the server." >&2
    exit 1
fi
if [ "$SQL_IS_RUNNING" -eq 0 ] ; then
    echo "ERROR: MySQL master is not started." >&2
    exit 1
fi

#############
############# END SANITY CHECK
#############

restore_master_writable() {
    # Run on any exit between making the master read-only and writable again.
    # A second interrupt must not stop the restore.
    trap '' INT TERM
    if "$SQL_CLIENT" -B -N --connect-timeout=10 -u "$DBROOTUSER" -h "$master_hostname" -p"$DBROOTPASSWORD" -e 'SET GLOBAL read_only = OFF' ; then
        echo "The master '$master_hostname' is writable again." >&2
    elif "$MYSQLADMIN" --connect-timeout=10 -u "$DBROOTUSER" -h "$master_hostname" -p"$DBROOTPASSWORD" ping > /dev/null 2>&1 ; then
        echo "ERROR: Can't make the master '$master_hostname' writable again: run 'SET GLOBAL read_only = OFF' on it." >&2
    else
        echo "ERROR: The master '$master_hostname' is stopped or stopping: start it with 'systemctl start $SQL_SYSTEMD_SERVICE'." >&2
    fi
}
trap restore_master_writable EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [ "$SQL_IS_MARIADB" -eq 1 ] ; then
    gtid_current_pos=$("$SQL_CLIENT" -B -N -u "$DBROOTUSER" -h "$master_hostname" -p"$DBROOTPASSWORD" -e 'SET GLOBAL read_only = ON; SELECT @@gtid_current_pos')
else
    # mysql -B prints the newlines of gtid_executed as a literal \n: strip them.
    # super_read_only also blocks the CONNECTION_ADMIN sessions that read_only
    # lets write, so that the copy matches the GTID set. Setting read_only=OFF
    # clears it.
    gtid_current_pos=$("$SQL_CLIENT" -B -N -u "$DBROOTUSER" -h "$master_hostname" -p"$DBROOTPASSWORD" -e "SET GLOBAL super_read_only = ON; SELECT REPLACE(@@gtid_executed, '\n', '')")
fi
if [ "$SQL_IS_MARIADB" -eq 1 ] ; then
    _gtid_pattern='[0-9]+-[0-9]+-[0-9]+'
else
    # uuid:interval, or uuid:tag:interval with GTID tags
    _gtid_pattern='[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}:([a-z_][a-z0-9_]*:)?[0-9]+'
fi
if ! echo "$gtid_current_pos" | grep -qE "$_gtid_pattern" ; then
    echo "ERROR: Can't make the master read-only or read its GTID set." >&2
    exit 1
fi

echo "gtid_current_pos = " $gtid_current_pos

i=0
echo -n "Stopping $SQL_DB_BINARY:"
if ! "$MYSQLADMIN" -f -u "$DBROOTUSER" -h "$master_hostname" -p"$DBROOTPASSWORD" shutdown ; then
    echo ""
    echo "ERROR: Can't stop MySQL Server" >&2
    exit 1
fi
while ps -o args --no-headers -C "$SQL_DB_BINARY" >/dev/null; do
    if [ "$i" -gt "$STOP_TIMEOUT" ] ; then
        echo ""
        echo "ERROR: Can't stop MySQL Server" >&2
        exit 1
    fi
    echo -n "."
    sleep 1
    i=$(($i + 1))
done
echo "OK"

###
# Do snapshot
###
echo "Create LVM snapshot"
if [ "$lv_name_logbin" != "$lv_name" ] ; then
    lvcreate -l $(($free_pe / 2)) -s -n dbbackupdatadir $lv_name
    lvcreate -l $(($free_pe / 2)) -s -n dbbackuplogbin $lv_name_logbin
else
    lvcreate -l $free_pe -s -n dbbackupdatadir $lv_name
fi

###
# Start server
###
echo "Start $SQL_DB_BINARY: (systemctl start $SQL_SYSTEMD_SERVICE)"
systemctl start "$SQL_SYSTEMD_SERVICE" &
i=0
until "$SQL_SHOW" -u "$DBROOTUSER" -h "$master_hostname" -p"$DBROOTPASSWORD" > /dev/null 2>&1; do
    if [ "$i" -gt "$STOP_TIMEOUT" ] ; then
        echo ""
        echo "ERROR: Can't start MySQL server" >&2
        exit 1
    fi
    echo -n "."
    sleep 1
    i=$(($i + 1))
done
echo "OK"

###
# Make master DB writable
###

echo "Remove read_only on master"
if ! "$SQL_CLIENT" -u "$DBROOTUSER" -h "$master_hostname" -p"$DBROOTPASSWORD" -e "SET GLOBAL read_only=off" ; then
    echo "ERROR: Can't remove read_only on master" >&2
    exit 1
fi
trap - EXIT INT TERM

###
# Mount snapshot
###

echo "Mount LVM snapshot"
SNAPSHOT_DATADIR_MOUNT="$SNAPSHOT_MOUNT_PATH/snap-dbbackupdatadir"
mkdir -p "$SNAPSHOT_DATADIR_MOUNT"
TYPEFS_BACKUP=$(df -T "$SQL_CONF_DATADIR" | tail -1 | awk -F' ' '{print $(NF-5)}')
[ "$TYPEFS_BACKUP"  = "xfs" ] && MNTOPTIONS="-o nouuid"
mount $MNTOPTIONS /dev/$vg_name/dbbackupdatadir "$SNAPSHOT_DATADIR_MOUNT"

concat_datadir=$(echo "$SQL_CONF_DATADIR" | sed "s#^${mount_point}##")

###
# Delete from other side
###

echo "Delete Logbin and RelayLog files"
$USER_SUDO ssh -p $SSH_PORT $slave_hostname "rm -f \"${logbin_loc}/${logbin_files}\"* \"${relaylog_loc}/${relaylog_files}\"*"

###
# Rsync
###

abort_copy() {
    # Leave the slave stopped: setting up the replication on a partial copy
    # would mark the master's transactions as executed without their data
    echo "ERROR: The copy to '$slave_hostname' is incomplete. Its database is left stopped: do not start it, nor let Pacemaker manage it, before mysql-sync-bigdb.sh succeeds." >&2
    if ! umount "$SNAPSHOT_DATADIR_MOUNT" || ! lvremove -f /dev/$vg_name/dbbackupdatadir ; then
        echo "ERROR: Remove the snapshot by hand: umount $SNAPSHOT_DATADIR_MOUNT; lvremove /dev/$vg_name/dbbackupdatadir" >&2
    fi
    exit 1
}

echo "Rsync in progress (exclude MySQL, ${logbin_files}, ${relaylog_files})"
# The packages leave root-owned markers of their version in the datadir
# (debian-*.flag, *_upgrade_info): each node keeps its own, and rsync,
# running as mysql, cannot update them
rsync -av $RSYNC_EXTRA_OPTIONS --delete --progress --exclude="mysql" --exclude="debian-*.flag" --exclude="mariadb_upgrade_info" --exclude="mysql_upgrade_info" --exclude="${SQL_CONF_PID_FILE}.pid" --exclude="${logbin_files}*" --exclude="${relaylog_files}*" --exclude="auto.cnf" --exclude="mysqld-auto.cnf" --exclude=".ssh/*" "$SNAPSHOT_DATADIR_MOUNT/$concat_datadir/" -e "$USER_SUDO ssh -p $SSH_PORT" $slave_hostname:$SQL_CONF_DATADIR/ || abort_copy

mysql_ibd_system=''
# MySQL keeps its system tables in mysql.ibd, copied above: mysql/ has no .ibd
for file in "$SNAPSHOT_DATADIR_MOUNT/$concat_datadir/mysql/"*.ibd; do
    [ -e "$file" ] || continue
    filename=$(basename $file | sed 's/\.ibd//')
    mysql_ibd_system="$mysql_ibd_system \"$SNAPSHOT_DATADIR_MOUNT/$concat_datadir/mysql/$filename.ibd\""
    # rsync fails on a missing source file
    if [ -f "$SNAPSHOT_DATADIR_MOUNT/$concat_datadir/mysql/$filename.frm" ] ; then
        mysql_ibd_system="$mysql_ibd_system \"$SNAPSHOT_DATADIR_MOUNT/$concat_datadir/mysql/$filename.frm\""
    fi
done
if [ -n "$mysql_ibd_system" ] ; then
    eval rsync -av $RSYNC_EXTRA_OPTIONS --progress $mysql_ibd_system -e \"\$USER_SUDO ssh -p $SSH_PORT\" \$slave_hostname:\"\$SQL_CONF_DATADIR/mysql/\" || abort_copy
fi

# Mode Fastest. Uncomment this and comment line above
#rsync -av --delete --progress --exclude="*.MYI" --exclude="*.MYD" --exclude="mysql" --exclude="${SQL_CONF_PID_FILE}.pid" --exclude="${logbin_files}*" --exclude="${relaylog_files}*" --exclude=".ssh/*" "$SNAPSHOT_DATADIR_MOUNT/$concat_datadir/" -e "$USER_SUDO ssh -p $SSH_PORT" $slave_hostname:$SQL_CONF_DATADIR/
#rsync -av --size-only --delete --progress --include='*/' --exclude="mysql" --include='*.MYI' --include='*.MYD' --exclude='*' --exclude=".ssh/*" "$SNAPSHOT_DATADIR_MOUNT/$concat_datadir/" -e "ssh -i /var/lib/mysql/.ssh/id_rsa -p $SSH_PORT" $USER@$slave_hostname:$SQL_CONF_DATADIR/

###
# Suppression du snapshot
###

echo "Umount and Delete LVM snapshot"
umount "$SNAPSHOT_DATADIR_MOUNT"
lvremove -f /dev/$vg_name/dbbackupdatadir

###
# Demarrer le serveur slave
###

echo "Start MySQL Slave"
# SQL_SYSTEMD_SERVICE and SQL_IS_MARIADB come from this node: both nodes must
# run the same engine on the same distribution
$USER_SUDO ssh -p $SSH_PORT $slave_hostname -- "$SUDO_MYSQL_START_SLAVE systemctl start '$SQL_SYSTEMD_SERVICE' &"
i=0
until "$SQL_SHOW" -u "$DBROOTUSER" -h "$slave_hostname" -p"$DBROOTPASSWORD" > /dev/null 2>&1; do
        if [ "$i" -gt "$STOP_TIMEOUT" ] ; then
                echo ""
                echo "ERROR: Can't start MySQL server" >&2
                exit 1
        fi
        echo -n "."
        sleep 1
        i=$(($i + 1))
done
echo "OK"

###
# Demarrer la replication
###
echo "Start Replication"
if [ "$SQL_IS_MARIADB" -eq 1 ] ; then
    reset_sql="RESET MASTER;
STOP SLAVE;
RESET SLAVE;"
    replication_sql="SET GLOBAL gtid_slave_pos = '$gtid_current_pos';
CHANGE MASTER TO MASTER_HOST='$master_hostname', MASTER_USER='$DBREPLUSER', MASTER_PASSWORD='$DBREPLPASSWORD', master_use_gtid=slave_pos;
START SLAVE;
SHOW PROCESSLIST;"
    status_sql="SHOW SLAVE STATUS\G"
    io_running_field="Slave_IO_Running"
    sql_running_field="Slave_SQL_Running"
else
    reset_sql="STOP REPLICA;
RESET REPLICA ALL;
RESET BINARY LOGS AND GTIDS;"
    # SOURCE_SSL=1: caching_sha2_password (the default plugin) needs a
    # secure connection whenever the source has not cached the password
    # yet, e.g. after a restart
    replication_sql="SET GLOBAL gtid_purged = '$gtid_current_pos';
CHANGE REPLICATION SOURCE TO SOURCE_HOST='$master_hostname', SOURCE_USER='$DBREPLUSER', SOURCE_PASSWORD='$DBREPLPASSWORD', SOURCE_AUTO_POSITION=1, SOURCE_SSL=1;
START REPLICA;
SHOW PROCESSLIST;"
    status_sql="SHOW REPLICA STATUS\G"
    io_running_field="Replica_IO_Running"
    sql_running_field="Replica_SQL_Running"
fi
# The datadir copied from the master can hold a stale replication
# configuration that makes the reset statements fail: keep going (-f), the
# next step fails if the reset was incomplete
if ! reset_output=$("$SQL_CLIENT" -f -u "$DBROOTUSER" -h "$slave_hostname" -p"$DBROOTPASSWORD" <<< "$reset_sql" 2>&1) ; then
    echo "WARNING: The reset of the replication reported errors, ignored as the setup below fails if it is incomplete:" >&2
    echo "$reset_output" >&2
fi
# No -f: never start the replication on a partial setup
if ! "$SQL_CLIENT" -u "$DBROOTUSER" -h "$slave_hostname" -p"$DBROOTPASSWORD" <<< "$replication_sql" ; then
    echo "ERROR: Can't start the replication on '$slave_hostname'." >&2
    exit 1
fi

# START SLAVE/REPLICA returns before the threads connect: wait until they run
# without error for a few seconds, so that the first events are applied
echo -n "Wait for the replication threads:"
i=0
running=0
while : ; do
    if replica_status=$("$SQL_CLIENT" -u "$DBROOTUSER" -h "$slave_hostname" -p"$DBROOTPASSWORD" -e "$status_sql") ; then
        if echo "$replica_status" | grep -qE "^ *${io_running_field}: Yes$" \
            && echo "$replica_status" | grep -qE "^ *${sql_running_field}: Yes$" \
            && echo "$replica_status" | grep -qE "^ *Last_IO_Errno: 0$" \
            && echo "$replica_status" | grep -qE "^ *Last_SQL_Errno: 0$" ; then
            running=$(($running + 1))
            if [ "$running" -ge 3 ] ; then
                break
            fi
        else
            running=0
        fi
    else
        replica_status=""
        running=0
    fi
    if [ "$i" -gt "$REPLICATION_TIMEOUT" ] ; then
        echo ""
        if [ -z "$replica_status" ] ; then
            echo "ERROR: Can't read the replication status on '$slave_hostname'." >&2
        else
            echo "ERROR: The replication does not run on '$slave_hostname':" >&2
            echo "$replica_status" | grep -E "^ *(${io_running_field}|${sql_running_field}|Last_IO_Error|Last_SQL_Error):" >&2
        fi
        exit 1
    fi
    echo -n "."
    sleep 1
    i=$(($i + 1))
done
echo "OK"

exit 0
