#!/bin/bash 
## VARS
yes="y"
no="n"
ok="OK"
fail="FAIL"
passed="PASSED"
warning="WARNING"
critical="CRITICAL"

# Init binary to empty to use pathfind or manual define
GREP=""
CAT=""
SED=""
CHMOD=""
CHOWN=""
CRON=/etc/init.d/cron

## COLOR FUNCTIONS
RES_COL="60"
MOVE_TO_COL="\\033[${RES_COL}G"
SETCOLOR_INFO="\\033[1;38m"
SETCOLOR_SUCCESS="\\033[1;32m"
SETCOLOR_FAILURE="\\033[1;31m"
SETCOLOR_WARNING="\\033[1;33m"
SETCOLOR_NORMAL="\\033[0;39m"

#----
## print info message
## add info message to log file
## @param	message info
## @param	type info (ex: INFO, username...)
## @Stdout	info message
## @Globals	LOG_FILE
#----
function echo_info() {
    echo -e "${1}${MOVE_TO_COL}${SETCOLOR_INFO}${2}${SETCOLOR_NORMAL}" 
    echo -e "$1 : $2" >> $LOG_FILE
}

#----
## print success message
## add success message to log file
## @param	message
## @param	word to specify success (ex: OK)
## @Stdout	success message
## @Globals	LOG_FILE
#----
function echo_success() {
    echo -e "${1}${MOVE_TO_COL}${SETCOLOR_SUCCESS}${2}${SETCOLOR_NORMAL}" 
    echo -e "$1 : $2" >> $LOG_FILE
}

#----
## print failure message
## add failure message to log file
## @param	message
## @param	word to specify failure (ex: fail)
## @Stdout	failure message
## @Globals	LOG_FILE
#----
function echo_failure() {
    echo -e "${1}${MOVE_TO_COL}${SETCOLOR_FAILURE}${2}${SETCOLOR_NORMAL}"
    echo -e "$1 : $2" >> $LOG_FILE
}

#----
## print passed message
## add passed message to log file
## @param	message
## @param	word to specify pass (ex: passed)
## @Stdout	passed message
## @Globals	LOG_FILE
#----
function echo_passed() {
    echo -e "${1}${MOVE_TO_COL}${SETCOLOR_WARNING}${2}${SETCOLOR_NORMAL}"
    echo -e "$1 : $2" >> $LOG_FILE
}

#----
## print warning message
## add warning message to log file
## @param	message
## @param	word to specify warning (ex: warn)
## @Stdout	warning message
## @Globals	LOG_FILE
#----
function echo_warning() {
    echo -e "${1}${MOVE_TO_COL}${SETCOLOR_WARNING}${2}${SETCOLOR_NORMAL}"
    echo -e "$1 : $2" >> $LOG_FILE
}

#----
## add message on log file
## @param	type of message level (debug, info, ...)
## @param	message
## @Globals	LOG_FILE
#----
function log() {
	local program="$0"
	local type="$1"
	shift
	local message="$@"
	echo -e "[$program]:$type: $message" >> $LOG_FILE
}

#----
## define a specific variables for grep,cat,sed,... binaries
## This functions was been use in first line on your script
## @return 0	All is't ok
## @return 1	problem with one variable
## @Globals	GREP, CAT, SED, CHMOD, CHOWN
#----
function define_specific_binary_vars() {
	local vars_bin="GREP CAT SED CHMOD CHOWN RM MKDIR CP MV"
	local var_bin_tolower=""
	for var_bin in $vars_bin ; 
	do
		if [ -z $(eval echo \$$var_bin) ] ; then
			var_bin_tolower="$(echo $var_bin | tr [:upper:] [:lower:])"
			pathfind_ret "$var_bin_tolower" "$(echo -n $var_bin)"
			if [ "$?" -eq 0 ] ; then
				eval "$var_bin='$(eval echo \$$var_bin)/$var_bin_tolower'"
				export $(echo $var_bin)
				log "INFO" "$var_bin=$(eval echo \$$var_bin)"
			else
				return 1
			fi
		fi
	done
	return 0
}

