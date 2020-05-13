#!/bin/bash
#----
## @Synopsis	Install Script for Centreon Gorgone module
## @Copyright	Copyright 2008, Guillaume Watteeux
## @Copyright	Copyright 2008-2020, Centreon
## @License	GPL : http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
#----
## Centreon is developed with GPL Licence 2.0
## Developed by : Julien Mathis - Romain Le Merlus
##
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## as published by the Free Software Foundation; either version 2
## of the License, or (at your option) any later version.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
##    For information : infos@centreon.com
#
# @TODO
# - add the silent mode install
# - add the upgrade  mode
# - add the kill and clean system

#----
## define the available options and usage of the script
## @param the chosen option
#----
display_help() {
    echo
    echo -e "Usage: $0 [option...]"
    echo -e "\t-i\tRun the installation script\n"
    echo -e "\t-f\tRun with file with all variable"
    exit 1
}

#----
## Test if the file exists, is readable and not empty
## As the functions file has not been imported yet, we need to duplicate it here
## @param   file
## @return 0 	file exist
## @return 1 	file does not exist
#----
test_file() {
    local file="$1"
    if [ -r "$file" ] && [ -s "$file" ]; then
        return 0
    else
        echo -e "The file named '$file' is missing or empty\nPlease check your sources"
	    exit 1
    fi
}

## define and check the current source location
BASE_DIR=$(dirname $0)
BASE_DIR=$( cd $BASE_DIR; pwd )
export BASE_DIR

if [ -z "${BASE_DIR#/}" ] ; then
	echo -e "You shouldn't install Centreon from the root filesystem folder\nPlease move the sources to another folder"
	exit 1
fi

## define, test and load functions and vars files
INSTALL_DIR="$BASE_DIR/sourceInstall"
export INSTALL_DIR
test_file "$INSTALL_DIR/functions"
test_file "$INSTALL_DIR/vars"
. $INSTALL_DIR/functions
. $INSTALL_DIR/vars

## Valid if launched as root
if [ "${FORCE_NO_ROOT:-0}" -ne 0 ]; then
	USERID=$(id -u)
	if [ "$USERID" != "0" ]; then
	    echo -e "You must execute the script using root user"
	    exit 1
	fi
fi

## get and check the chosen script's option
process_install="0"
silent_install="0"
user_install_vars=""

if [ "$#" -eq 0 ] ; then
    echo -e "Install : please select one option"
    display_help
    exit 1
fi

while getopts "if:h" Options; 
do
    case ${Options} in
        i ) silent_install="0"
            process_install="1"
            ;;
        f ) silent_install="1"
            user_install_vars="${OPTARG}"
            process_install="1"
            ;;
        \?|h ) display_help;
            exit 0
            ;;
        * )	display_help ;
            exit 1
            ;;
    esac
done


if [ "$process_install" -ne 1 ]; then
    echo "Install : option not found"
    exit 1
fi;

## init LOG_FILE
[ ! -d "$LOG_DIR" ] && mkdir -p "$LOG_DIR"
if [ -e "$LOG_FILE" ] ; then
	mv "$LOG_FILE" "$LOG_FILE.`date +%Y%m%d-%H%M%S`"
fi
${CAT} << __EOL__ > "$LOG_FILE"
__EOL__

## Init GREP,CAT,SED,CHMOD,CHOWN variables
log "INFO" "Check mandatory binaries"
define_specific_binary_vars

## display header banner
${CAT} << __EOT__

###############################################################################
#                                                                             #
#                                                                             #
#                        Centreon Gorgone daemon module                       #
#                                                                             #
#                                                                             #
###############################################################################

__EOT__

if [ "$silent_install" -ne 1 ] ; then 
	## displaying the license
	echo -e "\nPlease read the license.\\n\\tPress enter to continue."
	read
	tput clear
	more "$BASE_DIR/LICENSE.txt"

	yes_no_default "Do you accept the license ?"
	if [ "$?" -ne 0 ] ; then
	    echo_info "As you did not accept the license, we cannot continue."
	    log "INFO" "Installation aborted - License not accepted"
	    exit 1
	else
	    log "INFO" "Accepted the license"
	fi
else
	. $user_install_vars
fi

## Test all binaries
BINARIES="rm cp mv ${CHMOD} ${CHOWN} echo more mkdir find ${GREP} ${CAT} ${SED}"

line="------------------------------------------------------------------------"

echo "$line"
echo -e "\tChecking all needed binaries"
echo "$line"

