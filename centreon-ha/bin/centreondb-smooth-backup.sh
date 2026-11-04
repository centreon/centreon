#!/bin/bash

###################################################
# Centreon                                Juin 2017
#
# Permet de sauvegarder la base de donnees
# Par defaut: une total est realise
# Pour une incremental, specifier l'option -i
#
###################################################

source /usr/share/centreon-ha/lib/mysql-functions.sh
source /etc/centreon-ha/mysql-resources.sh

OPT_TOTAL=1
OPT_TOTALINCR=0
OPT_INCR=0
while [ $# -gt 0 ] ; do
    case $1 in
    -t|--total) OPT_TOTAL=1 ;;
    -I|--totalincr) OPT_TOTALINCR=1 ;;
    -i|--incremental) OPT_INCR=1 ;;
    (--) shift; break;;
    (-*) echo "$0: error - unrecognized option $1" 1>&2; exit 1;;
    (*) break;;
    esac
    shift
done

if [ "$OPT_TOTALINCR" -eq 1 ] && [ "$OPT_INCR" -eq 1 ] ; then
	echo "ERROR: Can't use -i and -I option" >&2
	exit 1
fi

###########################################
# SANITY CHECK
###########################################

# minimum Go
VG_FREESIZE_NEEDED=1
STOP_TIMEOUT=60
SNAPSHOT_MOUNT="/mnt/snap-backup"
BACKUP_DIR="/var/backup"
SAVE_LAST_DIR="/var/lib/centreon-backup"
SAVE_LAST_FILE="backup.last"
DO_ARCHIVE=1
PARTITION_NAME="centreon_storage/data_bin centreon_storage/logs"
PACEMAKER_ON="1"
PACEMAKER_RSC_MYSQL="ms_mysql"
MYSQL_CNF="/etc/my.cnf.d/server.cnf"
READONLY_CHECK=1

###
# Get DB parameters
###
sql_fetch_db_binary
sql_fetch_systemd_service
sql_fetch_db_config

if [ -z "$SQL_DB_BINARY" ] ; then
    echo "ERROR: Cannot find MariaDB/MySQL binary." >&2
    exit 1
fi

if [ -z "$SQL_SYSTEMD_SERVICE" ] ; then
    echo "ERROR: Cannot find MariaDB/MySQL systemd service." >&2
    exit 1
fi

logbin_activated=1

#####
# Functions
#####

check_readonly() {
    readonly_value=0
    if [ "$READONLY_CHECK" -eq "0" ] ; then
        return 0
    fi
    
    readonly_value=$(mysql -N -B -u "$DBROOTUSER" -p"$DBROOTPASSWORD" -e 'SELECT @@global.read_only')
    if [ "$?" -ne "0" ] ; then
        output_log "ERROR: cannot get readonly option value" 1
	exit 1
    fi
       
    if [ "$readonly_value" -eq "0" ] ; then
        output_log "ERROR: The database is not on read_only. Maybe you tried to perform the backup on the master"
        exit 1
    fi
}

set_readonly() {
    if [ "$READONLY_CHECK" -eq "0" ] ; then
        return 0
    fi
    # we let the default if we don't know before
        mysql -N -B -u "$DBROOTUSER" -p"$DBROOTPASSWORD" -e "SET GLOBAL read_only=$readonly_value"
}

get_current_logbin_file() {
	up_logbin=$1

	if [ "$up_logbin" -eq 1 ] ; then
		mysqladmin --user="$DBROOTUSER" --password="$DBROOTPASSWORD" flush-logs
	fi

	if [ "$SQL_IS_MARIADB" -eq 1 ] ; then
		_show_status="SHOW MASTER STATUS"
	else
		_show_status="SHOW BINARY LOG STATUS"
	fi

	if [ -z "$DBROOTPASSWORD" ] ; then
		file=$(mysql -B -u "$DBROOTUSER" -e "$_show_status\G" 2>&1 | grep 'File:' | awk '{ print $2 }')
	else
		file=$(mysql -B -u "$DBROOTUSER" -p"$DBROOTPASSWORD" -e "$_show_status\G" 2>&1 | grep 'File:' | awk '{ print $2 }')
	fi
	if [ "$?" -ne "0" ] ; then
		output_log "ERROR: connection MySQL to get index file." 1
		exit 1
	fi
	echo "$file" | awk -F. '{ print $2 - 1 }'
}

