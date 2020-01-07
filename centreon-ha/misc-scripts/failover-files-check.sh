#!/bin/bash

# default options
ACTION=check
NODE_TYPE=any
DRY_RUN=1
DIFF_OPTIONS=
MYSQL_OPTIONS_default='--skip-column-names --batch'
MYSQL_OPTIONS="$MYSQL_OPTIONS_default"
INTERACTIVE_FIX=
OK_STRING="\033[1;32mOK\033[m"
KO_STRING="\033[1;31mKO\033[m"
EXCLAMATION_SIGN="\033[1;33m!!! WARNING !!!\033[m"
SKIPPED_STRING="\033[1;33mSKIPPED\033[m"

OCF_SCRIPTS_LIST="{cbd-central-broker,misc-centreon,check-master-db,mysql-centreon,centreon-central-sync,misc-poller}"
OCS_SCRIPTS_PERMISSON_REGEXP='^-rwxr-xr-x'
OCS_SCRIPTS_PERMISSON_MASK=755

SCRIPTPATH="${0%/*}"
GITCLONEPATH="${SCRIPTPATH%/misc-scripts}"

DATA_DIR='/var/lib/mysql'
INSTALL_DIR='/usr/share/centreon/www/install'
CENTRAL_BROKER_CONFIG_FILE='/etc/centreon-broker/central-broker.xml'
RRD_TCP_PORT=5670

function exec-diff() {
    local cmdline cmdoutput cmdret choice

    if [[ ! -f ${1} ]] ; then
        echo "********************************************************************************"
        echo "*** ERROR: $1 DOES NOT EXIST ***"
        echo "********************************************************************************"
        echo -e "# You should run \`cp $2 $1\`\n"
        return 1
    fi
    cmdline="diff $DIFF_OPTIONS $1 $2"
    cmdoutput="$(eval $cmdline)"
    cmdret=$?
    if [[ -z $INTERACTIVE_FIX && $cmdret != 0 ]] ; then
        echo "### $cmdline"
        echo "$cmdoutput"
        return $cmdret
    elif [[ "$INTERACTIVE_FIX" == 1 && $cmdret != 0 ]] ; then
        echo "################################################################################"
        echo "### $cmdline"
        echo "################################################################################"
        echo -e "$cmdoutput\n"
        echo "### Do you want to patch $1 with the diff displayed above? ([yN])"
        read choice

        if [[ "${choice,,}" == 'y' ]] ; then
            [[ "$DEBUG" ]] && echo "# \\cp -f \"$1\" \"${1}.bak\"" >&2
            \cp -f "$1" "${1}.bak"
            [[ "$DEBUG" ]] && echo "# patch \"$1\" <<< \"$cmdoutput\"" >&2
            patch "$1" <<< "$cmdoutput"
        fi
        choice=
    elif [[ "$DEBUG" ]] ; then
        echo "# $cmdline" >&2
    fi

    return 0
}

function exec-sequence() {
    local cmdlines cmdline oIFS IFS

    oIFS="$IFS"
    IFS=$'\n'
    cmdlines=($1)
    IFS="$oIFS"
    for cmdline in "${cmdlines[@]}" ; do
        eval "$cmdline"
    done

}

function exec-mysql() {
    local cmdline cmdoutput cmdret choice

    cmdline="mysql $MYSQL_OPTIONS $1 -e \"$2\""
    if [[ "$DEBUG" ]] ; then
        echo "# $cmdline" >&2
    fi
    cmdoutput="$(eval $cmdline)"
    cmdret=$?
    echo "$cmdoutput"
    return $cmdret
}

function service-has-status() {
    local service_name expected_status actual_status
    service_name="$1"
    expected_status="$2"
    actual_status="$(systemctl is-enabled $1 2>/dev/null)"
    if [[ "$actual_status" == "$expected_status" ]] || [[ "$expected_status" == 'disabled'&& "$actual_status" == '' ]] ; then
        echo -e "Checking that service '$service_name' is '$expected_status'\t\t\t[${OK_STRING}]"
        return 0
    else
        echo -e "Checking that service '$service_name' is '$expected_status'\t\t\t[${KO_STRING}]"
        [[ "$DEBUG" ]] && echo "DEBUG: Expected status for service '$service_name' is '$expected_status' but it is actually '$actual_status'" >&2
        return 1
    fi
}

function ssh-without-dns() {
    local dns_lines dns_line dns_use
    oIFS="$IFS"; IFS=$'\n'
    # we filter uncommented lines and keep only lines containing DNS (cas insensitive)
    dns_lines=($(grep -Ev '^\s*(#.*|$)' /etc/ssh/sshd_config | grep -i dns))
    IFS="$oIFS"
    for dns_line in "${dns_lines[@]}" ; do
        dns_use="${dns_line#* }"
    done
    if [[ "${dns_use,,}" == 'no' ]] ; then
        echo -e "Checking that SSH does not use DNS\t\t\t\t[${OK_STRING}]"
    else
        echo -e "Checking that SSH does not use DNS\t\t\t\t[${KO_STRING}]"
        echo -e "\tPlease add 'UseDNS no' to /etc/ssh/sshd_config"
    fi
}

################################################################################
# NON-NODE-SPECIFIC CHECK FUNCTIONS
################################################################################

function any-deploy-files-dry-run() {

    echo "\\rm -rf /etc/centreon-failover"
    echo "\\cp -rf $GITCLONEPATH /etc/centreon-failover"
    echo "chmod $OCS_SCRIPTS_PERMISSON_MASK /etc/centreon-failover/ocf-scripts/${OCF_SCRIPTS_LIST}"
    echo "\\cp -rf /etc/centreon-failover/ocf-scripts/* /usr/lib/ocf/resource.d/heartbeat/"
    echo "mkdir /etc/centreon-failover/spool/"

    return 0
}

function any-deploy-files() {

    if [[ "$DRY_RUN" ]] ; then
        any-deploy-files-dry-run
    else
        exec-sequence "$(any-deploy-files-dry-run)"
    fi
}

function any-check-files() {
    local file filebasename file_permission_errors

    # checking the content of ocf-scripts files
    for file in $(find $GITCLONEPATH/ocf-scripts/ -type f) ; do
        filebasename="${file##*/ocf-scripts/}"
        exec-diff  /etc/centreon-failover/ocf-scripts/${filebasename} $file
        exec-diff  /usr/lib/ocf/resource.d/heartbeat/${filebasename} $file
    done

    # checking permissions
    file_permission_errors="$(eval "ls -l /etc/centreon-failover/ocf-scripts/${OCF_SCRIPTS_LIST}" | grep -vE $OCS_SCRIPTS_PERMISSON_REGEXP)"
    if [[ "$file_permission_errors" ]] ; then
        echo "# There are some permission issues on these files:"
        echo $file_permission_errors
    fi

}

