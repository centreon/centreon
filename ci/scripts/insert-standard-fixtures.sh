#!/bin/bash 

set -ex 

mysql centreon < /tmp/standard.sql
mysql centreon < /tmp/media.sql
mysql centreon < /tmp/openldap.sql
centreon -u admin -p Centreon\!2021 -a APPLYCFG -v 1