output_log() {
	error="$2"
	
	no_cr=""
	if [ -n "$3" ] && [ "$3" -eq "1" ] ; then
		no_cr="-n"
	fi
	if [ -n "$error" ] && [ "$error" -eq "1" ] ; then
		echo $no_cr "[$(date +%s)]" $1 >&2
	else
		echo $no_cr "[$(date +%s)]" $1
	fi
}

###
# Find datadir AND logbin
###
datadir="$SQL_CONF_DATADIR"

if [ -z "$datadir" ] ; then
	output_log "ERROR: Can't find MySQL datadir." 1
	exit 1
fi

if [ -z "$SQL_CONF_LOG_BIN_ARG" ] ; then
	output_log "'log-bin' option not found. Can't do an incremental backup."
	logbin_activated=0
	OPT_INCR=0
else
	logbin_files=$(basename "$SQL_CONF_LOG_BIN")
	logbin_loc=$(dirname "$SQL_CONF_LOG_BIN")
	if [ -z "$logbin_loc" ] || [ "$logbin_loc" = "." ] ; then
		logbin_loc="$datadir"
	fi
fi

output_log "MySQL datadir found: $datadir"
output_log "MySQL logbin files: $logbin_files"
output_log "MySQL logbin localisation: $logbin_loc"

# Get mount
###
mount_device=$(df -P "$datadir" | tail -1 | awk '{ print $1 }')
mount_point=$(df -P "$datadir" | tail -1 | awk '{ print $6 }')
if [ -z "$mount_device" ] ; then
	output_log "ERROR: Can't get mount device for datadir." 1
	exit 1
fi
if [ -z "$mount_point" ] ; then
	output_log "ERROR: Can't get mount point for datadir." 1
	exit 1
fi
output_log "Mount device found: $mount_device"
output_log "Mount point found: $mount_point"

###
# Get Volume group Name
###
vg_name=$(lvdisplay -c "$mount_device" | cut -d : -f 2)
lv_name=$(lvdisplay -c "$mount_device" | cut -d : -f 1)
if [ -z "$vg_name" ] ; then
	output_log "ERROR: Can't get VolumeGroup name for datadir." 1
	exit 1
fi
if [ -z "$lv_name" ] ; then
	output_log "ERROR: Can't get LogicalVolume name for datadir." 1
	exit 1
fi
output_log "VolumeGroup found: $vg_name"

###
# Get free Space
###

free_pe=$(vgdisplay -c "$vg_name" | cut -d : -f 16)
size_pe=$(vgdisplay -c "$vg_name" | cut -d : -f 13)
if [ -z "$free_pe" ] ; then
	output_log "ERROR: Can't get free PE value for the VolumeGroup." 1
	exit 1
fi
if [ -z "$size_pe" ] ; then
	output_log "ERROR: Can't get size PE value for the VolumeGroup." 1
	exit 1
fi

free_total_pe=$(echo $free_pe " " $size_pe | awk '{ print ($1 * $2) / 1024 / 1024 }')
output_log "Free total size in VolumeGroup (Go): $free_total_pe"

echo "$free_total_pe $VG_FREESIZE_NEEDED" | awk '{ if ($2 > $1) { exit(1) } else { exit(0) } }'
if [ "$?" -eq 1 ] ; then
	output_log "ERROR: Not enough free space in the VolumeGroup." 1
	exit 1
fi

###
# Create BACKUP DIR
###

if [ "$DO_ARCHIVE" -eq "0" ] ; then
	BACKUP_DIR_TOTAL="$BACKUP_DIR/mysql-$(date +%Y-%m-%d)"
else
	BACKUP_DIR_TOTAL="$BACKUP_DIR"
fi
mkdir -p "$BACKUP_DIR_TOTAL"
if [ ! -d "$BACKUP_DIR_TOTAL" ] ; then
	output_log "ERROR: Directory '$BACKUP_DIR_TOTAL' doesn't exist." 1
	exit 1
fi

###
# Check Last DIR
###
mkdir -p "$SAVE_LAST_DIR"
if [ ! -f "$SAVE_LAST_DIR/$SAVE_LAST_FILE" ] ; then
	touch "$SAVE_LAST_DIR/$SAVE_LAST_FILE"
