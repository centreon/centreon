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

#----
## get right and left spaces of header line
## @return 	"$x:$y"
#----
function get_spaces_modulo_name() {
	lenght_module_name=`echo ${#RNAME}`
	let "spaces=$LINE_SIZE-19-$lenght_module_name"
	let "modulo_spaces=$spaces%2"

	if [ $modulo_spaces -eq 0 ] ; then
			let "x=$spaces/2"
			echo "$x:$x"
			return 0
	else
			let "x=$spaces/2+1"
			let "y=$spaces/2"
			echo "$x:$y"
			return 0
	fi
}

#----
## get right and left spaces of header version line
## @return 	"$x:$y"
#----
function get_spaces_modulo_version() {
	lenght_module_version=`echo ${#VERSION}`
	let "spaces=$LINE_SIZE-4-$lenght_module_version"
	let "modulo_spaces=$spaces%2"

	if [ $modulo_spaces -eq 0 ] ; then
			let "x=$spaces/2"
			echo "$x:$x"
			return 0
	else
			let "x=$spaces/2+1"
			let "y=$spaces/2"
			echo "$x:$y"
			return 0
	fi
}

#----
## print header of script installation
#----
function print_header() {
	name_spaces=`get_spaces_modulo_name;`
	spaces_x_name=`echo $name_spaces | cut -d":" -f1`
	version_spaces=`get_spaces_modulo_version;`
	spaces_x_version=`echo $version_spaces | cut -d":" -f1`

	echo -e "################################################################################"
	echo -e "#                                                                              #"
	echo -e "#\\033[${spaces_x_name}GThanks for using ${RNAME}\\033[${LINE_SIZE}G#"
	echo -e "#\\033[${spaces_x_version}Gv ${VERSION}\\033[${LINE_SIZE}G#"
	echo -e "#                                                                              #"
	echo -e "################################################################################"
}

#----
## get right and left spaces of header version line
## @return 	"$x:$y"
#----
function get_spaces_modulo_forge_url() {
	lenght_forge_url=`echo ${#FORGE_URL}`
	let "spaces=$LINE_SIZE-2-$lenght_forge_url"
	let "modulo_spaces=$spaces%2"

	if [ $modulo_spaces -eq 0 ] ; then
			let "x=$spaces/2"
			echo "$x:$x"
			return 0
	else
			let "x=$spaces/2+1"
			let "y=$spaces/2"
			echo "$x:$y"
			return 0
	fi
}

#----
## print foorter of script installation
#----
function print_footer() {
	forge_url_spaces=`get_spaces_modulo_forge_url;`
	spaces_x=`echo $forge_url_spaces | cut -d":" -f1`
	
	echo -e "################################################################################"
	echo -e "#                                                                              #"
	echo -e "#       Go to the URL : http://your-server/centreon/ to finish the setup       #"
	echo -e "#                                                                              #"
	echo -e "#       Report bugs at                                                         #"
	echo -e "#\\033[${spaces_x}G${FORGE_URL}\\033[${LINE_SIZE}G#"
	echo -e "#                                                                              #"
	echo -e "################################################################################"
}

