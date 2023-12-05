#!/bin/bash
if [ ! -x /sbin/chkconfig ]; then
    apt-get update
    apt-get install -y chkconfig
fi

/sbin/chkconfig --add dsmd
/sbin/chkconfig --level 345 dsmd on
service dsmd start > /dev/null 2>&1 || echo "Failed to start dsmd service"
