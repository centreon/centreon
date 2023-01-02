#!/bin/bash

source /etc/centreon-ha/mysql-resources.sh
#DEBUG=1

if [[ -z $MYSQL_VIP_IPADDR ]] ; then
    echo "*** No MYSQL_VIP_IPADDR environment variable defined in /etc/centreon-ha/mysql-resources.sh" >&2
    exit 1
fi

# Check who is the curent MySQL master
IS_PRIMARY_MASTER=$(mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMEMASTER" "-p$DBROOTPASSWORD" -e "SHOW GLOBAL VARIABLES LIKE 'read_only'" | grep -c OFF)

IS_SECONDARY_MASTER=$(mysql -f -u "$DBROOTUSER" -h "$DBHOSTNAMESLAVE" "-p$DBROOTPASSWORD" -e "SHOW GLOBAL VARIABLES LIKE 'read_only'" | grep -c OFF)

[[ "$DEBUG" ]] && declare -p IS_PRIMARY_MASTER IS_SECONDARY_MASTER

if (( IS_PRIMARY_MASTER == 1 )) && (( IS_SECONDARY_MASTER == 1 )) ; then
    # Both db with read_only off => not normal
    echo "/!\\ Both db with read_only off => not normal"
    exit 2
elif (( IS_PRIMARY_MASTER == 0 )) && (( IS_SECONDARY_MASTER == 0 )) ; then
    # Both db with read_only on => not normal either
    echo "/!\\ Both db with read_only on => not normal"
    exit 2
elif (( IS_PRIMARY_MASTER == 1 )) && (( IS_SECONDARY_MASTER == 0 )) ; then
    # Primary db is master
    MASTER_DB="$DBHOSTNAMEMASTER"
    SLAVE_DB="$DBHOSTNAMESLAVE"
elif (( IS_PRIMARY_MASTER == 0 )) && (( IS_SECONDARY_MASTER == 1 )) ; then
    # Secondary db is master
    MASTER_DB="$DBHOSTNAMESLAVE"
    SLAVE_DB="$DBHOSTNAMEMASTER"
fi

[[ "$DEBUG" ]] && declare -p MASTER_DB SLAVE_DB


# See if the VIP address is currently used
VIP_USED=
ping -W 1 -c 1 "$MYSQL_VIP_IPADDR"  >/dev/null
[[ $? == 0 ]] && VIP_USED=1

[[ "$DEBUG" ]] && declare -p VIP_USED

# If the VIP address is used
VIP_OWNER=
if [[ "$VIP_USED" ]] ; then
    # Check who is using it
    MASTER_SERVER_ID=$(mysql -Nf -u "$DBROOTUSER" -h "$MASTER_DB" "-p$DBROOTPASSWORD" -e "SHOW GLOBAL VARIABLES LIKE 'server_id'" | awk '{print $2}')
    SLAVE_SERVER_ID=$(mysql -Nf -u "$DBROOTUSER" -h "$SLAVE_DB" "-p$DBROOTPASSWORD" -e "SHOW GLOBAL VARIABLES LIKE 'server_id'" | awk '{print $2}')
    VIP_OWNER_SERVER_ID=$(mysql -Nf -u "$DBROOTUSER" -h "$MYSQL_VIP_IPADDR" "-p$DBROOTPASSWORD" -e "SHOW GLOBAL VARIABLES LIKE 'server_id'" | awk '{print $2}')
    [[ "$DEBUG" ]] && declare -p MASTER_SERVER_ID SLAVE_SERVER_ID VIP_OWNER_SERVER_ID
    if [[ "$VIP_OWNER_SERVER_ID" == "$MASTER_SERVER_ID" ]] ; then
        VIP_OWNER="$MASTER_DB"
    elif [[ "$VIP_OWNER_SERVER_ID" == "$SLAVE_SERVER_ID" ]] ; then
        VIP_OWNER="$SLAVE_DB"
    else
        echo "MASTER's server_id is $MASTER_SERVER_ID, SLAVE's server_id is $SLAVE_SERVER_ID, VIP owner's server_id is ${VIP_OWNER_SERVER_ID}. WTF?!"
        exit 3
    fi

fi

[[ "$DEBUG" ]] && declare -p VIP_OWNER

# If mysql master != VIP holder
if [[ "$VIP_OWNER" == "$MASTER_DB" ]] ; then
    echo "The VIP address is already at the right place. Nothing to do."
    exit 0
fi

# If we reach this point, it means that the VIP is not mounted on the master server

# If it is mounted on the slave, we need to unmount it first
if [[ "$VIP_USED" ]] ; then
    echo "We have to move the VIP address"
    # Unmount the VIP on the current VIP holder 
    if [[ "$SLAVE_DB" == "$(hostname)" ]] ; then
        [[ "$DEBUG" ]] && echo "Local ifdown"
        ifdown "${MYSQL_VIP_IFNAME}:1"
    else
        # If not, we run it via SSH
        [[ "$DEBUG" ]] && echo "Remote ifdown"
        sudo -u mysql ssh "$SLAVE_DB" -- sudo ifdown "${MYSQL_VIP_IFNAME}:1"
    fi
fi

# Mount the VIP on the current master
#  ip addr add ${MYSQL_VIP_IPADDR}/${MYSQL_VIP_CIDR_NETMASK} brd 192.168.56.255 dev enp0s8
# If the master is the local host, we run it locally
if [[ "$MASTER_DB" == "$(hostname)" ]] ; then
    [[ "$DEBUG" ]] && echo "Local ifup"
    ifup "${MYSQL_VIP_IFNAME}:1"
else
    # If not, we run it via SSH
    [[ "$DEBUG" ]] && echo "Remote ifup"
    sudo -u mysql ssh "$MASTER_DB" -- sudo ifup "${MYSQL_VIP_IFNAME}:1"
fi


# Send gratuitous ARP from the new VIP holder
#arping -q -c 2 -w 3 -D -I $iface $ipaddr

