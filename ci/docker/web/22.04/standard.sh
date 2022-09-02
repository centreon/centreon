#!/bin/sh

set -e
set -x

service mysql start
# Temporary fix to provide default setup while we're working on free plugin packs.
mysql centreon < /tmp/standard/sql/standard.sql
mysql centreon < /tmp/standard/sql/media.sql
mysql centreon < /tmp/standard/sql/openldap.sql
centreon -u admin -p Centreon\!2021 -a APPLYCFG -v 1
service mysql stop