#----
## find in $PATH if binary exist
## @param	file to test
## @return 0	found
## @return 1	not found
## @Globals	PATH
#----
function pathfind() {
	OLDIFS="$IFS"
	IFS=:
	for p in $PATH; do
		if [ -x "$p/$*" ]; then
			IFS="$OLDIFS"
			return 0
		fi
	done
	IFS="$OLDIFS"
	return 1
}

#----
## find in $PATH if binary exist and return dirname
## @param	file to test
## @param	global variable to set a result
## @return 0	found
## @return 1	not found
## @Globals	PATH
#----
function pathfind_ret() {
	local bin=$1
	local var_ref=$2
	local OLDIFS="$IFS"
	IFS=:
	for p in $PATH; do
		if [ -x "$p/$bin" ]; then
			IFS="$OLDIFS"
			eval $var_ref=$p
			return 0
		fi
	done
	IFS="$OLDIFS"
	return 1
}

#----
## make a question with yes/no possiblity
## use "no" response by default
## @param	message to print
## @param 	default response (default to no)
## @return 0 	yes
## @return 1 	no
#----
function yes_no_default() {
	local message=$1
	local default=${2:-$no}
	local res="not_define"
	while [ "$res" != "$yes" ] && [ "$res" != "$no" ] && [ ! -z "$res" ] ; do
		echo -e "\n$message\n[y/n], default to [$default]:"
		echo -en "> "
		read res
		[ -z "$res" ] && res="$default"
	done
	if [ "$res" = "$yes" ] ; then 
		return 0
	else 
		return 1
	fi
}

