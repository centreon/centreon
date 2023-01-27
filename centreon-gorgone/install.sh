#!/bin/bash
#----
## @Synopsis    Install Script for Gorgone project
## @Copyright    Copyright 2008, Guillaume Watteeux
## @Copyright    Copyright 2008-2021, Centreon
## @License    GPL : http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
## Centreon Install Script
#----
## Centreon is developed with GPL Licence 2.0
##
## GPL License: http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
##
## Developed by : Julien Mathis - Romain Le Merlus
## Contributors : Guillaume Watteeux - Maximilien Bersoult
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

#----
## Usage information for install.sh
## @Sdtout    Usage information
#----
usage() {
    local program=$0
    echo -e "Usage: $program"
    echo -e "  -i\tinstall Gorgone with interactive interface"
    echo -e "  -u\tupgrade Gorgone specifying the directory of instGorgone.conf file"
    echo -e "  -s\tinstall/upgrade Gorgone silently"
    echo -e "  -e\textra variables, 'VAR=value' format (overrides input files)"
    exit 1
}

## Use TRAPs to call clean_and_exit when user press
## CRTL+C or exec kill -TERM.
trap clean_and_exit SIGINT SIGTERM

## Valid if you are root 
if [ "${FORCE_NO_ROOT:-0}" -ne 0 ]; then
    USERID=$(id -u)
    if [ "$USERID" != "0" ]; then
        echo -e "You must launch this script using a root user"
        exit 1
    fi
fi

## Define where are Gorgone sources
BASE_DIR=$(dirname $0)
BASE_DIR=$( cd $BASE_DIR; pwd )
if [ -z "${BASE_DIR#/}" ] ; then
    echo -e "You cannot select the filesystem root folder"
    exit 1
fi
INSTALL_DIR="$BASE_DIR/install"

_tmp_install_opts="0"
silent_install="0"
upgrade="0"

## Get options
while getopts "isu:e:h" Options
do
    case ${Options} in
        i ) silent_install="0"
            _tmp_install_opts="1"
            ;;
        s ) silent_install="1"
            _tmp_install_opts="1"
            ;;
        u ) silent_install="0"
            UPGRADE_FILE="${OPTARG%/}"
            upgrade="1" 
            _tmp_install_opts="1"
            ;;
        e ) env_opts+=("$OPTARG")
            ;;
        \?|h)    usage ; exit 0 ;;
        * )    usage ; exit 1 ;;
    esac
done
shift $((OPTIND -1))

if [ "$_tmp_install_opts" -eq 0 ] ; then
    usage
    exit 1
fi

INSTALLATION_MODE="install"
if [ ! -z "$upgrade" ] && [ "$upgrade" -eq 1 ]; then
    INSTALLATION_MODE="upgrade"
fi

## Load default input variables
source $INSTALL_DIR/inputvars.default.env
## Load all functions used in this script
source $INSTALL_DIR/functions

## Define a default log file
if [ ! -z $LOG_FILE ] ; then
    LOG_FILE="$BASE_DIR/log/install.log"
fi
LOG_DIR=$(dirname $LOG_FILE)
[ ! -d "$LOG_DIR" ] && mkdir -p "$LOG_DIR"

## Init LOG_FILE
if [ -e "$LOG_FILE" ] ; then
    mv "$LOG_FILE" "$LOG_FILE.`date +%Y%m%d-%H%M%S`"
fi
${CAT} << __EOL__ > "$LOG_FILE"
__EOL__

# Checking installation script requirements
BINARIES="rm cp mv chmod chown echo more mkdir find grep cat sed tr"
binary_fail="0"
# For the moment, I check if all binary exists in PATH.
# After, I must look a solution to use complet path by binary
for binary in $BINARIES; do
    if [ ! -e ${binary} ] ; then 
        pathfind_ret "$binary" "PATH_BIN"
        if [ "$?" -ne 0 ] ; then
            echo_error "${binary}" "FAILED"
            binary_fail=1
        fi
    fi
done

## Script stop if one binary is not found
if [ "$binary_fail" -eq 1 ] ; then
    echo_info "Please check failed binary and retry"
    exit 1
else
    echo_success "Script requirements" "OK"
fi

## Search distribution and version
if [ -z "$DISTRIB" ] || [ -z "$DISTRIB_VERSION" ] ; then
    find_os
fi
echo_info "Found distribution" "$DISTRIB $DISTRIB_VERSION"

## Load specific variables based on distribution
if [ -f $INSTALL_DIR/inputvars.$DISTRIB.env ]; then
    echo_info "Loading distribution specific input variables" "install/inputvars.$DISTRIB.env"
    source $INSTALL_DIR/inputvars.$DISTRIB.env
