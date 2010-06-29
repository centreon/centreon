#!/bin/bash
#
# Copyright 2005-2010 MERETHIS
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
# As a special exception, the copyright holders of this program give MERETHIS 
# permission to link this program with independent modules to produce an executable, 
# regardless of the license terms of these independent modules, and to copy and 
# distribute the resulting executable under terms of MERETHIS choice, provided that 
# MERETHIS also meet, for each linked independent module, the terms  and conditions 
# of the license of that module. An independent module is a module which is not 
# derived from this program. If you modify this program, you may extend this 
# exception to your version of the program, but you are not obliged to do so. If you
# do not wish to do so, delete this exception statement from your version.
# 
# For more information : contact@centreon.com
# 
# Module name: Centreon CONFIG BOOKS
# 
# First developpement by : Julien Mathis - Sylvestre Ho
#
#
# Script on Centreon install script by Sylvestre Ho (sho@merethis.com)
# 
# SVN : $URL
# SVN : $Id$
# 

# List of files containing macros
MACRO_FILES="bin/snmpTrapDyn.pl cron/enableTrap.pl cron/purgeCacheTrap.pl centreon-dsm.conf plugins/check_slot_available.pl plugins/check_slot_cache_size.pl"

# Define Centreon Config Books version
NAME="centreon-dsm"
VERSION="1.0"
MODULE=$NAME.$VERSION
LOG_VERSION="$MODULE installation"
FILE_CONF="instCentWeb.conf"
CENTREON_CONF="/etc/centreon/"
MODULE_DIR="www/modules/centreon-dsm/"
INSTALL_DIR_CENTREON="0"
WEB_USER="0"
WEB_GROUP="0"
NAGIOS_USER="0"
NAGIOS_GROUP="0"
NAGIOS_PLUGIN="0"
BACKUP="www/modules/centreon-dsm-backup"
PWD=`pwd`
TEMP=/tmp/install.$$
PROGRAM="install.sh"
DB_USER="0";
DB_HOST="0";
DB_PASSWORD="0";
DB_BASE="0";
DB_CENTSTORAGE="0";
PHP_INI="0";

#---
## {Print help and usage}
##
## @Stdout Usage and Help program
#----
function usage() {
	local program=$PROGRAM
	echo -e "Usage: $program"
	echo -e "  -i\tinstalls Centreon DSM manually"
	echo -e "  -u\tinstall/upgrade Centreon DSM with specify directory with contain $FILE_CONF"	
	exit 1
}

#---
## {Get Centreon install dir and user/group for apache}
#----
function get_centreon_parameters() {

	INSTALL_DIR_CENTREON=`cat $CENTREON_CONF/$FILE_CONF | grep "INSTALL_DIR_CENTREON" | cut -d '=' -f2`;
	LOG_DIR_CENTREON=`cat $CENTREON_CONF/$FILE_CONF | grep "CENTREON_LOG" | cut -d '=' -f2`;
	WEB_USER=`cat $CENTREON_CONF/$FILE_CONF | grep "WEB_USER" | cut -d '=' -f2`;
	WEB_GROUP=`cat $CENTREON_CONF/$FILE_CONF | grep "WEB_GROUP" | cut -d '=' -f2`;
	CENTREON_CONF=`cat $CENTREON_CONF/$FILE_CONF | grep "CENTREON_ETC" | cut -d '=' -f2`;
	NAGIOS_PLUGIN=`cat $CENTREON_CONF/$FILE_CONF | grep "NAGIOS_PLUGIN" | cut -d '=' -f2`;
	NAGIOS_VAR=`cat $CENTREON_CONF/$FILE_CONF | grep "NAGIOS_VAR" | cut -d '=' -f2`;
	CENTREON_VARLIB=`cat $CENTREON_CONF/$FILE_CONF | grep "CENTREON_VARLIB" | cut -d '=' -f2`;
	
	CENTREON_VARLIB=`echo $CENTREON_VARLIB | sed -e 's|\t|""|g'`
	
	if [ "$INSTALL_DIR_CENTREON" != "" ] && [ "$WEB_USER" != "" ] && [ "$WEB_GROUP" != "" ] ; then
		return 1;
	else
		return 0;
	fi
}