binary_fail="0"
for binary in $BINARIES; do
    if [ ! -e ${binary} ] ; then
        pathfind "$binary"
        if [ "$?" -eq 0 ] ; then
            echo_success "${binary}" "$ok"
        else
            echo_failure "${binary}" "$fail"
            log "ERROR" "\$binary not found in \$PATH"
            binary_fail=1
        fi
    else
        echo_success "${binary}" "$ok"
    fi
done

## Script stop if one binary wasn't found
if [ "$binary_fail" -eq 1 ] ; then
	echo_failure "Please check failing binary and retry"
	echo -e "\tThe logs are available in this file :\n$LOG_FILE"
	exit 1
fi

echo -e "\n$line"
echo -e "\tChecking the mandatory folders"
echo -e "$line"

## check filesystem space
check_disk_space
if [ "$silent_install" -ne 1 ] ; then
	allow_creation_of_missing_resources
else
   CREATION_ALLOWED=1
   export CREATION_ALLOWED
fi

## define destination folders
locate_gorgone_logdir
locate_gorgone_varlib
locate_gorgone_etcdir
locate_gorgone_bindir
locate_gorgone_perldir
locate_cron_d
locate_logrotate_d
locate_system_d
locate_sysconfig

echo "$line"
echo -e "\tChecking the required users"
echo "$line"

## create gorgone user
check_gorgone_group
check_gorgone_user

echo -e "\n$line"
echo -e "\tAdding Gorgone user to the mandatory folders"
echo -e "$line"

change_rights "$GORGONE_USER" "$GORGONE_GROUP" "775" "$GORGONE_LOG"
change_rights "$GORGONE_USER" "$GORGONE_GROUP" "775" "$GORGONE_VARLIB"

#----
## installation of Gorgone files
#----
echo "$line"
echo -e "\tInstalling Gorgone daemon"
echo "$line"

# modify rights on etc folder
${CHMOD} -R "775" "$GORGONE_ETC"
${CHOWN} -R "$GORGONE_USER:$GORGONE_GROUP" "$GORGONE_ETC"
if [ $? -ne 0 ] ; then
    echo_failure "$(gettext "Cannot modify the owner of the files in $GORGONE_ETC folder")" "$fail"
fi

# modify the gorgoned file to take in account the chosen user path
change_environment_file_path

## Copy the files in destination folders and modify rights
copy_and_modify_rights "$BASE_DIR/config/systemd" "gorgoned-service" "$SYSTEM_D" "gorgoned.service" "664"
copy_and_modify_rights "$BASE_DIR/config/systemd" "gorgoned-sysconfig" "$SYSCONFIG" "gorgoned" "664"
copy_and_modify_rights "$BASE_DIR/config/logrotate" "gorgoned" "$LOGROTATE_D" "gorgoned" "775"
copy_and_modify_rights "$BASE_DIR/packaging" "config.yaml" "$GORGONE_ETC" "config.yaml" "755"
copy_and_modify_rights "$BASE_DIR" "gorgoned" "$GORGONE_BINDIR" "gorgoned" "755"
copy_and_modify_rights "$BASE_DIR/contrib" "gorgone_config_init.pl" "$GORGONE_BINDIR" "gorgone_config_init.pl" "775"

## Recursively copy perl files
cp -R "$BASE_DIR/gorgone" "$GORGONE_PERL"
${CHMOD} -R "775" "$GORGONE_PERL/gorgone"
${CHOWN} -R "$GORGONE_USER:$GORGONE_GROUP" "$GORGONE_PERL/gorgone"

#----
## starting the service
#----
echo "$line"
echo -e "\tStarting gorgoned.service"
echo "$line"

## check the OS and launch the service
foundOS=""
find_OS "foundOS"
install_init_service "gorgoned"

## display footer banner
${CAT} << __EOT__

###############################################################################
#                                                                             #
#                         Thanks for using Gorgone.                           #
#                          -----------------------                            #
#                                                                             #
#           Please add the configuration in a file in the folder :            #
#                           $GORGONE_ETC/config.d                             #
#                     Then start the gorgoned.service                         #
#                                                                             #
#                You can read the documentation available here :              #
#      https://github.com/centreon/centreon-gorgone/blob/master/README.md     #
#                                                                             #
#      ------------------------------------------------------------------     #
#                                                                             #
#     Report bugs at https://github.com/centreon/centreon-gorgone/issues      #
#                                                                             #
#                        Contact : contact@centreon.com                       #
#                          http://www.centreon.com                            #
#                                                                             #
#                          -----------------------                            #
#              For security issues, please read our security policy           #
#           https://github.com/centreon/centreon-gorgone/security/policy      #
#                                                                             #
###############################################################################

__EOT__
exit 0