fi
if [ ! -w "$SAVE_LAST_DIR/$SAVE_LAST_FILE" ] ; then
	output_log "ERROR: Don't have permission on '$SAVE_LAST_DIR/$SAVE_LAST_FILE' file." 1
	exit 1
fi

###
# Get last index file
##
last_index_file=""
if [ "$logbin_activated" -eq "1" ] && [ "$OPT_INCR" -eq "1" ] ; then
	if [ -f "$SAVE_LAST_DIR/centreondb-smooth-backup.save" ] ; then
		last_binlog_file=$(cat "$SAVE_LAST_DIR/centreondb-smooth-backup.save")
	fi

	if [ -z "$last_binlog_file" ] || ! echo $last_binlog_file | head -1 | grep -qE "^[0-9]+$" ; then
		output_log "Can't get last index file. Can't do an incremental backup."
		OPT_INCR=0
	fi
fi

###
# Verify if an problem in incremental numbers
###
if [ "$logbin_activated" -eq "1" ] && [ "$OPT_INCR" -eq 1 ] ; then
	current_binlog_file=$(get_current_logbin_file 1)
	if [ -z "$current_binlog_file" ] || ! echo "$last_binlog_file $current_binlog_file" | awk '{ if ($1 < $2 ) { exit(0) } else { exit(1) } }' ; then
		output_log "Binary logs value error '$last_binlog_file' '$current_binlog_file'. Can't do an incremental backup."
		OPT_INCR=0
	fi
fi

###
# Verify if there is partition
###
for table in $PARTITION_NAME ; do
	num=$(ls "$datadir/$table#P#"* 2> /dev/null | wc -l)
	if [ -z "$num" ] || [ "$num" -eq 0 ] ; then
		output_log "ERROR: Can't find partition for table '$table'." 1
		exit 1
	fi
done

check_readonly

#############
############# END SANITY CHECK
#############

###########################################
# Beginning
###########################################

if [ "$OPT_INCR" -ne "0" ] ; then
	echo "#####################"
	echo "Incremental backup launched:"
	echo "#####################"

	save_bin_logs=""
	while : ; do
		last_binlog_file=$(($last_binlog_file + 1))
		if [ "$last_binlog_file" -gt "$current_binlog_file" ] ; then
			break
		fi
		file_test=$(echo "$logbin_loc/$logbin_files.$(printf "%06d" $last_binlog_file)")
		if [ -f "$file_test" ] ; then
			save_bin_logs="$save_bin_logs \"$file_test\" "
		fi
	done
	
	if [ "$logbin_activated" -ne "0" ] ; then
		current_logbin_file=$(get_current_logbin_file 0)
		output_log "Save current logbin file = $current_logbin_file"
		echo "$current_logbin_file" > "$SAVE_LAST_DIR/centreondb-smooth-backup.save"
	fi
	
	echo "Save files"
	if [ "$DO_ARCHIVE" -eq "0" ] ; then
		eval cp -pf $save_bin_logs \"$BACKUP_DIR_TOTAL/\"
	else
		eval tar czvf \"$BACKUP_DIR_TOTAL/mysql-$(date +%Y-%m-%d).tar.gz\" $save_bin_logs
	fi
	
	exit 0
fi

echo "#####################"
echo "Full backup launched:"
echo "#####################"
###
# We need to stop if need
###
if [ "$PACEMAKER_ON" = "1" ] ; then
	pcs resource unmanage "$PACEMAKER_RSC_MYSQL"
fi
i=0
output_log "Stopping $SQL_DB_BINARY:" 0 1
mysqladmin --user="$DBROOTUSER" --password="$DBROOTPASSWORD"  shutdown
while ps -o args --no-headers -C "$SQL_DB_BINARY" >/dev/null; do
	if [ "$i" -gt "$STOP_TIMEOUT" ] ; then
		output_log ""
		output_log "ERROR: Can't stop MySQL Server" 1
		exit 1
	fi
	output_log "." 0 1
	sleep 1
	i=$(($i + 1))
done
output_log "OK"

save_timestamp=$(date '+%s')

###
# Do snapshot
###
output_log "Create LVM snapshot"
lvcreate -l $free_pe -s -n dbbackup $lv_name