fi

## Load specific variables based on version
if [ -f $INSTALL_DIR/inputvars.$DISTRIB.$DISTRIB_VERSION.env ]; then
    echo_info "Loading version specific input variables" "install/inputvars.$DISTRIB.$DISTRIB_VERSION.env"
    source $INSTALL_DIR/inputvars.$DISTRIB.$DISTRIB_VERSION.env
fi

## Load specific variables defined by user
if [ -f $INSTALL_DIR/../inputvars.env ]; then
    echo_info "Loading user specific input variables" "inputvars.env"
    source $INSTALL_DIR/../inputvars.env
fi

## Load previous installation input variables if upgrade
if [ "$upgrade" -eq 1 ] ; then
    test_file "$UPGRADE_FILE" "Gorgone upgrade file"
    if [ "$?" -eq 0 ] ; then
        echo_info "Loading previous installation input variables" "$UPGRADE_FILE"
        source $UPGRADE_FILE
    else
        echo_error "Missing previous installation input variables" "FAILED"
        echo_info "Either specify it in command line or using UPGRADE_FILE input variable"
        exit 1
    fi
fi

## Load variables provided in command line
for env_opt in "${env_opts[@]}"; do
    if [[ "${env_opt}" =~ .+=.+ ]] ; then
        variable=$(echo $env_opt | cut -f1 -d "=")
        value=$(echo $env_opt | cut -f2 -d "=")
        if [ ! -z "$variable" ] && [ ! -z "$value" ] ; then
            echo_info "Loading command line input variables" "${variable}=${value}"
            eval ${variable}=${value}
        fi
    fi
done

## Check installation mode
if [ -z "$INSTALLATION_TYPE" ] ; then
    echo_error "Installation mode" "NOT DEFINED"
    exit 1
