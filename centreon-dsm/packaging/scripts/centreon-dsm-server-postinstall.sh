#!/bin/bash

/sbin/chkconfig --add dsmd
/sbin/chkconfig --level 345 dsmd on
service dsmd start > /dev/null 2>&1 || echo "Failed to start dsmd service"
