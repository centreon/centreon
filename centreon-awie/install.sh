#!/bin/bash
#
# Copyright 2005-2018 CENTREON
# Centreon is developped by : Julien Mathis and Romain Le Merlus under
# GPL Licence 2.0.
# 
# This program is free software; you can redistribute it and/or modify it under 
# the terms of the GNU General Public License as published by the Free Software 
# Foundation ; either version 2 of the License.
# 
# This program is distributed in the hope that it will be useful, but WITHOUT ANY
# WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A 
# PARTICULAR PURPOSE. See the GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License along with 
# this program; if not, see <http://www.gnu.org/licenses>.
# 
# Linking this program statically or dynamically with other modules is making a 
# combined work based on this program. Thus, the terms and conditions of the GNU 
# General Public License cover the whole combination.
# 
# As a special exception, the copyright holders of this program give CENTREON 
# permission to link this program with independent modules to produce an executable, 
# regardless of the license terms of these independent modules, and to copy and 
# distribute the resulting executable under terms of CENTREON choice, provided that 
# CENTREON also meet, for each linked independent module, the terms  and conditions 
# of the license of that module. An independent module is a module which is not 
# derived from this program. If you modify this program, you may extend this 
# exception to your version of the program, but you are not obliged to do so. If you
# do not wish to do so, delete this exception statement from your version.
# 
# For more information : contact@centreon.com
# 

# Define module information
RNAME="Centreon Web Import/Export"
NAME="centreon-awie"
VERSION="1.0.1"
FORGE_URL="https://github.com/centreon/centreon-awie"
CRON_FILE="myproject.cron"
MODULE_DIR="www/modules/"$NAME
MODULE=$RNAME.$VERSION

# Define vars
LINE_SIZE=80
LOG_VERSION="Centreon Module $MODULE installation"
FILE_CONF="instCentWeb.conf"
CENTREON_CONF="/etc/centreon/"
CENTSTORAGE_FILE_CONF="instCentStorage.conf"
CENTPLUGINS_FILE_CONF="instCentPlugins.conf"
INSTALL_DIR_CENTREON="0"
CENTREON_LOG="0"
CENTREON_VARLIB="0"
WEB_USER="0"
WEB_GROUP="0"
NAGIOS_USER="0"
NAGIOS_GROUP="0"
NAGIOS_PLUGIN="0"
NAGIOS_VAR="0"
PWD=`pwd`
TEMP=/tmp/install.$$
_tmp_install_opts="0"
silent_install="0"
user_conf=""

#---
## {Print help and usage}
##
## @Stdout Usage and Help program
#----
function usage() {
	local program=$PROGRAM
	echo -e "Usage: $program"
	echo -e "  -i\tinstall/update $RNAME manually"
	echo -e "  -u\tinstall/upgrade $RNAME with specify directory with contain $FILE_CONF"
	exit 1
}


### Main

# define where is a centreon source 
BASE_DIR=$(dirname $0)
## set directory
BASE_DIR=$( cd $BASE_DIR; pwd )
export BASE_DIR
if [ -z "${BASE_DIR#/}" ] ; then
	echo -e "I think it is not right to have Centreon source on slash"
	exit 1
fi

INSTALL_DIR="$BASE_DIR/libinstall"
export INSTALL_DIR

# init variables
line="------------------------------------------------------------------------"
export line

## load all functions used in this script
. $INSTALL_DIR/functions.sh

## Define a default log file
LOG_DIR="$BASE_DIR/log"
LOG_FILE="$PWD/install.log"

## Valid if you are root 
USERID=`id -u`
if [ "$USERID" != "0" ]; then
    echo -e "You must exec with root user"
    exit 1
fi

## Getopts
while getopts "iu:h" Options
do
	case ${Options} in
		i )	_tmp_install_opts="1"
			silent_install="0"
			;;
		u )	_tmp_install_opts="1"
			silent_install="1"
			user_conf="${OPTARG%/}"
			;;
		\?|h)	usage ; 
			exit 0 
			;;
		* )	usage ; 
			exit 1 
			;;
	esac
done

if [ "$_tmp_install_opts" -eq 0 ] ; then
	usage
	exit 1
fi

#Export variable for all programs
export silent_install CENTREON_CONF  

## init LOG_FILE
# backup old log file...
if [ -e "$LOG_FILE" ] ; then
	mv "$LOG_FILE" "$LOG_FILE.`date +%Y%m%d-%H%M%S`"
fi
# Clean (and create) my log file
${CAT} << __EOL__ > "$LOG_FILE"
__EOL__

# Init GREP,CAT,SED,CHMOD,CHOWN variables
define_specific_binary_vars;

print_header;

## Test all binaries
BINARIES="rm cp mv ${CHMOD} ${CHOWN} echo more mkdir find ${GREP} ${CAT} ${SED}"

echo "$line"
echo -e "\tChecking all needed binaries"
echo "$line"

binary_fail="0"
# For the moment, I check if all binary exists in path.
# After, I must look a solution to use complet path by binary
for binary in $BINARIES; do
	if [ ! -e ${binary} ] ; then 
		pathfind "$binary"
		if [ "$?" -eq 0 ] ; then
			echo_success "${binary}" "$ok"
		else 
			echo_failure "${binary}" "$fail"
			log "ERR" "\$binary not found in \$PATH"
			binary_fail=1
		fi
	else
		echo_success "${binary}" "$ok"
	fi
done

# Script stop if one binary wasn't found
if [ "$binary_fail" -eq 1 ] ; then
	echo_info "Please check fail binary and retry"
	exit 1
fi

if [ "$silent_install" -eq 0 ] ; then
	get_centreon_configuration_location;
else
	if [ -d $user_conf ] && [ -f $user_conf/$FILE_CONF ] ; then
		CENTREON_CONF=$user_conf/;
	else
		echo_failure "File \"$FILE_CONF\" does not exist in your specified directory!" "$fail"
		exit 1
	fi
fi

get_centreon_parameters;
load_parameters=$?
if [ "$load_parameters" -eq 1 ] ; then
	echo_success "Parameters was loaded with success" "$ok"
	install_module;
else
	echo_failure "Unable to load all parameters in \"$FILE_CONF\"" "$fail"
	exit 1
fi

print_footer;

exit 0
