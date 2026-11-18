#!/bin/bash

source /usr/share/centreon-ha/lib/mysql-functions.sh

PARTITION_NAME="centreon_storage/data_bin"
DB_ROOT_USER="root"
DB_ROOT_PASSWORD=""

if ! sql_fetch_db_config ; then
	echo "ERROR: Cannot find the MariaDB configuration." >&2
	exit 1
fi

# MySQL tracks the partition files in its data dictionary: they must not be
# deleted behind its back
if [ "$SQL_IS_MARIADB" -ne 1 ] ; then
	echo "ERROR: This script only supports MariaDB." >&2
	exit 1
fi

####
# Find DATADIR
####
datadir="$SQL_CONF_DATADIR"
if [ -z "$datadir" ] ; then
	echo "ERROR: Can't find MySQL datadir." >&2
	exit 1
fi

echo "MySQL datadir found: $datadir"

status=0

#####
# Check for each
# Request = SELECT PARTITION_NAME FROM information_schema.PARTITIONS WHERE TABLE_NAME='data_bin' AND TABLE_SCHEMA='centreon_storage';
#####
for partition in $PARTITION_NAME; do
	num=$(ls "$datadir/$partition#P#"* 2> /dev/null | wc -l)
	if [ -z "$num" ] || [ "$num" -eq 0 ] ; then
		echo "ERROR: Can't find partition for table '$partition'." >&2
		status=1
		continue
	fi

	tmp_db=$(dirname "$partition")
	tmp_table=$(basename "$partition")

	request="SELECT CONCAT('\"', PARTITION_NAME, '\"') FROM information_schema.PARTITIONS WHERE TABLE_NAME='$tmp_table' AND TABLE_SCHEMA='$tmp_db'"
	password_args=()
	if [ -n "$DB_ROOT_PASSWORD" ] ; then
		password_args=(-p"$DB_ROOT_PASSWORD")
	fi
	# Without the list of partitions, every file would be deleted
	if ! result=$(mysql -B -u "$DB_ROOT_USER" "${password_args[@]}" -e "$request") || [ -z "$result" ] ; then
		echo "ERROR: Can't get the partitions of '$partition', nothing deleted." >&2
		exit 1
	fi

	for file in "$datadir/$partition#P#"* ; do
		partition_part=$(basename "$file" | perl -p -e "s/$tmp_table#P#(.*?)\..*/\1/;")
		if [ -n "$partition_part" ] ; then
			# Test if exist
			tmp_result=$(echo $partition_part | awk -v excludefiles="$result" '{ if (match(excludefiles, "\"" $0 "\"")) {  print "OK"; exit(0) } } { print "NOK"; exit (0) }')
			if [ "$tmp_result" = "NOK" ] ; then
				echo "Remove file '$file'"
				rm -f "$file"
			fi
		fi
	done
done

exit $status
