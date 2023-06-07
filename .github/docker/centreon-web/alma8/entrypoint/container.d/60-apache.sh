#!/bin/sh

if [ -e /opt/rh/httpd24/enable ] ; then
  source /opt/rh/httpd24/enable
fi
httpd -k start