function any-plugin-files() {
    local verbose ret

    verbose="$(any-check-files)"

    if [[ "$verbose" ]] ; then
        echo "WARNING: Some differences have been found. Please read extended status information."
        echo "$verbose"
        exit 1
    else
        echo "OK: Everything looks fine."
        exit 0
    fi

}

function any-diagnostic() {

    local node ret chkconfig_output

    # Checking that all services are enabled/disabled as they should
    service-has-status mysql disabled
    chkconfig_output="$(LANG=en chkconfig 2>/dev/null  | grep -E 'mysql.*on')"
    ret=$?
    if [[ "$ret" == 0 ]] ; then
        echo -e "Checking that 'mysql' is not enabled via chkconfig\t\t[${KO_STRING}]"
    else
        echo -e "Checking that 'mysql' is not enabled via chkconfig\t\t[${OK_STRING}]"
    fi
    service-has-status snmpd enabled
    service-has-status pcsd enabled
    service-has-status pacemaker enabled
    service-has-status corosync enabled
    # No use of DNS (in particular in sshd_config)
    ssh-without-dns
    # Check /etc/hosts
    for node in $(crm_mon -n -1 | grep Node | awk '{print substr($2, 1, length($2)-1)}') ; do
        grep -Ev '^#' /etc/hosts | grep -E "\s${node}"'($|\s)' >/dev/null 2>&1
        ret=$?
        echo -ne "Checking that node ${node} is resolved in /etc/hosts\t"
        if [[ $ret == 0 ]] ; then
            echo -e "[${OK_STRING}]"
        else
            echo -e "[${KO_STRING}]"
            echo -e "\tNo correct entry in /etc/hosts for node ${node}"
        fi
    done

}

################################################################################
# CENTRAL-NODE-SPECIFIC FUNCTIONS
################################################################################

function central-deploy-files-dry-run() {
    local peer_ip_address

    # Checking mandatory options
    if [[ -z $CENTRAL_MASTER_IP || -z $CENTRAL_SLAVE_IP ]] ; then
        echo -e "Error: in this mode, central master and slave IPs are MANDATORY.\n"
        show-help
        exit 1
    fi

    # Calling the common files deployment
    any-deploy-files-dry-run

    # Printing the central-specific commands
    echo "\\cp -f /etc/centreon-failover/misc-scripts/centreon-central-sync /usr/share/centreon/bin/"
    echo "chmod 755 /usr/share/centreon/bin/centreon-central-sync"
    echo "\\cp -f /etc/centreon-failover/resources/cron-19.10/* /etc/centreon-failover/resources/cron/"
    echo "cat /etc/centreon-failover/resources/centreon-cluster > /etc/sudoers.d/centreon-cluster"

    # Determining which one of master and slave ip is the peer ip address
    if [[ $(ip a | grep -w $CENTRAL_MASTER_IP) ]] ; then
        peer_ip_address="$CENTRAL_SLAVE_IP"
    else
        peer_ip_address="$CENTRAL_MASTER_IP"
    fi

    # Replacing the peer IP address in centreon-central-sync
    echo "sed -i.bak '0,/XXX.XXX.XXX.XXX/s/XXX.XXX.XXX.XXX/${peer_ip_address}/' /usr/share/centreon/bin/centreon-central-sync"
    return 0
}

function central-deploy-files() {

    if [[ "$DRY_RUN" ]] ; then
        central-deploy-files-dry-run
    else
        exec-sequence "$(central-deploy-files-dry-run)"
    fi

}

function central-check-files() {
    local file filebasename file_permission_errors

    # Checking the common files
    any-check-files

    # Checking centreon-central-sync content

    # NB: it is normal to have a difference in centreon-central-sync on this line: "my $peer_addr = 'XXX.XXX.XXX.XXX' # Need Change"
    DIFF_OPTIONS="--ignore-matching-lines='Need Change'" exec-diff /usr/share/centreon/bin/centreon-central-sync $GITCLONEPATH/misc-scripts/centreon-central-sync

    # Checking permissions
    file_permission_errors="$(eval "ls -l /etc/centreon-failover/ocf-scripts/${OCF_SCRIPTS_LIST}" | grep -vE $OCS_SCRIPTS_PERMISSON_REGEXP)"

    # Checking the content of cron files
    for file in $(find $GITCLONEPATH/resources/cron-19.10/ -type f) ; do
        filebasename="${file##*/cron-19.10/}"
        exec-diff /etc/cron.d/${filebasename} $file
    done

    # Checking the content of the sudoers file
    exec-diff /etc/sudoers.d/centreon-cluster ${GITCLONEPATH}/resources/centreon-cluster

}

function central-plugin-files() {
    local verbose ret

    verbose="$(central-check-files)"

    if [[ "$verbose" ]] ; then
        echo "WARNING: Some differences have been found. Please read extended status information."
        echo "$verbose"
        exit 1
    else
        echo "OK: Everything looks fine."
        exit 0
    fi

}

