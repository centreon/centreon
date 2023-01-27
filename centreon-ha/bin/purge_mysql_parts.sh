#!/bin/bash

PARTITION_NAME="centreon_storage/data_bin"
DB_ROOT_USER="root"
DB_ROOT_PASSWORD=""

process=$(ps -o args --no-headers -C mysqld)
started=0

####
# Find DATADIR
####
if [ -n "$process" ] ; then
	datadir=$(echo "$process" | awk '{ for (i = 1; i < NF; i++) { if (match($i, "--datadir")) { print $i } } }' | awk -F\= '{ print $2 }')
	etc_file=$(echo "$process" | awk '{ for (i = 1; i < NF; i++) { if (match($i, "--defaults-file")) { print $i } } }' | awk -F\= '{ print $2 }')
	started=1
fi
if [ -z "$etc_file" ] ; then
	etc_file="/etc/my.cnf"
fi
if [ -z "$datadir" ] ; then
	datadir=$(cat "$etc_file" | grep -E '^datadir' | awk -F\= '{ print $2 }')
fi
if [ -z "$datadir" ] ; then
	echo "ERROR: Can't find MySQL datadir." >&2
	exit 1
fi
### Avoid datadir is a symlink (get the absolute path)
datadir=$(cd "$datadir"; pwd -P)

echo "MySQL datadir found: $datadir"

#####
# Check for each
# Request = SELECT PARTITION_NAME FROM information_schema.PARTITIONS WHERE TABLE_NAME='data_bin' AND TABLE_SCHEMA='centreon_storage';
#####
for partition in $PARTITION_NAME; do
	num=$(ls "$datadir/$partition#P#"* 2> /dev/null | wc -l)
	if [ -z "$num" ] || [ "$num" -eq 0 ] ; then
		echo "ERROR: Can't find partition for table '$table'." >&2
		continue
	fi

	tmp_db=$(dirname "$partition")
	tmp_table=$(basename "$partition")

	request="SELECT CONCAT('\"', PARTITION_NAME, '\"') FROM information_schema.PARTITIONS WHERE TABLE_NAME='$tmp_table' AND TABLE_SCHEMA='$tmp_db'"
	if [ -z "$DB_ROOT_PASSWORD" ] ; then
		result=$(mysql -B -u "$DB_ROOT_USER" -e "$request")
	else
		result=$(mysql -B -u "$DB_ROOT_USER" -p"$DB_ROOT_PASSWORD" -e "$request")
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

exit 0
