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
## Define OS
## check on etc to find a specific file <p>
## Debian, Suse, Redhat, FreeBSD
## @param	variable to set a result
## @return 0	OS found
## @return 1	OS not found
#----
function find_OS() {
	local distrib=$1
	local dist_found=""
	local system=""
	local lsb_release=""
	system="$(uname -s)"
	if [ "$system" = "Linux" ] ; then
		if [ "$(pathfind lsb_release; echo $?)" -eq "0" ] ; then
			lsb_release="$(lsb_release -i -s)"
		else
			lsb_release="NOT_FOUND"
		fi
		if [ "$lsb_release" = "Debian" ] || \
			[ "$lsb_release" = "Ubuntu" ] || \
			[ -e "/etc/debian_version" ] ; then
			dist_found="DEBIAN"
			log "INFO" "$(gettext "GNU/Linux Debian Distribution")"
		elif [ "$lsb_release" = "SUSE LINUX" ] || \
			[ -e "/etc/SuSE-release" ] ; then
			dist_found="SUSE"
			log "INFO" "$(gettext "GNU/Linux Suse Distribution")"
                elif [ "$lsb_release" = "openSUSE project" ] || \
			[ -e "/etc/SuSE-release" ] ; then
			dist_found="SUSE"
			log "INFO" "$(gettext "GNU/openSUSE Distribution")"
		elif [ "$lsb_release" = "RedHatEnterpriseES" ] || \
			[ "$lsb_release" = "Fedora" ] || \
			[ -e "/etc/redhat-release" ] ; then
			dist_found="REDHAT"
			log "INFO" "$(gettext "GNU/Linux Redhat Distribution")"
		else
			dist_found="NOT_FOUND"
			log "INFO" "$(gettext "GNU/Linux distribution not found")"
			return 1
		fi
	elif [ "$system" = "FreeBSD" ] ; then
		dist_found="FREEBSD"
		log "INFO" "$(gettext "FreeBSD System")"
	elif [ "$system" = "AIX" ] ; then
		dist_found="AIX"
		log "INFO" "$(gettext "AIX System")"
	elif [ "$system" = "SunOS" ] ; then
		dist_found="SUNOS"
		log "INFO" "$(gettext "SunOS System")"
	else
		dist_found="NOT_FOUND"
		log "INFO" "$(gettext "System not found")"
	fi

	eval $distrib=$dist_found
	return 0
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
    INSTALL_DIR_CENTREON=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "INSTALL_DIR_CENTREON" | cut -d '=' -f2`
    WEB_USER=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "WEB_USER" | cut -d '=' -f2`
    WEB_GROUP=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "WEB_GROUP" | cut -d '=' -f2`
    CENTREON_LOG=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_LOG" | cut -d '=' -f2`
    CENTREON_USER=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_USER" | cut -d '=' -f2`
    CENTREON_GROUP=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_GROUP" | cut -d '=' -f2`
    CENTREON_VARLIB=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_VARLIB" | cut -d '=' -f2`
    CENTREON_BINDIR=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_BINDIR" | cut -d '=' -f2`
    CENTREON_ETC=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_ETC" | cut -d '=' -f2`

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
    if [ "$CENTREON_LOG" != "" ] ; then
    RESULT=`expr $RESULT + 1`
    fi
    if [ "$CENTREON_VARLIB" != "" ] ; then
        RESULT=`expr $RESULT + 1`
    fi
    if [ "$CENTREON_ETC" != "" ] ; then
        RESULT=`expr $RESULT + 1`
    fi

    if [ "$RESULT" -eq 6 ]; then
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

function install_module_cron_files() {
	echo ""
	echo "$line"
	echo -e "\tInstall $NAME cron"
	echo "$line"

	CRON_NAME=centreon-dsm
	if [ -f /etc/cron.d/$CRON_NAME ] ; then
		${RM} -Rf "/etc/cron.d/$CRON_NAME"
		if [ $? -eq 0 ]; then
			echo_success "Removal of the old dsm cron:" "$ok"
		else
			echo_failure "Removal of the old dsm cron:" "$fail"
			echo -e "Please delete cron file: /etc/cron.d/$CRON_NAME and reload install script"
			exit 1
		fi
	fi

	CRON_MODULE=$INSTALL_DIR_CENTREON/$MODULE_DIR

	FILE="cron/centreon-dsm"

	${SED} -i -e 's|@CENTREON_DSM_PATH@|'"$CRON_MODULE"'|g' $FILE 2>> $LOG_FILE
	${SED} -i -e 's|@CENTREON_LOG@|'"$CENTREON_LOG"'|g' $FILE 2>> $LOG_FILE
    ${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Changing macro" "$ok"
	else
		echo_failure "Changing macro" "$fail"
		exit 1
	fi

    ${CP} -Rf www/modules/centreon-dsm/cron/centreon_dsm_purge.pm $CENTREON_CONF/centreon_dsm_purge.pm >> $LOG_FILE 2>> $LOG_FILE

	${CP} cron/centreon-dsm /etc/cron.d/$CRON_NAME >> $LOG_FILE 2>> $LOG_FILE

	${CHMOD} 644 /etc/cron.d/$CRON_NAME >> $LOG_FILE 2>> $LOG_FILE
	${CHOWN} root:root /etc/cron.d/$CRON_NAME >> $LOG_FILE 2>> $LOG_FILE
	if [ $? -eq 0 ]; then
		echo_success "Copy cron in cron.d directory:" "$ok"
	else
		echo_failure "Copy cron in cron.d directory:" "$fail"
		exit 1
	fi

    ${RM} -Rf $INSTALL_DIR_CENTREON/$MODULE_DIR/cron/centreon_dsm_purge.pm >> $LOG_FILE 2>> $LOG_FILE
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

    install_module_cron_files

	echo ""
	echo "$line"
	echo -e "\tInstall $NAME binaries"
	echo "$line"
	TEMP_D="/tmp/Install_module"

	${MKDIR} -p $TEMP_D/bin >> $LOG_FILE 2>> $LOG_FILE
	${MKDIR} -p $TEMP_D/libinstall >> $LOG_FILE 2>> $LOG_FILE

	${CP} -Rf bin/* $TEMP_D/bin >> $LOG_FILE 2>> $LOG_FILE
	# Prepare init.d and default-sysconfig/dsmd
    DISTRIB=""
    find_OS "DISTRIB"
    if [ "$DISTRIB" = "DEBIAN" ]; then
	   ${CP} -Rf libinstall/debian/init.d.dsmd $TEMP_D/libinstall/ >> $LOG_FILE 2>> $LOG_FILE
 	   ${CP} -Rf libinstall/debian/dsmd.default $TEMP_D/libinstall/ >> $LOG_FILE 2>> $LOG_FILE
    else
	   ${CP} -Rf libinstall/redhat/dsmd.systemd $TEMP_D/libinstall/ >> $LOG_FILE 2>> $LOG_FILE
 	   ${CP} -Rf libinstall/redhat/dsmd.sysconfig $TEMP_D/libinstall/ >> $LOG_FILE 2>> $LOG_FILE
    fi


	################################################################
	## DSMD client
	#
	RESULT=0
	FILE="bin/dsmclient.pl"
	${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	################################################################
	## DSMD daemon
	#
	FILE="bin/dsmd.pl"
	${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_CONF"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	if [ "$RESULT" -eq 2 ] ; then
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

	################################################################
	## Install binaries
	#
	RESULT=0
	${CP} -Rf --preserve $TEMP_D/bin/* $INSTALL_DIR_CENTREON/bin/ >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	if [ "$RESULT" -eq 1 ] ; then
		echo_success "Copying module" "$ok"
	else
		echo_failure "Copying module" "$fail"
		exit 1
	fi

	################################################################
	## DSMD init script
	#

	RESULT=0
	FILE="libinstall/dsmd.systemd"
	${SED} -i -e 's|@CENTREON_USER@|'"$CENTREON_USER"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	${SED} -i -e 's|@INSTALL_DIR_CENTREON@|'"$INSTALL_DIR_CENTREON"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	if [ "$RESULT" -eq 2 ] ; then
		echo_success "Changing macros for init script" "$ok"
	else
		echo_failure "Changing macros for init script" "$fail"
		exit 1
	fi

	${CHMOD} -R 755 $TEMP_D/libinstall/dsmd.systemd >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Set owner for init script" "$ok"
	else
		echo_failure "Set owner for init script" "$fail"
		exit 1
	fi
	${CHOWN} $CENTREON_USER $TEMP_D/libinstall/dsmd.systemd >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Set mod for init script" "$ok"
	else
		echo_failure "Set mod for init script" "$fail"
		exit 1
	fi

	${CP} -Rf --preserve $TEMP_D/libinstall/dsmd.systemd /etc/systemd/system/dsmd.service >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Copying init script" "$ok"
	else
		echo_failure "Copying init script" "$fail"
		exit 1
	fi

    ################################################################
	## DSMD sysconfig/default script
	#
	RESULT=0
	# Prepare init.d and default/dsmd
    DISTRIB=""
    find_OS "DISTRIB"
    if [ "$DISTRIB" = "DEBIAN" ]; then
  	   FILE="libinstall/dsmd.default"

	   ${SED} -i -e 's|@CENTREON_LOG@|'"$CENTREON_LOG"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  RESULT=`expr $RESULT + 1`
	   fi
	   ${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_ETC"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  RESULT=`expr $RESULT + 1`
	   fi

	   if [ "$RESULT" -eq 2 ] ; then
		  echo_success "Changing macros for default script" "$ok"
	   else
		  echo_failure "Changing macros for default script" "$fail"
		  exit 1
	   fi

	   ${CHMOD} -R 644 $TEMP_D/$FILE >> $LOG_FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  echo_success "Set owner for default script" "$ok"
	   else
		  echo_failure "Set owner for default script" "$fail"
		  exit 1
	   fi
	   ${CHOWN} root $TEMP_D/$FILE >> $LOG_FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  echo_success "Set mod for default script" "$ok"
	   else
		  echo_failure "Set mod for default script" "$fail"
		  exit 1
	   fi

	   ${CP} -Rf --preserve $TEMP_D/$FILE /etc/default/dsmd >> $LOG_FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  echo_success "Copying default script" "$ok"
	   else
		  echo_failure "Copying default script" "$fail"
		  exit 1
	   fi
    else
	   FILE="libinstall/dsmd.sysconfig"
	   ${SED} -i -e 's|@CENTREON_LOG@|'"$CENTREON_LOG"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  RESULT=`expr $RESULT + 1`
	   fi
	   ${SED} -i -e 's|@CENTREON_ETC@|'"$CENTREON_ETC"'|g' $TEMP_D/$FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  RESULT=`expr $RESULT + 1`
	   fi

	   if [ "$RESULT" -eq 2 ] ; then
		  echo_success "Changing macros for sysconfig script" "$ok"
	   else
		  echo_failure "Changing macros for sysconfig script" "$fail"
		  exit 1
	   fi

	   ${CHMOD} -R 644 $TEMP_D/$FILE >> $LOG_FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  echo_success "Set owner for sysconfig script" "$ok"
	   else
		  echo_failure "Set owner for sysconfig script" "$fail"
		  exit 1
	   fi
	   ${CHOWN} root $TEMP_D/$FILE >> $LOG_FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  echo_success "Set mod for sysconfig script" "$ok"
	   else
		  echo_failure "Set mod for sysconfig script" "$fail"
		  exit 1
	   fi

	   ${CP} -Rf --preserve $TEMP_D/$FILE /etc/sysconfig/dsmd >> $LOG_FILE 2>> $LOG_FILE
	   if [ "$?" -eq 0 ] ; then
		  echo_success "Copying sysconfig script" "$ok"
	   else
		  echo_failure "Copying sysconfig script" "$fail"
		  exit 1
	   fi
    fi

	${RM} -Rf $TEMP_D >> $LOG_FILE 2>> $LOG_FILE

	echo ""
	echo "$line"
	echo -e "\tEnd of $NAME installation"
	echo "$line"
	echo_success "\n\nInstallation of $NAME is finished" "$ok"
	echo -e  "See README and the log file for more details."
}