function central-diagnostic() {

    local lines line IFS oIFS current_ip peer_ip
    # Checking that all services are enabled/disabled as they should
    any-diagnostic
    service-has-status cbd enabled
    if [[ -L /etc/systemd/system/multi-user.target.wants/cbd.service ]]  && ! [[ -e /etc/systemd/system/centreon.service.wants/cbd.service ]] ; then
        echo -e "Checking that cbd is enabled independantly of centreon\t\t\t[${OK_STRING}]"
    else
        echo -e "Checking that cbd is enabled independantly of centreon\t\t\t[${KO_STRING}]"
        echo "The symlink towards cbd.service should be located in /etc/systemd/system/multi-user.target.wants/ and not in /etc/systemd/system/centreon.service.wants/"
    fi
    service-has-status centengine disabled
    service-has-status centcore disabled
    service-has-status centreontrapd disabled
    service-has-status snmptrapd disabled
    service-has-status dsmd disabled
    service-has-status httpd24-httpd disabled
    # Getting database credentials
    oIFS="$IFS"; IFS=$'\n'
    lines=( $(grep -E "my .peer_addr" /usr/share/centreon/bin/centreon-central-sync) )
    IFS="$oIFS"
    # Now that we have the lines containing $peer_addr declarations, we parse them and keep only the last value
    regex='^.*=.*['\''"](.+)['\''"].*$'
    for line in "${lines[0]}" ; do
        #declare -p line
        if [[ "$line" =~ $regex ]] ; then
            peer_ip="${BASH_REMATCH[1]}"
        fi

    done

    # Getting peer hostname via SSH
    ssh_peer_ip="$(timeout 1 sudo -u centreon ssh $peer_ip -- '/usr/sbin/ip a' | grep -w "$peer_ip")"
    ret=$?

    if [[ $ret == 0 ]] ; then
        echo -e "Checking SSH login to '$peer_ip'\t\t\t\t\t[${OK_STRING}]"
    else
        echo -e "Checking SSH login to '$peer_ip'\t\t\t\t\t[${KO_STRING}]"
    fi
    # Checking that central broker is not linked to cbd
    local config_file_lines config_file
    config_file_lines=($(grep configuration_file /etc/centreon-broker/watchdog.xml | sed -E 's/.*CDATA\[(.*)\]\].*$/\1/g'))
    if [[ "${#config_file_lines[@]}" == 1 && "${config_file_lines/rrd/}" != "${config_file_lines}" ]] ; then
        echo -e "Checking that central broker is not linked to cbd\t\t\t[${OK_STRING}]"
    else
        echo -e "Checking that central broker is not linked to cbd\t\t\t[${KO_STRING}]"
            echo -e "\tConfig files : "
        for config_file in "${config_file_lines[@]}" ; do
            echo -e "\t\t$config_file "
        done
    fi

    # Checking that install/update directory is not in place on the other node
    if [[ -d /usr/share/centreon/www/install ]] ; then
        echo -e "Checking that no install/update dir is present\t\t\t\t[${KO_STRING}]"
        echo -e "\t/usr/share/centreon/www/install is present"
    else
        echo -e "Checking that no install/update dir is present\t\t\t\t[${OK_STRING}]"
    fi

    # Checking that install/update directory is not in place
    timeout 2 ssh $peer_ip --  test -d /usr/share/centreon/www/install >/dev/null 2>&1
    ret=$?
    if [[ $ret == 0 ]] ; then
        echo -e "Checking that no install/update dir is present on $peer_ip\t[${KO_STRING}]"
        echo -e "\t/usr/share/centreon/www/install is present on $peer_ip"
    else
        echo -e "Checking that no install/update dir is present on $peer_ip\t[${OK_STRING}]"
    fi

    # Checking that there are two output flux in broker configuration (sending to nodes ips and not to vip)
    local rrd_port_lines
    oIFS="$IFS"; IFS=$'\n'
    rrd_port_lines=($(grep -Ew 'port.*5670' "$CENTRAL_BROKER_CONFIG_FILE" ))
    ret=$?; IFS="$oIFS"
    if [[ "${#rrd_port_lines[@]}" != 2 ]] ; then
        echo -e "Checking that 2 RRD flux are configured\t\t\t\t\t[${KO_STRING}]"
        echo -e "\t/etc/centreon-broker/central-broker.xml should contain 2 RRD outputs"
    else
        echo -e "Checking that 2 RRD flux are configured\t\t\t\t\t[${OK_STRING}]"
    fi

    # Checking that the db flux are sent to the virtual IP
    local db_vip_lines

    oIFS="$IFS"; IFS=$'\n'
    db_vip_lines=($(grep "db_host.*$CENTREON_VIP" "$CENTRAL_BROKER_CONFIG_FILE" ))
    ret=$?; IFS="$oIFS"
    if [[ "${#db_vip_lines[@]}" < 2 ]] ; then
        echo -e "Checking that 2+ DB flux are configured with mysql VIP\t\t\t[${KO_STRING}]"
        echo -e "\tMaybe the DB flux are still configured for one particular MySQL node?"
    else
        echo -e "Checking that 2+ DB flux are configured with mysql VIP\t\t\t[${OK_STRING}]"
    fi

    # No use of DNS (in particular in sshd_config)
    # Check /etc/hosts ?

}

################################################################################
# DB-NODE-SPECIFIC FUNCTIONS
################################################################################

function db-deploy-files-dry-run() {

    # Checking mandatory options
    if [[ -z $DB_MASTER_NAME || -z $DB_SLAVE_NAME || -z $DB_TEST_PASSWORD || -z $DB_REPLICATION_PASSWORD ]] ; then
        echo -e "Error: in this mode, db master name, db slave name, db replication password and db test password are all MANDATORY.\n"
        show-help
        exit 1
    fi
    any-deploy-files-dry-run

    echo "cp /etc/centreon-failover/resources/mysql-resources.sh /etc/centreon-failover/resources/mysql-resources.sh_bak_$(date +%F_%H-%M-%S)"
    echo "sed -iE 's/^DBHOSTNAMEMASTER=.*$/DBHOSTNAMEMASTER=\"$DB_MASTER_NAME\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBHOSTNAMESLAVE=.*$/DBHOSTNAMESLAVE=\"$DB_SLAVE_NAME\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBROOTPASSWORD=.*$/DBROOTPASSWORD=\"$DB_TEST_PASSWORD\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBREPLPASSWORD=.*$/DBREPLPASSWORD=\"$DB_REPLICATION_PASSWORD\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "cp /etc/centreon-failover/resources/centreon-cluster-db /etc/sudoers.d/"

    return 0
}
function db-deploy-files() {

    if [[ "$DRY_RUN" ]] ; then
        db-deploy-files-dry-run
    else
        exec-sequence "$(db-deploy-files-dry-run)"
    fi

}

function db-check-files() {

    # Checking the common files
    any-check-files

    DIFF_OPTIONS="--ignore-matching-lines='^DBROOTPASSWORD=' --ignore-matching-lines='^DBREPLPASSWORD=' --ignore-matching-lines='^DBHOSTNAMESLAVE=' --ignore-matching-lines='^DBHOSTNAMEMASTER='" exec-diff /etc/centreon-failover/resources/mysql-resources.sh $GITCLONEPATH/resources/mysql-resources.sh

    # Checking the content of the sudoers file
    exec-diff /etc/sudoers.d/centreon-cluster-db ${GITCLONEPATH}/resources/centreon-cluster

    # Checking the content of the MySQL scripts
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-sync-bigdb.sh ${GITCLONEPATH}/mysql-exploit/mysql-sync-bigdb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/centreondb-smooth-backup.sh ${GITCLONEPATH}/mysql-exploit/centreondb-smooth-backup.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-check-status.sh ${GITCLONEPATH}/mysql-exploit/mysql-check-status.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-dump-smalldb.sh ${GITCLONEPATH}/mysql-exploit/mysql-dump-smalldb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-purge-logs.sh ${GITCLONEPATH}/mysql-exploit/mysql-purge-logs.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-slave2master.sh ${GITCLONEPATH}/mysql-exploit/mysql-slave2master.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-slave-init.sh ${GITCLONEPATH}/mysql-exploit/mysql-slave-init.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-switch-slave-master.sh ${GITCLONEPATH}/mysql-exploit/mysql-switch-slave-master.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-sync-slave-smalldb.sh ${GITCLONEPATH}/mysql-exploit/mysql-sync-slave-smalldb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/purge_mysql_parts.sh ${GITCLONEPATH}/mysql-exploit/purge_mysql_parts.sh

}