#---
## {Get location of instCentWeb.conf file}
##
## @Stdout Error message if user set incorrect directory
## @Stdin Path with must contain $FILE_CONF
#----
function get_centreon_configuration_location() {
	echo ""
	echo "$line"
	echo -e "\tLoad parameters"
	echo "$line"
	err=1
	while [ $err != 0 ]
	do
		echo -e "Please specify the directory that contains \"$FILE_CONF\""
		echo -en "> "
		read temp_read

		if [ -z "$temp_read" ]; then
			echo_failure "The directory does not exist!" "$fail"
		fi

		if [ -d $temp_read ] && [ -f $temp_read/$FILE_CONF ] ; then
			err=0
			CENTREON_CONF=$temp_read
		else
			echo_failure "File \"$FILE_CONF\" does not exist in this directory!" "$fail"
		fi
	done
}

#---
## {Install Centreon DSM Module}
##
## @Stdout Actions realised by function
## @Stderr Log into $LOG_FILE
#----
function install_module() {
	echo ""
	echo "$line"
	echo -e "\tCentreon DSM Module Installation"
	echo "$line"
	
	TEMP_D="/tmp/installation-dsm"
	/bin/mkdir $TEMP_D >> $LOG_FILE 2>> $LOG_FILE
    /bin/mkdir $TEMP_D/www >> $LOG_FILE 2>> $LOG_FILE
    /bin/mkdir $TEMP_D/bin >> $LOG_FILE 2>> $LOG_FILE
    /bin/mkdir $TEMP_D/cron >> $LOG_FILE 2>> $LOG_FILE
    /bin/mkdir $TEMP_D/plugins >> $LOG_FILE 2>> $LOG_FILE

    /bin/cp -Rf www/* $TEMP_D/www >> $LOG_FILE 2>> $LOG_FILE
    /bin/cp -Rf bin/* $TEMP_D/bin >> $LOG_FILE 2>> $LOG_FILE
    /bin/cp -Rf cron/* $TEMP_D/cron >> $LOG_FILE 2>> $LOG_FILE
    /bin/cp -Rf plugins/* $TEMP_D/plugins >> $LOG_FILE 2>> $LOG_FILE
    /bin/cp -Rf centreon-dsm.conf $TEMP_D/ >> $LOG_FILE 2>> $LOG_FILE
	
	/bin/rm -Rf $TEMP_D/install $TEMP_D/*.log

	echo_success "Replacing macros" "$ok"
	for file in $MACRO_FILES
	{
		if [ -e $TEMP_D/$file ]
		then
			replace_macro $TEMP_D/$file
		fi
	}

	echo_success "Setting right" "$ok"
	chmod -R 755 $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE

	echo_success "Setting owner/group" "$ok"
	/bin/chown -R $WEB_USER.$WEB_GROUP $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE

	INSTALL_DIR_MODULE=$INSTALL_DIR_CENTREON/$MODULE_DIR

	if [ -d $INSTALL_DIR_MODULE ] ; then
		if [ -d  $INSTALL_DIR_CENTREON/$BACKUP ] ; then
			echo_success "Delete old Centreon DSM backup" "$ok"
			/bin/rm -Rf $INSTALL_DIR_CENTREON/$BACKUP/*
		else
			echo_success "Create a directory to backup old files" "$ok"
			/bin/mkdir $INSTALL_DIR_CENTREON/$BACKUP
		fi
		
		echo_success "Backup old installation" "$ok"
		mv $INSTALL_DIR_MODULE/* $INSTALL_DIR_CENTREON/$BACKUP >> $LOG_FILE 2>> $LOG_FILE		
	fi

	if [ ! -d $INSTALL_DIR_MODULE ] ; then
		echo_success "Create module directory" "$ok"
		/bin/mkdir $INSTALL_DIR_MODULE >> $LOG_FILE 2>> $LOG_FILE
		/bin/chown -R $WEB_USER.$WEB_GROUP $INSTALL_DIR_MODULE >> $LOG_FILE 2>> $LOG_FILE
		/bin/chmod -R 755 $INSTALL_DIR_MODULE >> $LOG_FILE 2>> $LOG_FILE	
	fi

	echo_success "Copying module" "$ok"
    /bin/cp -Rf --preserve $TEMP_D/www/* $INSTALL_DIR_CENTREON/www >> $LOG_FILE 2>> $LOG_FILE

    echo_success "Copying Handler" "$ok"
    /bin/cp -Rf --preserve $TEMP_D/bin/* $INSTALL_DIR_CENTREON/bin >> $LOG_FILE 2>> $LOG_FILE

    echo_success "Copying Cron script" "$ok"
    /bin/cp -Rf --preserve $TEMP_D/cron/* $INSTALL_DIR_CENTREON/cron >> $LOG_FILE 2>> $LOG_FILE

    echo_success "Copying Plugins" "$ok"
    /bin/cp -Rf --preserve $TEMP_D/plugins/* $NAGIOS_PLUGIN/ >> $LOG_FILE 2>> $LOG_FILE

	echo_success "Install Cron config File" "$ok"
	/bin/cp -Rf --preserve $TEMP_D/centreon-dsm.conf /etc/cron.d/ >> $LOG_FILE 2>> $LOG_FILE

	echo_success "Restart crond" "$ok"
	/etc/init.d/crond restart >> $LOG_FILE 2>> $LOG_FILE

	echo_success "Delete temp install directory" "$ok"
	/bin/rm -Rf $TEMP_D $TEMP >> $LOG_FILE 2>> $LOG_FILE

	echo_success "\nThe $LOG_VERSION is finished" "$ok"
	echo -e  "See README and the log file for more details."
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
INSTALL_DIR="$BASE_DIR/install"
export INSTALL_DIR

# init variables
line="------------------------------------------------------------------------"
export line

## log default vars 
. $INSTALL_DIR/vars

## load all functions used in this script
. $INSTALL_DIR/functions

## Define a default log file
LOG_FILE="$PWD/install.log"

## Valid if you are root 
USERID=`id -u`
if [ "$USERID" != "0" ]; then
    echo "You must exec with root user"
    exit 1
fi

_tmp_install_opts="0"
silent_install="0"
user_conf=""

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
export silent_install user_install_vars CENTREON_CONF cinstall_opts inst_upgrade_dir

## init LOG_FILE
# backup old log file...
if [ -e "$LOG_FILE" ] ; then
	mv "$LOG_FILE" "$LOG_FILE.`date +%Y%m%d-%H%M%S`"
fi

# Clean (and create) my log file
${CAT} << __EOL__ > "$LOG_FILE"
__EOL__

# Init GREP,CAT,SED,CHMOD,CHOWN variables
define_specific_binary_vars

${CAT} << __EOT__
###############################################################################
#                                                                             #
#              Module : Centreon DSM version $VERSION                #
#                                                                             #
###############################################################################
__EOT__

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
	echo_info "Please check on your binaries and try again"
	exit 1
fi

if [ "$silent_install" -eq 0 ] ; then
	get_centreon_configuration_location;
	get_centreon_parameters;
	load_parameters=$?
	
	if [ "$load_parameters" -eq 1 ] ; then
		install_module;
	else
		echo -e "\nUnable to load all parameters in \"$FILE_CONF\""
		exit 1
	fi
fi

if [ "$silent_install" -eq 1 ] ; then
	if [ -d $user_conf ] && [ -f $user_conf/$FILE_CONF ] ; then
		CENTREON_CONF=$user_conf/;
		get_centreon_parameters;
		load_parameters=$?
		if [ "$load_parameters" -eq 1 ] ; then
			echo_success "Parameters was loaded with success" "$ok"
			install_module;
		else
			echo_failure "Unable to load all parameters in \"$FILE_CONF\"" "$fail"
			exit 1
		fi
	else
		echo_failure "File \"$FILE_CONF\" does not exist in your specified directory!" "$fail"
		exit 1
	fi
fi

${CAT} << __EOT__
###############################################################################
#                                                                             #
#      Please go to the URL : http://your-server/centreon/                    #
#                   	to finish the setup                                   #
#                                                                             #
#                                                                             #
###############################################################################
__EOT__

exit 0