###
# Start server
###
output_log "Start $SQL_DB_BINARY:"
systemctl start "$SQL_SYSTEMD_SERVICE"

set_readonly

###
# Pacemaker start
###
if [ "$PACEMAKER_ON" = "1" ] ; then
	pcs resource manage "$PACEMAKER_RSC_MYSQL"
	pcs resource cleanup "$PACEMAKER_RSC_MYSQL"
fi

###
# Mount snapshot
###
output_log "Mount LVM snapshot"
mkdir -p "$SNAPSHOT_MOUNT"
TYPEFS_BACKUP=$(df -T "$datadir" | tail -1 | awk -F' ' '{print $(NF-5)}')
[ "$TYPEFS_BACKUP"  = "xfs" ] && MNTOPTIONS="-o nouuid"
mount $MNTOPTIONS /dev/$vg_name/dbbackup "$SNAPSHOT_MOUNT"
if [ $? -eq 0 ]; then
    output_log "Device mounted successfully" 
else
    output_log "Unable to mount device, backup aborted" 
    lvremove -f /dev/$vg_name/dbbackup
    exit 1;
fi

###
# Get Index path
###

if [ "$logbin_activated" -ne "0" ] ; then
	current_logbin_file=$(get_current_logbin_file 0)
	output_log "Save current logbin file = $current_logbin_file"
	echo "$current_logbin_file" > "$SAVE_LAST_DIR/centreondb-smooth-backup.save"
fi

concat_datadir=$(echo "$datadir" | sed "s#^${mount_point}##")

###
# Do DB save
###
ar_exclude_file=""
last_save_time=$(cat "$SAVE_LAST_DIR/$SAVE_LAST_FILE")
if [ "$OPT_TOTALINCR" -eq 1 ] && [ -n "$last_save_time" ] ; then
	minutes=$((($save_timestamp - $last_save_time) / 60))
	# File we don't want
	for table in $PARTITION_NAME ; do
		tmp_dir=$(dirname "$table")
		tmp_name=$(basename "$table")
		tmp_path=$(echo "$SNAPSHOT_MOUNT/$concat_datadir/$tmp_dir" | sed "s#/\+#/#g")
		for tmp_file in $(find "$tmp_path" -name "$tmp_name*" -mmin +$minutes -and -type f); do
			ar_exclude_file="${ar_exclude_file}$tmp_file\n"
		done
	done
fi
### Exclude log-bin
if [ -n "$logbin_files" ] ; then
	# Dont manage logbin other than datadir
	concat_logdir=$(echo "$logbin_loc" | sed "s#^${mount_point}##")
	tmp_path=$(echo "$SNAPSHOT_MOUNT/$concat_logdir" | sed "s#/\+#/#g")
	for tmp_file in $(find "$tmp_path" -maxdepth 1 -name "$logbin_files*" -type f) ; do
		ar_exclude_file="${ar_exclude_file}$tmp_file\n"
	done
fi

save_files=""
tmp_path=$(echo "$SNAPSHOT_MOUNT/$concat_datadir" | sed "s#/\+#/#g")
for tmp_file in $(find "$tmp_path" -type f); do
	tmp_result=$(echo -e "$ar_exclude_file" | awk -v pattern="$tmp_file" 'BEGIN { message="NOK" } $0 == pattern { message="OK"; exit(0) }  END { print message }')
	if [ "$tmp_result" = "NOK" ] ; then
		tmp_file=$(echo "$tmp_file" | sed "s#^$SNAPSHOT_MOUNT/##")
		save_files="$save_files \"$tmp_file\""
	fi
done

output_log "Save files"
cd $SNAPSHOT_MOUNT
if [ "$DO_ARCHIVE" -eq "0" ] ; then
	eval cp --parent -pf $save_files \"$BACKUP_DIR_TOTAL/\"
else
	eval tar czvf \"$BACKUP_DIR_TOTAL/mysql-$(date +%Y-%m-%d).tar.gz\" $save_files
fi
cd -

###
# Suppression du snapshot
###

output_log "Umount and Delete LVM snapshot"
umount "$SNAPSHOT_MOUNT"
lvremove -f /dev/$vg_name/dbbackup

echo "$save_timestamp" > "$SAVE_LAST_DIR/$SAVE_LAST_FILE"

exit 0