function db-plugin-files() {
    local verbose ret

    verbose="$(db-check-files)"

    if [[ "$verbose" ]] ; then
        echo "WARNING: Some differences have been found. Please read extended status information."
        echo "$verbose"
        exit 1
    else
        echo "OK: Everything looks fine."
        exit 0
    fi

}


function db-diagnostic() {

    local lines line IFS oIFS query_result_set_1 query_result_set_2 query_result_line ret_connect_1 ret_connect_2 ret current_host peer_host ssh_peer_host ssh_current_host
    local DBHOSTNAMEMASTER DBHOSTNAMESLAVE DBREPLUSER DBREPLPASSWORD DBROOTUSER DBROOTPASSWORD SAVE_DIR CENTREON_DB CENTREON_STORAGE_DB
    any-diagnostic
    # Getting database credentials
    oIFS="$IFS"
    IFS=$'\n'
    lines=( $(grep -E "^[_A-Z]+=[\"'].+[\"']" /etc/centreon-failover/resources/mysql-resources.sh) )
    IFS="$oIFS"
    # Now that we have the variables listed, we set them
    for line in "${lines[@]}" ; do
        eval "$line"
    done

    # Checking that the current host is able to login to its peer host via SSH
    current_host="$(hostname)"
    current_host="${current_host%%.*}" # get rid of domain extension
    if [[ "$DBHOSTNAMEMASTER" == "$current_host" ]] ; then
        peer_host="$DBHOSTNAMESLAVE"
    else
        peer_host="$DBHOSTNAMEMASTER"
    fi

    # Getting peer hostname via SSH
    ssh_peer_host="$(timeout 1 sudo -u mysql ssh $peer_host -- hostname | cut -d\. -f1)"
    if [[ "$ssh_peer_host" == "$peer_host" ]] ; then
        echo -e "Checking SSH login to '$peer_host'\t\t\t\t\t[${OK_STRING}]"
    else
        echo -e "Checking SSH login to '$peer_host'\t\t\t\t\t[${KO_STRING}]"
    fi
    ssh_current_host="$(timeout 1 sudo -u mysql ssh $peer_host -- ssh $current_host -- hostname | cut -d\. -f1)"
    if [[ "$ssh_current_host" == "$current_host" ]] ; then
        echo -e "Checking SSH login to '$current_host' via '$peer_host'\t\t\t\t[${OK_STRING}]"
    else
        echo -e "Checking SSH login to '$current_host' via '$peer_host'\t\t\t\t[${KO_STRING}]"
    fi

    # Checking mysql connection
    MYSQL_OPTIONS="$MYSQL_OPTIONS_default -u \"$DBROOTUSER\" -p\"$DBROOTPASSWORD\""

    oIFS="$IFS"; IFS=$'\n'
    query_result_set_1=($(exec-mysql "-h $current_host centreon" "SELECT 'OK'"))
    ret_connect_1=$?
    query_result_set_2=($(exec-mysql "-h $peer_host centreon" "SELECT 'OK'"))
    ret_connect_2=$?; IFS="$oIFS"
    if [[ "$ret_connect_1" != 0 || "$query_result_set_1" != 'OK' || "$ret_connect_2" != 0 || "$query_result_set_2" != 'OK' ]] ; then
        echo -e "Connection as '$DBROOTUSER' to '$current_host':\t\t\t\t\t[${KO_STRING}]"
        echo -e "Connection as '$DBROOTUSER' to '$peer_host':\t\t\t\t\t[${KO_STRING}]"
        echo -e "Checking that no anonymous user is registered:\t\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that no user is registered without a password:\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBROOTUSER' does not have several passwords:\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBROOTUSER'@'%' has all privileges:\t\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBROOTUSER'@'localhost' has all privileges:\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBREPLUSER'@'%' has the right privileges:\t\t[${SKIPPED_STRING}]"

        return 1
    fi
    echo -e "Connection as '$DBROOTUSER' to '$current_host':\t\t\t\t\t[${OK_STRING}]"
    echo -e "Connection as '$DBROOTUSER' to '$peer_host':\t\t\t\t\t[${OK_STRING}]"

    if [[ -z "$DB_TEST_PASSWORD" ]] ; then
        echo -e "$EXCLAMATION_SIGN You must provide the local root password to be able to check the following points. Please provide it by using --db-test-password"
        echo -e "Checking that no anonymous user is registered:\t\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that no user is registered without a password:\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBROOTUSER' does not have several passwords:\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBROOTUSER'@'%' has all privileges:\t\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBROOTUSER'@'localhost' has all privileges:\t\t\t[${SKIPPED_STRING}]"
        echo -e "Checking that '$DBREPLUSER'@'%' has the right privileges:\t\t[${SKIPPED_STRING}]"
        return 1
    fi
    MYSQL_OPTIONS="$MYSQL_OPTIONS_default -u root -p\"$DB_TEST_PASSWORD\""
    # Checking that no anonymous user is configured
    oIFS="$IFS"; IFS=$'\n'
    query_result_set=($(exec-mysql mysql "SELECT CONCAT(user, '@', host) from user where user = ''"))
    ret=$?; IFS="$oIFS"
    if [[ "${#query_result_set[@]}" > 0 ]] ; then
        echo -e "Checking that no anonymous user is registered:\t\t\t\t[${KO_STRING}]"
        for query_result_line in "${query_result_set[@]}" ; do
            echo "$query_result_line must be dropped"
        done
    else
        echo -e "Checking that no anonymous user is registered:\t\t\t\t[${OK_STRING}]"
    fi

    # Checking that no user is registered without a password
    oIFS="$IFS"; IFS=$'\n'
    query_result_set=($(exec-mysql mysql "SELECT CONCAT(user, '@', host) from user where password = ''"))
    ret=$?; IFS="$oIFS"
    if [[ "${#query_result_set[@]}" > 0 ]] ; then
        echo -e "Checking that no user is registered without a password:\t\t\t[${KO_STRING}]"
        for query_result_line in "${query_result_set[@]}" ; do
            echo "$query_result_line must have a password"
        done
    else
        echo -e "Checking that no user is registered without a password:\t\t\t[${OK_STRING}]"
    fi

    # Checking that distant and local root have the same password
    oIFS="$IFS"; IFS=$'\n'
    query_result_set=($(exec-mysql mysql "SELECT DISTINCT(password) from user where user = '$DBROOTUSER'"))
    ret=$?; IFS="$oIFS"
    if [[ "${#query_result_set[@]}" > 1 ]] ; then
        echo -e "Checking that '$DBROOTUSER' does not have several passwords:\t\t\t[${KO_STRING}]"
        for query_result_line in "${query_result_set[@]}" ; do
            echo "$query_result_line must have a password"
        done
    else
        echo -e "Checking that '$DBROOTUSER' does not have several passwords:\t\t\t[${OK_STRING}]"
    fi

    # Checking that distant and local root@% have the right GRANTS
    oIFS="$IFS"; IFS=$'\n'
    query_result_set=($(exec-mysql mysql "SHOW GRANTS FOR '$DBROOTUSER'@'%'" | grep 'ALL PRIVILEGES'))
    ret=$?; IFS="$oIFS"
    if [[ "${#query_result_set[@]}" < 1 ]] ; then
        echo -e "Checking that '$DBROOTUSER'@'%' has all privileges:\t\t\t\t[${KO_STRING}]"
        for query_result_line in "${query_result_set[@]}" ; do
            echo "$query_result_line must have a password"
        done
    else
        echo -e "Checking that '$DBROOTUSER'@'%' has all privileges:\t\t\t\t[${OK_STRING}]"
    fi

    # Checking that distant and local root@localhost have the right GRANTS
    oIFS="$IFS"; IFS=$'\n'
    query_result_set=($(exec-mysql mysql "SHOW GRANTS FOR '$DBROOTUSER'@'localhost'" | grep 'ALL PRIVILEGES'))
    ret=$?; IFS="$oIFS"
    if [[ "${#query_result_set[@]}" < 1 ]] ; then
        echo -e "Checking that '$DBROOTUSER'@'localhost' has all privileges:\t\t\t[${KO_STRING}]"
        for query_result_line in "${query_result_set[@]}" ; do
            echo "$query_result_line must have a password"
        done
    else
        echo -e "Checking that '$DBROOTUSER'@'localhost' has all privileges:\t\t\t[${OK_STRING}]"
    fi

    # Checking that centreon-repl has the right GRANTS
    oIFS="$IFS"; IFS=$'\n'
    query_result_set=($(exec-mysql mysql "SHOW GRANTS FOR '$DBREPLUSER'@'%'" | grep 'RELOAD, PROCESS, SUPER, REPLICATION SLAVE, REPLICATION CLIENT'))
    ret=$?; IFS="$oIFS"
    if [[ "${#query_result_set[@]}" < 1 ]] ; then
        echo -e "Checking that '$DBREPLUSER'@'%' has the right privileges:\t\t[${KO_STRING}]"
        for query_result_line in "${query_result_set[@]}" ; do
            echo "$query_result_line must have a password"
        done
    else
        echo -e "Checking that '$DBREPLUSER'@'%' has the right privileges:\t\t[${OK_STRING}]"
    fi

    # Checking that there is no specific locations for root and centroen-repl (useless if passwords match)

}

