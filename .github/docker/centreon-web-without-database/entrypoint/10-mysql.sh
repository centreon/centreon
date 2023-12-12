#!/bin/sh

mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} 2>/dev/null < /usr/local/src/centreon.sql
mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} 2>/dev/null < /usr/local/src/centreon_storage.sql

sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/centreon.conf.php
sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/conf.pm

mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"

# mydumper -u root -p centreon -h 172.18.0.2 -P 3306 -G -v 3 -o /tmp/mydumper-centreon -B centreon
# myloader -u root -p centreon -h 172.18.0.4 -P 3306 -s centreon -o -v 3 -d /tmp/mydumper-centreon
# mydumper -u root -p centreon -h 172.18.0.2 -P 3306 -G -v 3 -o /tmp/mydumper-centreon_storage -B centreon_storage
# myloader -u root -p centreon -h 172.18.0.4 -P 3306 -s centreon_storage -o -v 3 -d /tmp/mydumper-centreon_storage