#---
## {Get Centreon install dir and user/group for apache}
#----
function get_centreon_parameters() {
	CENTREON_DIR=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "INSTALL_DIR_CENTREON" | cut -d '=' -f2`;
	CENTREON_LOG_DIR=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_LOG" | cut -d '=' -f2`;
	#CENTREON_VARLIB=`${CAT} $CENTREON_CONF/$CENTSTORAGE_FILE_CONF | ${GREP} "CENTREON_VARLIB" | cut -d '=' -f2`;
	#CENTCORE_CMD=$CENTREON_VARLIB"/centcore.cmd"

	WEB_USER=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "WEB_USER" | cut -d '=' -f2`;
	WEB_GROUP=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "WEB_GROUP" | cut -d '=' -f2`;
	
	MONITORINGENGINE_BINARY=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "MONITORINGENGINE_BINARY" | cut -d '=' -f2`;
	PLUGIN_DIR=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "PLUGIN_DIR" | cut -d '=' -f2`;
	MONITORINGENGINE_USER=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "MONITORINGENGINE_USER" | cut -d '=' -f2`;
	CENTREON_GROUP=`${CAT} $CENTREON_CONF/$FILE_CONF | ${GREP} "CENTREON_GROUP" | cut -d '=' -f2`;

	PLUGIN_DIR=`${CAT} $CENTREON_CONF/$CENTPLUGINS_FILE_CONF | ${GREP} "PLUGIN_DIR" | cut -d '=' -f2`
    
	RESULT=0
	# check centreon parameters
	if [ "$CENTREON_DIR" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$CENTREON_LOG_DIR" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	#if [ "$CENTREON_VARLIB" != "" ] ; then
	#	RESULT=`expr $RESULT + 1`
	#fi


	# check apache parameters
	if [ "$WEB_USER" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$WEB_GROUP" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi


	# check Nagios parameters
	if [ "$MONITORINGENGINE_BINARY" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$PLUGIN_DIR" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$MONITORINGENGINE_USER" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	if [ "$CENTREON_GROUP" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi
    if [ "$PLUGIN_DIR" != "" ] ; then
		RESULT=`expr $RESULT + 1`
	fi


	#if [ "$RESULT" -eq 10 ]; then 
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
## {Install my Module}
##
## @Stdout Actions realised by function
## @Stderr Log into $LOG_FILE
function install_module() {
	install_module_web;
	install_module_end;
}

## {Install Web Interface of Module}
##
## @Stdout Actions realised by function
## @Stderr Log into $LOG_FILE
function install_module_web() {
	INSTALL_DIR_MODULE=$CENTREON_DIR/$MODULE_DIR

	echo ""
	echo "$line"
	echo -e "\tInstall $NAME"
	echo "$line"
	TEMP_D="/tmp/Install_module"
	${MKDIR} -p $TEMP_D >> $LOG_FILE 2>> $LOG_FILE
	${CP} -Rf www/modules/centreon-awie/* $TEMP_D/ >> $LOG_FILE 2>> $LOG_FILE
	${RM} -Rf $TEMP_D/install $TEMP_D/*.log

	${CHMOD} -R 755 $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Setting right" "$ok"
	else 
		echo_failure "Setting right" "$fail"
		exit 1
	fi
	
	RESULT=0
	${CHOWN} -R $WEB_USER.$WEB_GROUP $TEMP_D/* >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi
	
	if [ "$RESULT" -eq 1 ] ; then
		echo_success "Setting owner/group" "$ok"
	else 
		echo_failure "Setting owner/group" "$fail"
		exit 1
	fi
	
	RESULT=0
	find $TEMP_D -type f -exec ${SED} -i -e 's|@CENTREON_ETC@|'$CENTREON_CONF'|g' \{\} \; 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		RESULT=`expr $RESULT + 1`
	fi

	if [ "$RESULT" -eq 1 ] ; then
		echo_success "Changing macro" "$ok"
	else 
		echo_failure "Changing macro" "$fail"
		exit 1
	fi
	
	if [ ! -d $INSTALL_DIR_MODULE ] ; then
		RESULT=0
		${MKDIR} $INSTALL_DIR_MODULE >> $LOG_FILE 2>> $LOG_FILE
		if [ "$?" -eq 0 ] ; then
			RESULT=`expr $RESULT + 1`
		fi
		${CHOWN} -R $WEB_USER.$WEB_GROUP $INSTALL_DIR_MODULE >> $LOG_FILE 2>> $LOG_FILE
		if [ "$?" -eq 0 ] ; then
			RESULT=`expr $RESULT + 1`
		fi
		${CHMOD} -R 755 $INSTALL_DIR_MODULE >> $LOG_FILE 2>> $LOG_FILE
		if [ "$?" -eq 0 ] ; then
			RESULT=`expr $RESULT + 1`
		fi
		
		if [ "$RESULT" -eq 3 ] ; then
			echo_success "Create module directory" "$ok"
		else 
			echo_failure "Create module directory" "$fail"
			exit 1
		fi
	fi
	
	${CP} -Rf --preserve $TEMP_D/* $INSTALL_DIR_MODULE >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Copying module" "$ok"
	else 
		echo_failure "Copying module" "$fail"
		exit 1
	fi
}

## {End of installation}
##
## @Stdout Actions realised by function
## @Stderr Log into $LOG_FILE
function install_module_end() {
	${RM} -Rf $TEMP_D $TEMP >> $LOG_FILE 2>> $LOG_FILE
	if [ "$?" -eq 0 ] ; then
		echo_success "Delete temp install directory" "$ok"
	else 
		echo_failure "Delete temp install directory" "$fail"
		exit 1
	fi
    
    echo ""
	echo "$line"
	echo -e "\tEnd of $RNAME installation"
	echo "$line"
	echo_success "Installation of $RNAME is finished" "$ok"
	echo -e  "See README and the log file for more details."
	echo ""
}