################################################################################
# 3 SERVERS CENTRAL-DATABASE FUNCTIONS
################################################################################

function 3srv-deploy-files-dry-run() {
    local peer_ip_address

    # Checking mandatory options
    if [[ -z $CENTRAL_MASTER_IP || -z $CENTRAL_SLAVE_IP ]] ; then
        echo -e "Error: in this mode, central master and slave IPs are MANDATORY.\n"
        show-help
        exit 1
    fi
    if [[ -z $DB_MASTER_NAME || -z $DB_SLAVE_NAME || -z $DB_TEST_PASSWORD || -z $DB_REPLICATION_PASSWORD ]] ; then
        echo -e "Error: in this mode, db master name, db slave name, db replication password and db test password are all MANDATORY.\n"
        show-help
        exit 1
    fi

    # Calling the common files deployment
    central-deploy-files-dry-run

    echo "cp /etc/centreon-failover/resources/mysql-resources.sh /etc/centreon-failover/resources/mysql-resources.sh_bak_$(date +%F_%H-%M-%S)"
    echo "sed -iE 's/^DBHOSTNAMEMASTER=.*$/DBHOSTNAMEMASTER=\"$DB_MASTER_NAME\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBHOSTNAMESLAVE=.*$/DBHOSTNAMESLAVE=\"$DB_SLAVE_NAME\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBROOTPASSWORD=.*$/DBROOTPASSWORD=\"$DB_TEST_PASSWORD\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBREPLPASSWORD=.*$/DBREPLPASSWORD=\"$DB_REPLICATION_PASSWORD\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "cat /etc/centreon-failover/resources/centreon-cluster-db > /etc/sudoers.d/centreon-cluster-db"

    return 0
}

function 3srv-deploy-files() {

    if [[ "$DRY_RUN" ]] ; then
        3srv-deploy-files-dry-run
    else
        exec-sequence "$(3srv-deploy-files-dry-run)"
    fi

}

