#!/bin/bash

source /etc/centreon-ha/mysql-resources.sh
#DEBUG=1

usage()
{
    echo
    echo "Usage: $0 <new-master>"
    echo
}

cmd_line()
{
	:
}

# Checking that we have the right argument (master-to-be)
if [[ $# < 1 ]] ; then
    usage
    exit
fi
NEW_MASTER_IPADDR="$1"

timeout 0.1 ping -W 1 -c 1 "$NEW_MASTER_IPADDR"  >/dev/null
if [[ $? != 0 ]] ; then
    echo "Host $NEW_MASTER_IPADDR is not responding to ping. Nothing can be done. Exiting."
    exit 1
fi

# Is there a master? (is the vip used?)
VIP_USED=
timeout 0.1 ping -W 1 -c 1 "$CENTRAL_VIP_IPADDR"  >/dev/null
[[ $? == 0 ]] && VIP_USED=1
[[ "$DEBUG" ]] && declare -p VIP_USED
# VIP not used => no master
if [[ "$VIP_USED" == 1 ]] ; then
    NEW_MASTER_HOSTNAME=$(sudo -u centreon ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ${NEW_MASTER_IPADDR} -- hostname 2>/dev/null)
    CURRENT_MASTER_HOSTNAME=$(sudo -u centreon ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ${CENTRAL_VIP_IPADDR} -- hostname 2>/dev/null)
    [[ "$DEBUG" ]] && declare -p NEW_MASTER_HOSTNAME CURRENT_MASTER_HOSTNAME
    # Is the master-to-be different from the current one?
    if [[ "$NEW_MASTER_HOSTNAME" != "$CURRENT_MASTER_HOSTNAME" ]] ; then
        # Yes ? Then shut its services down
        [[ "$VERBOSE" ]] && echo "Host ${CURRENT_MASTER_HOSTNAME} is the current master. Wanted master: ${NEW_MASTER_IPADDR}"
        echo "Stopping centreon.service on ${CURRENT_MASTER_HOSTNAME}..."
        [[ "$DEBUG" ]] && set -x
        SERVICES_SHUTDOWN_OUTPUT=$(sudo -u centreon ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ${CURRENT_MASTER_HOSTNAME} -- sudo systemctl stop centreon 2>&1)
        SERVICES_SHUTDOWN_RC=$?
        [[ "$DEBUG" ]] && set +x
        if [[ "$SERVICES_SHUTDOWN_RC" != 0 ]] ; then
            echo "*** An error occured while stopping centreon.service on ${CURRENT_MASTER_HOSTNAME}."
            echo "$SERVICES_SHUTDOWN_OUTPUT"
            exit 1
        fi
        echo "Unmounting VIP on ${CURRENT_MASTER_HOSTNAME}..."
        [[ "$DEBUG" ]] && set -x
        VIP_SHUTDOWN_OUTPUT=$(sudo -u centreon ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ${CURRENT_MASTER_HOSTNAME} -- sudo ifdown "${CENTRAL_VIP_IFNAME}\:1" 2>&1)
        VIP_SHUTDOWN_RC=$?
        [[ "$DEBUG" ]] && set +x
        if [[ "$VIP_SHUTDOWN_RC" != 0 ]] ; then
            echo "*** An error occured while removing the VIP from ."
            echo "$VIP_SHUTDOWN_OUTPUT" 
            exit 1
        fi
    else
        # No ? Then there is nothing to do --> exit
        echo "Host ${NEW_MASTER_IPADDR} is already the current master :-)"
        exit 0
    fi
fi


# To that point, we have no Centreon master, we have to turn it on
# First mount the VIP
echo "Adding vip to ${NEW_MASTER_IPADDR}..."
[[ "$DEBUG" ]] && set -x
VIP_START_OUTPUT=$(sudo -u centreon ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ${NEW_MASTER_IPADDR} -- sudo ifup "${CENTRAL_VIP_IFNAME}:1" 2>&1)
VIP_START_RC=$?
[[ "$DEBUG" ]] && set +x
if [[ "$VIP_START_RC" != 0 ]] ; then
    echo "*** An error occured while adding the VIP."
    echo "$VIP_START_OUTPUT"
    exit 1
fi
# Now start the services (centreon.service should start them all)

echo "Starting centreon.service on ${NEW_MASTER_IPADDR}..."
[[ "$DEBUG" ]] && set -x
SERVICES_START_OUTPUT=$(sudo -u centreon ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ${NEW_MASTER_IPADDR} -- sudo systemctl start centreon 2>&1)
SERVICES_START_RC=$?
[[ "$DEBUG" ]] && set +x
if [[ "$SERVICES_START_RC" != 0 ]] ; then
    echo "*** An error occured while starting centreon.service."
    echo "$SERVICES_START_OUTPUT"
    exit 1
fi