#---
## {Get Centreon install dir and user/group for apache}
#----
function get_centreon_parameters() {
	INSTALL_DIR_CENTREON=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "INSTALL_DIR_CENTREON" | cut -d '=' -f2`;
	WEB_USER=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "WEB_USER" | cut -d '=' -f2`;
	WEB_GROUP=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "WEB_GROUP" | cut -d '=' -f2`;
	NAGIOS_PLUGIN=`${CAT} $CENTREON_CONF/$FILE_CONF_CENTPLUGIN | ${GREP} "NAGIOS_PLUGIN" | cut -d '=' -f2`;
	NAGIOS_USER=`${CAT} $CENTREON_CONF/$FILE_CONF_CENTPLUGIN | ${GREP} "NAGIOS_USER" | cut -d '=' -f2`;
	NAGIOS_GROUP=`${CAT} $CENTREON_CONF/$FILE_CONF_CENTPLUGIN | ${GREP} "NAGIOS_GROUP" | cut -d '=' -f2`;
	NAGIOS_VAR=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "NAGIOS_VAR" | cut -d '=' -f2`;
	CENTREON_LOG=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_LOG" | cut -d '=' -f2`;
	CENTREON_VARLIB=`${CAT} $CENTREON_CONF/instCentCore.conf | ${GREP} "CENTREON_VARLIB" | cut -d '=' -f2`;

	RESULT=0
	if [ "$INSTALL_DIR_CENTREON" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$WEB_USER" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$WEB_GROUP" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$NAGIOS_PLUGIN" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$NAGIOS_USER" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$NAGIOS_GROUP" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$NAGIOS_VAR" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$CENTREON_LOG" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$CENTREON_VARLIB" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	
	if [ "$RESULT" -eq 9 ]; then 
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
		echo -e "Please specify the directory with contain \"$FILE_CONF\""
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
## {Install centreon-es Module}
##
## @Stdout Actions realised by function
## @Stderr Log into $LOG_FILE
function install_module() {
	INSTALL_DIR_MODULE=$INSTALL_DIR_CENTREON/$MODULE_DIR

	echo ""
	echo "$line"
	echo -e "\tBackup $NAME"
	echo "$line"

	if [ -d $INSTALL_DIR_MODULE ] ; then
		if [ -d  $INSTALL_DIR_CENTREON/$BACKUP ] ; then
			${RM} -Rf $INSTALL_DIR_CENTREON/$BACKUP/*
			if [ "$?" -eq 0 ] ; then
				echo_success "Delete old $NAME backup" "$ok"
			else 
				echo_failure "Delete old $NAME backup" "$fail"
				exit 1
			fi
		else
			${MKDIR} $INSTALL_DIR_CENTREON/$BACKUP
			if [ "$?" -eq 0 ] ; then
				echo_success "Create a directory to backup old files" "$ok"
			else 
				echo_failure "Create a directory to backup old files" "$fail"
				exit 1
			fi
		fi

		${MV} $INSTALL_DIR_MODULE $INSTALL_DIR_CENTREON/$BACKUP >> $LOG_FILE 2>> $LOG_FILE
		if [ "$?" -eq 0 ] ; then
			echo_success "Backup old installation" "$ok"
		else 
			echo_failure "Backup old installation" "$fail"
			exit 1
		fi
	fi

	echo ""
	echo "$line"
	echo -e "\tInstall $NAME web interface"
	echo "$line"
	TEMP_D="/tmp/Install_module"
	${MKDIR} -p $TEMP_D/www >> $LOG_FILE 2>> $LOG_FILE

	${CP} -Rf www/modules/centreon-dsm/* $TEMP_D/www >> $LOG_FILE 2>> $LOG_FILE

	${CHMOD} -R 755 $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting right" "$ok"
	else 
		echo_failure "Setting right" "$fail"
		exit 1
	fi	

	${CHOWN} -R $WEB_USER.$WEB_GROUP $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting owner/group" "$ok"
	else 
		echo_failure "Setting owner/group" "$fail"
		exit 1
	fi

	${MKDIR} -p $INSTALL_DIR_CENTREON/$MODULE_DIR >> $LOG_FILE 2>> $LOG_FILE
	${CP} -Rf --preserve $TEMP_D/www/* $INSTALL_DIR_CENTREON/$MODULE_DIR >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Copying module" "$ok"
	else 
		echo_failure "Copying module" "$fail"
		exit 1
	fi

	${RM} -Rf $TEMP_D >> $LOG_FILE 2>> $LOG_FILE

	echo ""
	echo "$line"
	echo -e "\tInstall $NAME binaries"
	echo "$line"
	TEMP_D="/tmp/Install_module"
	${MKDIR} -p $TEMP_D/bin >> $LOG_FILE 2>> $LOG_FILE

	${CP} -Rf bin/* $TEMP_D/bin >> $LOG_FILE 2>> $LOG_FILE

	${MKDIR} -p $CENTREON_VARLIB/centreon-dsm >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Creating path for temporary files" "$ok"
	else 
		echo_failure "Creating path for temporary files" "$fail"
		exit 1
	fi
	
	RESULT=0
	FILE="bin/snmpTrapDyn.pl"
	${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="bin/snmpTrapDyn.pl"
	${SED} -i -e 's|@CENTREON_LOG@|'"$CENTREON_LOG"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="bin/snmpTrapDyn.pl"
	${SED} -i -e 's|@CENTREON_VARLIB@|'"$CENTREON_VARLIB"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="bin/snmpTrapDyn.pl"
	${SED} -i -e 's|@NAGIOS_CMD@|'"$NAGIOS_VAR/rw"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	if [ "$RESULT" -eq 4 ] ; then
		echo_success "Changing macros" "$ok"
	else 
		echo_failure "Changing macros" "$fail"
		exit 1
	fi

	${CHMOD} -R 755 $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting right" "$ok"
	else 
		echo_failure "Setting right" "$fail"
		exit 1
	fi	

	${CHOWN} -R $WEB_USER.$WEB_GROUP $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting owner/group" "$ok"
	else 
		echo_failure "Setting owner/group" "$fail"
		exit 1
	fi

	${CP} -Rf --preserve $TEMP_D/bin/* $INSTALL_DIR_CENTREON/bin/. >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Copying module" "$ok"
	else 
		echo_failure "Copying module" "$fail"
		exit 1
	fi

	${RM} -Rf $TEMP_D >> $LOG_FILE 2>> $LOG_FILE

	echo ""
	echo "$line"
	echo -e "\tInstall $NAME cron"
	echo "$line"
	TEMP_D="/tmp/Install_module"
	${MKDIR} -p $TEMP_D/cron >> $LOG_FILE 2>> $LOG_FILE

	${CP} -Rf cron/* $TEMP_D/cron >> $LOG_FILE 2>> $LOG_FILE

	RESULT=0
	FILE="cron/enableTrap.pl"
	${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="cron/purgeCacheTrap.pl"
	${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="cron/enableTrap.pl"
	${SED} -i -e 's|@CENTREON_VARLIB@|'"$CENTREON_VARLIB"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="cron/purgeCacheTrap.pl"
	${SED} -i -e 's|@CENTREON_VARLIB@|'"$CENTREON_VARLIB"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="cron/purgeCacheTrap.pl"
	${SED} -i -e 's|@CENTREON_LOG@|'"$CENTREON_LOG"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="cron/enableTrap.pl"
	${SED} -i -e 's|@NAGIOS_CMD@|'"$NAGIOS_VAR/rw"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="cron/purgeCacheTrap.pl"
	${SED} -i -e 's|@NAGIOS_CMD@|'"$NAGIOS_VAR/rw"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	if [ "$RESULT" -eq 7 ] ; then
		echo_success "Changing macros" "$ok"
	else 
		echo_failure "Changing macros" "$fail"
		exit 1
	fi

	${CHMOD} -R 755 $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting right" "$ok"
	else 
		echo_failure "Setting right" "$fail"
		exit 1
	fi	

	${CHOWN} -R $WEB_USER.$WEB_GROUP $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting owner/group" "$ok"
	else 
		echo_failure "Setting owner/group" "$fail"
		exit 1
	fi

	${CP} -Rf --preserve $TEMP_D/cron/* $INSTALL_DIR_CENTREON/cron/. >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Copying module" "$ok"
	else 
		echo_failure "Copying module" "$fail"
		exit 1
	fi

	${RM} -Rf $TEMP_D >> $LOG_FILE 2>> $LOG_FILE

	echo ""
	echo "$line"
	echo -e "\tInstall $NAME plugins"
	echo "$line"
	TEMP_D="/tmp/Install_module"
	${MKDIR} -p $TEMP_D/plugins >> $LOG_FILE 2>> $LOG_FILE

	${CP} -Rf plugins/* $TEMP_D/plugins >> $LOG_FILE 2>> $LOG_FILE

	RESULT=0
	FILE="plugins/check_slot_available.pl"
	${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="plugins/check_slot_cache_size.pl"
	${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	FILE="plugins/check_slot_cache_size.pl"
	${SED} -i -e 's|@CENTREON_VARLIB@|'"$CENTREON_VARLIB"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	if [ "$RESULT" -eq 3 ] ; then
		echo_success "Changing macros" "$ok"
	else 
		echo_failure "Changing macros" "$fail"
		exit 1
	fi

	${CHMOD} -R 755 $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting right" "$ok"
	else 
		echo_failure "Setting right" "$fail"
		exit 1
	fi	

	${CHOWN} -R $NAGIOS_USER.$NAGIOS_GROUP $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting owner/group" "$ok"
	else 
		echo_failure "Setting owner/group" "$fail"
		exit 1
	fi

	${CP} -Rf --preserve $TEMP_D/plugins/* $NAGIOS_PLUGIN/. >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Copying module" "$ok"
	else 
		echo_failure "Copying module" "$fail"
		exit 1
	fi

	${RM} -Rf $TEMP_D >> $LOG_FILE 2>> $LOG_FILE
	
	echo ""
	echo "$line"
	echo -e "\tIntegrate $NAME cron"
	echo "$line"

	FILE="centreon-dsm.conf"
	${SED} -i -e 's|@INSTALL_DIR_CENTREON@|'"$INSTALL_DIR_CENTREON"'|g' $FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	${CP} -Rf centreon-dsm.conf /etc/cron.d/centreon-dsm >> $LOG_FILE 2>> $LOG_FILE

	${CHMOD} -R 644 /etc/cron.d/centreon-dsm >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting right" "$ok"
	else 
		echo_failure "Setting right" "$fail"
		exit 1
	fi	

	${CHOWN} -R root:root /etc/cron.d/centreon-dsm >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting owner/group" "$ok"
	else 
		echo_failure "Setting owner/group" "$fail"
		exit 1
	fi

	echo ""
	echo "$line"
	echo -e "\tEnd of $NAME installation"
	echo "$line"
	echo_success "\n\nInstallation of $NAME is finished" "$ok"
	echo -e  "See README and the log file for more details."
}