function 3srv-check-files() {
    local file filebasename file_permission_errors

    # Checking the common files
    central-check-files

    DIFF_OPTIONS="--ignore-matching-lines='^DBROOTPASSWORD=' --ignore-matching-lines='^DBREPLPASSWORD=' --ignore-matching-lines='^DBHOSTNAMESLAVE=' --ignore-matching-lines='^DBHOSTNAMEMASTER='" exec-diff /etc/centreon-failover/resources/mysql-resources.sh $GITCLONEPATH/resources/mysql-resources.sh

    # Checking the content of the sudoers file
    exec-diff /etc/sudoers.d/centreon-cluster-db ${GITCLONEPATH}/resources/centreon-cluster-db
    exec-diff /etc/sudoers.d/centreon-cluster ${GITCLONEPATH}/resources/centreon-cluster

    # Checking the content of the MySQL scripts
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-sync-bigdb.sh ${GITCLONEPATH}/mysql-exploit/mysql-sync-bigdb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/centreondb-smooth-backup.sh ${GITCLONEPATH}/mysql-exploit/centreondb-smooth-backup.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-check-status.sh ${GITCLONEPATH}/mysql-exploit/mysql-check-status.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-dump-smalldb.sh ${GITCLONEPATH}/mysql-exploit/mysql-dump-smalldb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-purge-logs.sh ${GITCLONEPATH}/mysql-exploit/mysql-purge-logs.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-slave2master.sh ${GITCLONEPATH}/mysql-exploit/mysql-slave2master.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-slave-init.sh ${GITCLONEPATH}/mysql-exploit/mysql-slave-init.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-switch-slave-master.sh ${GITCLONEPATH}/mysql-exploit/mysql-switch-slave-master.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-sync-slave-smalldb.sh ${GITCLONEPATH}/mysql-exploit/mysql-sync-slave-smalldb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/purge_mysql_parts.sh ${GITCLONEPATH}/mysql-exploit/purge_mysql_parts.sh

}

function 3srv-plugin-files() {
    local verbose ret

    verbose="$(3srv-check-files)"

    if [[ "$verbose" ]] ; then
        echo "WARNING: Some differences have been found. Please read extended status information."
        echo "$verbose"
        exit 1
    else
        echo "OK: Everything looks fine."
        exit 0
    fi

}

function 3srv-diagnostic() {

    local lines line IFS oIFS current_ip peer_ip
    # Checking that all services are enabled/disabled as they should
    central-diagnostic
    db-diagnostic

    # No use of DNS (in particular in sshd_config)
    # Check /etc/hosts ?

}

################################################################################
# 2 SERVERS CENTRAL-DATABASE FUNCTIONS
################################################################################

function 2srv-deploy-files-dry-run() {
    local peer_ip_address

    # Checking mandatory options
    if [[ -z $CENTRAL_MASTER_IP || -z $CENTRAL_SLAVE_IP ]] ; then
        echo -e "Error: in this mode, central master and slave IPs are MANDATORY.\n"
        show-help
        exit 1
    fi
    if [[ -z $DB_MASTER_NAME || -z $DB_SLAVE_NAME || -z $DB_TEST_PASSWORD || -z $DB_REPLICATION_PASSWORD ]] ; then
        echo -e "Error: in this mode, db master name, db slave name, db replication password and db test password are all MANDATORY.\n"
        show-help
        exit 1
    fi

    # Calling the common files deployment
    central-deploy-files-dry-run

    echo "cp /etc/centreon-failover/resources/mysql-resources.sh /etc/centreon-failover/resources/mysql-resources.sh_bak_$(date +%F_%H-%M-%S)"
    echo "sed -iE 's/^DBHOSTNAMEMASTER=.*$/DBHOSTNAMEMASTER=\"$DB_MASTER_NAME\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBHOSTNAMESLAVE=.*$/DBHOSTNAMESLAVE=\"$DB_SLAVE_NAME\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBROOTPASSWORD=.*$/DBROOTPASSWORD=\"$DB_TEST_PASSWORD\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "sed -iE 's/^DBREPLPASSWORD=.*$/DBREPLPASSWORD=\"$DB_REPLICATION_PASSWORD\"/' /etc/centreon-failover/resources/mysql-resources.sh"
    echo "cat /etc/centreon-failover/resources/centreon-cluster-db > /etc/sudoers.d/centreon-cluster-db"

    return 0
}

function check-selinux() {

    local SELINUX_DISABLED=$(getenforce)
    if [[ "$SELINUX_DISABLED" != 'Disabled' ]] ; then
        echo -e "Checking that service SELinux is disabled\t\t\t[${KO_STRING}]"
        echo "/!\\ SELinux is still enabled /!\\"
        echo "Now changing /etc/selinux/config file to disable it."
        sed -i.bak 's/SELINUX=enabled/SELINUX=disabled/' /etc/selinux/config
        echo '-----------'
        grep -vE '^\s*(#.*|$)' /etc/selinux/config
        echo '-----------'
        echo "Please check the quoted file above is ok and reboot before running this script again."
        return 1
    else
        echo -e "Checking that service SELinux is disabled\t\t\t[${OK_STRING}]"
        return 0
    fi

}

function check-vg-free() {

    local VG_FREE VG_FREE_UNIT VG_FREE_VALUE MOUNT_DEVICE MOUNT_POINT

    MOUNT_DEVICE=$(df -P "$DATA_DIR" | tail -1 | awk '{ print $1 }')
    MOUNT_POINT=$(df -P "$DATA_DIR" | tail -1 | awk '{ print $6 }')

    if [[ -z "$MOUNT_DEVICE" || -z "$MOUNT_POINT" ]] ; then
        echo -e "Checking that $DATA_DIR is on a mountpoint\t\t\t[${KO_STRING}]"
        return 1
    fi

    VG_NAME=$(lvdisplay -c "$MOUNT_DEVICE" | cut -d : -f 2)
    LV_NAME=$(lvdisplay -c "$MOUNT_DEVICE" | cut -d : -f 1)

    if [[ -z "$VG_NAME" || -z "$LV_NAME" ]] ; then
        echo -e "Checking that $DATA_DIR is on a logical volume\t\t\t[${KO_STRING}]"
        return 1
    fi

    FREE_PE=$(vgdisplay -c "$VG_NAME" | cut -d : -f 16)
    SIZE_PE=$(vgdisplay -c "$VG_NAME" | cut -d : -f 13)
    FREE_GB=$((FREE_PE * SIZE_PE / 1024 / 1024))

    if (( $FREE_GB >= 1 )) ; then
        echo -e "Checking that VG $VG_NAME has at least 1GB free (${FREE_GB}GB)\t\t\t[${OK_STRING}]"
        return 0
    else
        echo -e "Checking that VG $VG_NAME has at least 1GB free (${FREE_GB}GB)\t\t\t[${OK_STRING}]"
        return 1
    fi
}

function disable-firewall() {

    systemctl stop firewalld
    systemctl disable firewalld
    iptables -F

}

function disable-ipv6() {

    local IPV6_DISABLED=$(</proc/sys/net/ipv6/conf/all/disable_ipv6)

    if [[ "$IPV6_DISABLED" != 1 ]] ; then
        cat >> /etc/sysctl.conf <<EOF
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv4.tcp_retries2 = 3
net.ipv4.tcp_keepalive_time = 200
net.ipv4.tcp_keepalive_probes = 2
net.ipv4.tcp_keepalive_intvl = 2
EOF
    systemctl restart network
    fi

}