fi
if [[ ! "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    echo_error "Installation mode" "$INSTALLATION_TYPE"
    exit 1
fi
echo_info "Installation type" "$INSTALLATION_TYPE"
echo_info "Installation mode" "$INSTALLATION_MODE"

## Check space of tmp dir
check_tmp_disk_space
if [ "$?" -eq 1 ] ; then
    if [ "$silent_install" -eq 1 ] ; then
        purge_centreon_tmp_dir "silent"
    else
        purge_centreon_tmp_dir
    fi
fi

## Installation is interactive
if [ "$silent_install" -ne 1 ] ; then
    echo -e "\n"
    echo_info "Welcome to Centreon installation script!"
    yes_no_default "Should we start?" "$yes"
    if [ "$?" -ne 0 ] ; then
        echo_info "Exiting"
        exit 1
    fi
fi

# Start installation

ERROR_MESSAGE=""

# Centreon installation requirements
echo_title "Centreon installation requirements"

if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    # System
    test_dir_from_var "LOGROTATED_ETC_DIR" "Logrotate directory"
    test_dir_from_var "SYSTEMD_ETC_DIR" "SystemD directory"
    test_dir_from_var "SYSCONFIG_ETC_DIR" "Sysconfig directory"
    test_dir_from_var "BINARY_DIR" "System binary directory"

    ## Perl information
    find_perl_info
    test_file_from_var "PERL_BINARY" "Perl binary"
    test_dir_from_var "PERL_LIB_DIR" "Perl libraries directory"
fi

if [ ! -z "$ERROR_MESSAGE" ] ; then
    echo_error "Installation requirements" "FAILED"
    echo_error "\nErrors:"
    echo_error "$ERROR_MESSAGE"
    exit 1
fi

echo_success "Installation requirements" "OK"

## Gorgone information
echo_title "Gorgone information"

if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    test_var_and_show "GORGONE_USER" "Gorgone user"
    test_var_and_show "GORGONE_GROUP" "Gorgone group"
    test_var_and_show "GORGONE_ETC_DIR" "Gorgone configuration directory"
    test_var_and_show "GORGONE_LOG_DIR" "Gorgone log directory"
    test_var_and_show "GORGONE_VARLIB_DIR" "Gorgone variable library directory"
    test_var_and_show "GORGONE_CACHE_DIR" "Gorgone cache directory"
    test_var_and_show "CENTREON_USER" "Centreon user"
    test_var_and_show "CENTREON_HOME" "Centreon home directory"
    test_var_and_show "CENTREON_ETC_DIR" "Centreon configuration directory"
    test_var_and_show "CENTREON_SERVICE" "Centreon service"
    test_var_and_show "ENGINE_USER" "Engine user"
    test_var_and_show "ENGINE_GROUP" "Engine group"
    test_var_and_show "BROKER_USER" "Broker user"
    test_var_and_show "BROKER_GROUP" "Broker group"
fi

if [ ! -z "$ERROR_MESSAGE" ] ; then
    echo_error "\nErrors:"
    echo_error "$ERROR_MESSAGE"
    exit 1
fi

if [ "$silent_install" -ne 1 ] ; then 
    yes_no_default "Everything looks good, proceed to installation?"
    if [ "$?" -ne 0 ] ; then
        purge_centreon_tmp_dir "silent"
        exit 1
    fi
fi

# Start installation

## Build files
echo_title "Build files"
echo_line "Copying files to '$TMP_DIR'"

if [ -d $TMP_DIR ] ; then
    mv $TMP_DIR $TMP_DIR.`date +%Y%m%d-%k%m%S`
fi

create_dir "$TMP_DIR/source"

if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    {
        copy_dir "$BASE_DIR/config" "$TMP_DIR/source/" &&
        copy_dir "$BASE_DIR/gorgone" "$TMP_DIR/source/" &&
        copy_dir "$BASE_DIR/install" "$TMP_DIR/source/" &&
        copy_file "$BASE_DIR/gorgoned" "$TMP_DIR/source/"
    } || {
        echo_error_on_line "FAILED"
        if [ ! -z "$ERROR_MESSAGE" ] ; then
            echo_error "\nErrors:"
            echo_error "$ERROR_MESSAGE"
        fi
        purge_centreon_tmp_dir "silent"
        exit 1
    }
fi
echo_success_on_line "OK"

echo_line "Replacing macros"
eval "echo \"$(cat "$TMP_DIR/source/install/src/instGorgone.conf")\"" > $TMP_DIR/source/install/src/instGorgone.conf
if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    {
        replace_macro "install/src"
    } || {
        echo_error_on_line "FAILED"
        if [ ! -z "$ERROR_MESSAGE" ] ; then
            echo_error "\nErrors:"
            echo_error "$ERROR_MESSAGE"
        fi
        purge_centreon_tmp_dir "silent"
        exit 1
    }
fi
echo_success_on_line "OK"

test_user "$GORGONE_USER"
if [ $? -ne 0 ]; then
    {
        ### Create user and group
        create_dir "$GORGONE_VARLIB_DIR" &&
        create_group "$GORGONE_GROUP" &&
        create_user "$GORGONE_USER" "$GORGONE_GROUP" "$GORGONE_VARLIB_DIR" &&
        set_ownership "$GORGONE_VARLIB_DIR" "$GORGONE_USER" "$GORGONE_GROUP" &&
        set_permissions "$GORGONE_VARLIB_DIR" "755"
    } || {
        if [ ! -z "$ERROR_MESSAGE" ] ; then
            echo_error "\nErrors:"
            echo_error "$ERROR_MESSAGE"
        fi
        purge_centreon_tmp_dir "silent"
        exit 1
    }
fi

echo_line "Building installation tree"
BUILD_DIR="$TMP_DIR/build"
create_dir "$BUILD_DIR"

if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    {
        ### Configuration diretories and base file
        create_dir "$BUILD_DIR/$GORGONE_ETC_DIR" "$GORGONE_USER" "$GORGONE_GROUP" "755" &&
        create_dir "$BUILD_DIR/$GORGONE_ETC_DIR/config.d" "$GORGONE_USER" "$GORGONE_GROUP" "775" &&
        create_dir "$BUILD_DIR/$GORGONE_ETC_DIR/config.d/cron.d" "$GORGONE_USER" "$GORGONE_GROUP" "775" &&
        copy_file "$TMP_DIR/source/install/src/config.yaml" "$BUILD_DIR/$GORGONE_ETC_DIR/config.yaml" \
            "$GORGONE_USER" "$GORGONE_GROUP" &&

        ### Install save file
        copy_file "$TMP_DIR/source/install/src/instGorgone.conf" \
            "$BUILD_DIR/$GORGONE_ETC_DIR/instGorgone.conf" \
            "$GORGONE_USER" "$GORGONE_GROUP" "644" &&

        ### Log directory
        create_dir "$BUILD_DIR/$GORGONE_LOG_DIR" "$GORGONE_USER" "$GORGONE_GROUP" "755" &&

        ### Cache directories
        create_dir "$BUILD_DIR/$GORGONE_CACHE_DIR" "$GORGONE_USER" "$GORGONE_GROUP" "755" &&
        create_dir "$BUILD_DIR/$GORGONE_CACHE_DIR/autodiscovery" "$GORGONE_USER" "$GORGONE_GROUP" "755"
    } || {
        echo_error_on_line "FAILED"
        if [ ! -z "$ERROR_MESSAGE" ] ; then
            echo_error "\nErrors:"
            echo_error "$ERROR_MESSAGE"
        fi
        purge_centreon_tmp_dir "silent"
        exit 1
    }
fi
echo_success_on_line "OK"

## Install files
echo_title "Install builded files"
echo_line "Copying files from '$TMP_DIR' to final directory"
copy_dir "$BUILD_DIR/*" "/"
if [ "$?" -ne 0 ] ; then
    echo_error_on_line "FAILED"
    if [ ! -z "$ERROR_MESSAGE" ] ; then
        echo_error "\nErrors:"
        echo_error "$ERROR_MESSAGE"
    fi
    purge_centreon_tmp_dir "silent"
    exit 1
fi
echo_success_on_line "OK"

## Install remaining files
echo_title "Install remaining files"

if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    ### Configurations
    copy_file_no_replace "$TMP_DIR/source/install/src/centreon.yaml" \
        "$GORGONE_ETC_DIR/config.d/30-centreon.yaml" \
        "Centreon configuration" \
        "$GORGONE_USER" "$GORGONE_GROUP" "644"
    copy_file_no_replace "$TMP_DIR/source/install/src/centreon-api.yaml" \
        "$GORGONE_ETC_DIR/config.d/31-centreon-api.yaml" \
        "Centreon API configuration" \
        "$GORGONE_USER" "$GORGONE_GROUP" "644"

    ### Perl libraries
    copy_dir "$TMP_DIR/source/gorgone" "$PERL_LIB_DIR/gorgone"

    ### Gorgoned binary
    copy_file "$TMP_DIR/source/gorgoned" "$BINARY_DIR"

    ### Systemd files
    restart_gorgoned="0"
    copy_file "$TMP_DIR/source/install/src/gorgoned.systemd" \
        "$SYSTEMD_ETC_DIR/gorgoned.service" && restart_gorgoned="1"
    copy_file_no_replace "$TMP_DIR/source/install/src/gorgoned.sysconfig" "$SYSCONFIG_ETC_DIR/gorgoned" \
        "Sysconfig Gorgoned configuration" && restart_gorgoned="1"

    ### Logrotate configuration
    copy_file_no_replace "$TMP_DIR/source/install/src/gorgoned.logrotate" "$LOGROTATED_ETC_DIR/gorgoned" \
        "Logrotate Gorgoned configuration"
fi

if [ ! -z "$ERROR_MESSAGE" ] ; then
    echo_error "\nErrors:"
    echo_error "$ERROR_MESSAGE"
    ERROR_MESSAGE=""
fi

## Update groups memberships
echo_title "Update groups memberships"
if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    add_user_to_group "$GORGONE_USER" "$BROKER_GROUP"
    add_user_to_group "$GORGONE_USER" "$ENGINE_GROUP"
    add_user_to_group "$ENGINE_USER" "$GORGONE_GROUP"
    add_user_to_group "$BROKER_USER" "$GORGONE_GROUP"
fi

if [ ! -z "$ERROR_MESSAGE" ] ; then
    echo_error "\nErrors:"
    echo_error "$ERROR_MESSAGE"
    ERROR_MESSAGE=""
fi

## Retrieve Centreon SSH key
if [ ! -d "$GORGONE_VARLIB_DIR/.ssh" ] && [ -d "$CENTREON_HOME/.ssh" ] ; then
    echo_title "Retrieve Centreon SSH key"
    copy_file "$CENTREON_HOME/.ssh/*" "$GORGONE_VARLIB_DIR/.ssh" "$GORGONE_USER" "$GORGONE_GROUP" &&
    set_permissions "$GORGONE_VARLIB_DIR/.ssh/id_rsa" "600"
fi

## Configure and restart services
echo_title "Configure and restart services"
if [[ "${INSTALLATION_TYPE}" =~ ^central|poller$ ]] ; then
    ### Gorgoned
    enable_service "gorgoned"

    if [ "$restart_gorgoned" -eq 1 ] ; then
        reload_daemon
        restart_service "gorgoned"
    fi
fi

if [ ! -z "$ERROR_MESSAGE" ] ; then
    echo_error "\nErrors:"
    echo_error "$ERROR_MESSAGE"
    ERROR_MESSAGE=""
fi

## Purge working directories
purge_centreon_tmp_dir "silent"

# End
echo_title "You're done!"
echo_info ""
echo_info "Take a look at the documentation"
echo_info "https://docs.centreon.com/current."
echo_info "Thanks for using Gorgone!"
echo_info "Follow us on https://github.com/centreon/centreon-gorgone!"

exit 0
