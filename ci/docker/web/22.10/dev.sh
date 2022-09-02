#!/bin/sh

set -e
set -x

service mysql start
php /tmp/dev/update-centreon.php -c /etc/centreon/centreon.conf.php
mysql centreon < /tmp/dev/sql/standard.sql
mysql centreon < /tmp/dev/sql/media.sql
service mysql stop
rm -rf /usr/share/centreon/www/install