function yum-install () {

    local YUM_OUTPUT=$(yum install -y $*)
    local YUM_RETURN=$?

    if [[ $YUM_RETURN == 0 ]] ; then
        echo -e "Installing $* \t\t\t[${OK_STRING}]"
        return 0
    else
        echo -e "Installing $* \t\t\t[${KO_STRING}]"
        echo "$YUM_OUTPUT"
        return 1
    fi

}

function rewrite-etc-hosts() {

    local DATE_TS=$(date +%F_%H-%M-%S)

    cp /etc/hosts{,_$DATE_TS} || return 1
    cat >/etc/hosts <<EOF
127.0.0.1   localhost localhost.localdomain localhost4 localhost4.localdomain4
${CENTRAL_MASTER_IP}  ${DB_MASTER_NAME}
${CENTRAL_SLAVE_IP}  ${DB_SLAVE_NAME}
EOF

}

function check-install-complete() {

    if [[ ! -e /usr/share/centreon/www/install ]] ; then
        echo  -e "Checking that installation is completed \t\t\t[${OK_STRING}]"
        return 0
    else
        echo  -e "Checking that installation is completed \t\t\t[${KO_STRING}]"
        echo "Directory $INSTALL_DIR found. Please complete installation (mandatory on master) or move/delete it."
        return 1
    fi

}

function check-central-broker-not-linked-to-cbd() {

    grep central-broker-master /etc/centreon-broker/watchdog.json >/dev/null 2>&1
    local CMD_RETURN=$?
    if [[ $CMD_RETURN == 0 ]] ; then
        echo  -e "Checking that central broker is not linked to cbd\t\t\t[${KO_STRING}]"
        return 1
    else
        echo  -e "Checking that central broker is not linked to cbd\t\t\t[${OK_STRING}]"
        return 0
    fi

}

function check-central-broker-is-configured() {

    # Checking that there are two output flux in broker configuration (sending to nodes ips and not to vip)
    local rrd_port_lines
    oIFS="$IFS"; IFS=$'\n'
    rrd_port_lines=($(grep -Ew "port.*${RRD_TCP_PORT}" "$CENTRAL_BROKER_CONFIG_FILE" ))
    ret=$?; IFS="$oIFS"
    if [[ "${#rrd_port_lines[@]}" != 2 ]] ; then
        echo -e "Checking that 2 RRD flux are configured\t\t\t\t\t[${KO_STRING}]"
        echo -e "\t$CENTRAL_BROKER_CONFIG_FILE should contain 2 outputs targetting port $RRD_TCP_PORT. If broker config file is different from $CENTRAL_BROKER_CONFIG_FILE or RRD broker TCP port is different from $RRD_TCP_PORT, then customize it at the beginning of this script."
    else
        echo -e "Checking that 2 RRD flux are configured\t\t\t\t\t[${OK_STRING}]"
    fi

    # Checking that the db flux are sent to the virtual IP
    local db_vip_ip db_vip_lines
    db_vip_ip="$(pcs resource show vip_mysql | grep ip= | sed -e 's/.*ip=\([^ ]*\) .*/\1/')"
    oIFS="$IFS"; IFS=$'\n'
    db_vip_lines=($(grep "db_host.*$db_vip_ip" "$CENTRAL_BROKER_CONFIG_FILE" ))
    ret=$?; IFS="$oIFS"
    if [[ "${#db_vip_lines[@]}" < 2 ]] ; then
        echo -e "Checking that 2+ DB flux are configured with mysql VIP\t\t\t[${KO_STRING}]"
        echo -e "\tMaybe the DB flux are still configured for one particular MySQL node?"
    else
        echo -e "Checking that 2+ DB flux are configured with mysql VIP\t\t\t[${OK_STRING}]"
    fi

    if [ grep central-broker-master /etc/centreon-broker/watchdog.xml >/dev/null 2>&1 ] ; then
        echo -e "Checking that central broker is not linked to cbd\t\t\t[${KO_STRING}]"
        return 1
    else
        echo -e "Checking that central broker is not linked to cbd\t\t\t[${OK_STRING}]"
        return 0
    fi

}

function 2srv-deploy-files() {

    check-selinux || exit 1
    disable-firewall >/dev/null 2>&1 || exit 1
    disable-ipv6 || exit 1
    check-vg-free || exit 1
    yum-install epel-release || exit 1
    yum-install wget vim git pacemaker corosync resource-agents pcs corosync-qnetd corosync-qdevice pssh perl-Linux-Inotify2 perl-common-sense || exit 1
    rewrite-etc-hosts
    check-install-complete || exit 1
    check-central-broker-not-linked-to-cbd || exit 1

    if [[ "$DRY_RUN" ]] ; then
        2srv-deploy-files-dry-run
    else
        exec-sequence "$(2srv-deploy-files-dry-run)"
    fi

}

function 2srv-check-files() {
    local file filebasename file_permission_errors

    # Checking the common files
    central-check-files

    DIFF_OPTIONS="--ignore-matching-lines='^DBROOTPASSWORD=' --ignore-matching-lines='^DBREPLPASSWORD=' --ignore-matching-lines='^DBHOSTNAMESLAVE=' --ignore-matching-lines='^DBHOSTNAMEMASTER='" exec-diff /etc/centreon-failover/resources/mysql-resources.sh $GITCLONEPATH/resources/mysql-resources.sh

    # Checking the content of the sudoers file
    exec-diff /etc/sudoers.d/centreon-cluster-db ${GITCLONEPATH}/resources/centreon-cluster-db
    exec-diff /etc/sudoers.d/centreon-cluster ${GITCLONEPATH}/resources/centreon-cluster

    # Checking the content of the MySQL scripts
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-sync-bigdb.sh ${GITCLONEPATH}/mysql-exploit/mysql-sync-bigdb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/centreondb-smooth-backup.sh ${GITCLONEPATH}/mysql-exploit/centreondb-smooth-backup.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-check-status.sh ${GITCLONEPATH}/mysql-exploit/mysql-check-status.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-dump-smalldb.sh ${GITCLONEPATH}/mysql-exploit/mysql-dump-smalldb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-purge-logs.sh ${GITCLONEPATH}/mysql-exploit/mysql-purge-logs.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-slave2master.sh ${GITCLONEPATH}/mysql-exploit/mysql-slave2master.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-slave-init.sh ${GITCLONEPATH}/mysql-exploit/mysql-slave-init.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-switch-slave-master.sh ${GITCLONEPATH}/mysql-exploit/mysql-switch-slave-master.sh
    exec-diff /etc/centreon-failover/mysql-exploit/mysql-sync-slave-smalldb.sh ${GITCLONEPATH}/mysql-exploit/mysql-sync-slave-smalldb.sh
    exec-diff /etc/centreon-failover/mysql-exploit/purge_mysql_parts.sh ${GITCLONEPATH}/mysql-exploit/purge_mysql_parts.sh

}

function 2srv-plugin-files() {

    local verbose ret

    verbose="$(2srv-check-files)"

    if [[ "$verbose" ]] ; then
        echo "WARNING: Some differences have been found. Please read extended status information."
        echo "$verbose"
        exit 1
    else
        echo "OK: Everything looks fine."
        exit 0
    fi

}

function 2srv-diagnostic() {

    local lines line IFS oIFS current_ip peer_ip

    # Checking that all services are enabled/disabled as they should
    central-diagnostic
    db-diagnostic

    # No use of DNS (in particular in sshd_config)
    # Check /etc/hosts ?

}


################################################################################

function show-help() {
    echo "Usage: ${0##*/} [OPTIONS]"
    echo
    echo "OPTIONS can be:"
    echo -e "\t--help|-h\t\t\t\tShow this help"
    echo -e "\t--node-type=(any|central|3srv)\t\tSelect type of node (default: any, which can apply to any node)"
    echo -e "\t--action=ACTION\t\t\t\tSelect ACTION between check, deploy, plugin and interactive-fix (default: check)"
    echo -e "\t\tcheck\t\tChecks if there are differences between installed files and reference files."
    echo -e "\t\tplugin\t\tIdentical to check, but provides a nagios-plugin like output and return code."
    echo -e "\t\tdeploy\t\tOverwrites all installed files with the reference files. Recommended in install."
    echo -e "\t\tinteractive-fix\tShows differences file by file and asks whether to overwrite it or not. Recommended in upgrade."
    echo -e "\t--no-dry-run\t\t\t\tIf action is 'deploy', deploy for REAL."
    echo -e "\t--central-master-ip=IPADDR\t\tEnter the central master IP address"
    echo -e "\t--central-slave-ip=IPADDR\t\tEnter the central slave IP address"
    echo -e "\t--db-master-name=HNAME\t\t\tEnter the db master name"
    echo -e "\t--db-slave-name=HNAME\t\t\tEnter the db slave name"
    echo -e "\t--db-test-password=passwd\t\tEnter the db test password"
    echo -e "\t--db-replication-password=passwd\tEnter the db replication user's (centreon-repl) password"
    echo -e "\t--debug\t\t\t\t\tDisplay debug information"
    echo
}

# Reading options
if (( $# == 0 )) ; then
    show-help
    exit 1
fi

while (( $# > 0 )) ; do
    arg="$1"
    case $arg in
        --help|-h)
            show-help
            exit 0
            ;;
        --action=*)
            ACTION="${arg#*=}"
            ACTION=${ACTION,,}
            ;;
        --action)
            ACTION="${2,,}"
            shift
            ;;
        --no-dry-run)
            DRY_RUN=
            ;;
        --node-type=*)
            NODE_TYPE="${arg#*=}"
            NODE_TYPE=${NODE_TYPE,,}
            ;;
        --node-type)
            NODE_TYPE="${2,,}"
            shift
            ;;
        --centreon-vip=*)
            CENTREON_VIP="${arg#*=}"
            ;;
        --centreon-vip)
            CENTREON_VIP="${2}"
            shift
            ;;
        --central-master-ip=*)
            CENTRAL_MASTER_IP="${arg#*=}"
            ;;
        --central-master-ip)
            CENTRAL_MASTER_IP="${2}"
            shift
            ;;
        --central-slave-ip=*)
            CENTRAL_SLAVE_IP="${arg#*=}"
            ;;
        --central-slave-ip)
            CENTRAL_SLAVE_IP="${2}"
            shift
            ;;
        --db-master-name=*)
            DB_MASTER_NAME="${arg#*=}"
            ;;
        --db-master-name)
            DB_MASTER_NAME="${2}"
            shift
            ;;
        --db-slave-name=*)
            DB_SLAVE_NAME="${arg#*=}"
            ;;
        --db-slave-name)
            DB_SLAVE_NAME="${2}"
            shift
            ;;
        --db-test-password=*)
            DB_TEST_PASSWORD="${arg#*=}"
            ;;
        --db-test-password)
            DB_TEST_PASSWORD="${2}"
            shift
            ;;
        --db-replication-password=*)
            DB_REPLICATION_PASSWORD="${arg#*=}"
            ;;
        --db-replication-password)
            DB_REPLICATION_PASSWORD="${2}"
            shift
            ;;
        --debug)
            DEBUG=1
            ;;
        *)
            echo -e "*** Error: Argument '$arg' is not supported\n"
            show-help
            exit 1
        ;;
    esac
    shift
done

case "${NODE_TYPE}" in
    central|any|db|3srv|2srv)
        ;;
    *)
        echo "Node type '$NODE_TYPE' is not handled"
        exit 1
        ;;
esac

if [[ "${ACTION}" == deploy ]] ; then
    if [[ "$DRY_RUN" ]] ; then
        echo "### This is a dry run. To actually deploy files, use the --no-dry-run option."
    else
        echo "### WARNING: This is NOT a dry run. Files are going to be removed if you answer 'yes'"
        read ANSWER
        if [[ "${ANSWER,,}" != 'yes' ]] ; then
            echo -e "\nYou have not typed 'yes', so the program will exit without doing anything"
            exit 0
        fi
    fi
fi

case "${ACTION}" in
    diagnostic)
        ${NODE_TYPE}-diagnostic
        ;;
    check|deploy|plugin)
        ${NODE_TYPE}-${ACTION}-files
        ;;
    interactive-fix)
        # This mode requires patch program
        rpm -q patch >/dev/null 2>&1
        ret=$?
        if [[ $ret != 0 ]] ; then
            echo -e "\nERROR: 'patch' utility is missing. Please install it by running 'yum install -y patch'"
            exit 1
        fi
        INTERACTIVE_FIX=1
        ${NODE_TYPE}-check-files
        echo -e "\nNow it might be a good idea to run this command:\nsystemctl daemon-reload"
        ;;
    *)
        echo "'$ACTION' action is not supported"
        exit 1
        ;;
esac



